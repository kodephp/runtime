<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Fiber 协作式调度器
 *
 * 为 PHP 原生 Fiber 提供就绪队列 + 定时器的事件循环，
 * 使 sleep()、Channel 阻塞等操作真正让出执行权而非忙等
 */
final class FiberScheduler
{
    /**
     * 无定时器时事件循环的最小空转间隔（微秒）
     */
    private const int IDLE_INTERVAL_US = 200;

    private static ?self $instance = null;

    /**
     * 就绪队列
     *
     * @var \SplQueue<\Fiber>
     */
    private \SplQueue $ready;

    /**
     * 已排队的 Fiber ID 集合
     *
     * @var array<int, true>
     */
    private array $queued = [];

    /**
     * 定时器列表：Fiber ID => [唤醒时间, Fiber]
     *
     * @var array<int, array{float, \Fiber}>
     */
    private array $timers = [];

    /**
     * 存活的 Fiber：ID => Fiber
     *
     * @var array<int, \Fiber>
     */
    private array $alive = [];

    /**
     * 协程内未捕获的异常
     *
     * @var list<\Throwable>
     */
    private array $exceptions = [];

    /**
     * 「无法经由 run() 传出」的协程异常处理器，为 null 时写 stderr
     */
    private ?\Closure $errorHandler = null;

    private bool $looping = false;

    public function __construct()
    {
        $this->ready = new \SplQueue();
    }

    /**
     * 获取全局调度器实例
     *
     * @return self 调度器
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 重置全局调度器（主要用于测试）
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 创建并立即启动一个 Fiber
     *
     * @param callable $callback 协程函数
     * @param callable|null $onFinish 协程结束回调（无论成功失败都会执行）
     * @return \Fiber Fiber 实例
     */
    public function spawn(callable $callback, ?callable $onFinish = null): \Fiber
    {
        $fiber = new \Fiber(static function () use ($callback, $onFinish): void {
            try {
                $callback();
            } finally {
                if ($onFinish !== null) {
                    $onFinish();
                }
            }
        });

        $this->alive[spl_object_id($fiber)] = $fiber;
        $this->step($fiber);

        return $fiber;
    }

    /**
     * 让出当前 Fiber 的执行权，等待下一轮调度
     */
    public function yield(): void
    {
        $fiber = \Fiber::getCurrent();
        if ($fiber === null) {
            return;
        }

        $this->enqueue($fiber);
        \Fiber::suspend();
    }

    /**
     * 在协程中休眠指定秒数
     *
     * 处于 Fiber 内时挂起自身并交还执行权；
     * 处于主流程时驱动事件循环直到超时，避免协程被饿死
     *
     * @param float $seconds 休眠秒数
     */
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            $this->yield();
            return;
        }

        $fiber = \Fiber::getCurrent();
        if ($fiber !== null) {
            $this->timers[spl_object_id($fiber)] = [microtime(true) + $seconds, $fiber];
            \Fiber::suspend();
            return;
        }

        $deadline = microtime(true) + $seconds;
        while (($remaining = $deadline - microtime(true)) > 0) {
            if (!$this->tick()) {
                usleep((int) round($remaining * 1_000_000));
                return;
            }
        }
    }

    /**
     * 驱动事件循环直到所有协程结束
     *
     * @throws \Throwable 协程内未捕获的第一个异常
     */
    public function run(): void
    {
        if ($this->looping) {
            return;
        }

        $this->looping = true;
        try {
            while ($this->tick()) {
                // 持续调度直到没有可执行的协程
            }
        } finally {
            $this->looping = false;
        }

        $this->throwPendingException();
    }

    /**
     * 执行一轮调度
     *
     * @return bool 仍有待执行的协程返回 true
     */
    public function tick(): bool
    {
        $this->wakeTimers();

        if (!$this->ready->isEmpty()) {
            $fiber = $this->ready->dequeue();
            unset($this->queued[spl_object_id($fiber)]);
            $this->step($fiber);
            return $this->hasPendingWork();
        }

        if ($this->timers !== []) {
            $delay = $this->nextTimerAt() - microtime(true);
            usleep($delay > 0 ? (int) round($delay * 1_000_000) : self::IDLE_INTERVAL_US);
            $this->wakeTimers();
            return true;
        }

        return false;
    }

    /**
     * 是否仍有待执行的协程
     *
     * @return bool 有返回 true
     */
    public function hasPendingWork(): bool
    {
        return !$this->ready->isEmpty() || $this->timers !== [];
    }

    /**
     * 除当前协程外是否还有其他待执行协程
     *
     * 用于 Channel 阻塞时判断是否会发生死锁
     *
     * @return bool 有返回 true
     */
    public function hasOtherWork(): bool
    {
        $current = \Fiber::getCurrent();
        $currentId = $current !== null ? spl_object_id($current) : 0;

        foreach ($this->timers as $id => $timer) {
            if ($id !== $currentId) {
                return true;
            }
        }

        foreach ($this->ready as $fiber) {
            if (spl_object_id($fiber) !== $currentId) {
                return true;
            }
        }

        return false;
    }

    /**
     * 当前存活的协程数量
     *
     * @return int 协程数量
     */
    public function count(): int
    {
        return count($this->alive);
    }

    /**
     * 当前是否处于本调度器管理的协程内
     *
     * @return bool 是返回 true
     */
    public function inFiber(): bool
    {
        $fiber = \Fiber::getCurrent();

        return $fiber !== null && isset($this->alive[spl_object_id($fiber)]);
    }

    /**
     * 推进一个 Fiber 的执行
     *
     * @param \Fiber $fiber 目标 Fiber
     */
    private function step(\Fiber $fiber): void
    {
        $id = spl_object_id($fiber);

        try {
            if (!$fiber->isStarted()) {
                $fiber->start();
            } elseif ($fiber->isSuspended()) {
                $fiber->resume();
            }
        } catch (\Throwable $e) {
            unset($this->alive[$id], $this->timers[$id], $this->queued[$id]);
            $this->exceptions[] = $e;
            return;
        }

        if ($fiber->isTerminated()) {
            unset($this->alive[$id], $this->timers[$id], $this->queued[$id]);
            return;
        }

        // 协程通过原生 Fiber::suspend() 挂起时，视为一次协作式让出
        if ($fiber->isSuspended() && !isset($this->queued[$id]) && !isset($this->timers[$id])) {
            $this->enqueue($fiber);
        }
    }

    /**
     * 将 Fiber 放入就绪队列
     *
     * @param \Fiber $fiber 目标 Fiber
     */
    private function enqueue(\Fiber $fiber): void
    {
        $id = spl_object_id($fiber);
        if (isset($this->queued[$id])) {
            return;
        }

        $this->queued[$id] = true;
        $this->ready->enqueue($fiber);
    }

    /**
     * 将到期定时器移入就绪队列
     */
    private function wakeTimers(): void
    {
        if ($this->timers === []) {
            return;
        }

        $now = microtime(true);
        foreach ($this->timers as $id => [$wakeAt, $fiber]) {
            if ($wakeAt <= $now) {
                unset($this->timers[$id]);
                $this->enqueue($fiber);
            }
        }
    }

    /**
     * 获取最近的定时器到期时间
     *
     * @return float 到期时间戳
     */
    private function nextTimerAt(): float
    {
        $times = array_column($this->timers, 0);

        return $times === [] ? microtime(true) : min($times);
    }

    /**
     * 抛出协程内未捕获的第一个异常
     *
     * 其余异常没有调用方可达（一次 run() 只能抛一个），必须先交出去，
     * 否则它们登记完就蒸发——表现为「协程明明炸了，进程里一个字都没有」。
     *
     * @throws \Throwable 协程异常
     */
    private function throwPendingException(): void
    {
        if ($this->exceptions === []) {
            return;
        }

        $first = $this->exceptions[0];
        $rest = \array_slice($this->exceptions, 1);

        // 先清再报再抛：上报处理器里若回调 run()，不能看到同一批异常两次
        $this->exceptions = [];
        $this->report($rest);

        throw $first;
    }

    /**
     * 设置「无法经由 run() 传出」的协程异常处理器
     *
     * 常驻进程用它把协程失败接到自己的日志通道；未设置时写 stderr。
     */
    public function setErrorHandler(?callable $handler): void
    {
        $this->errorHandler = $handler === null
            ? null
            : ($handler instanceof \Closure ? $handler : \Closure::fromCallable($handler));
    }

    /**
     * 上报无人接收的协程异常
     *
     * @param list<\Throwable> $exceptions 待上报异常
     */
    private function report(array $exceptions): void
    {
        foreach ($exceptions as $exception) {
            try {
                if ($this->errorHandler !== null) {
                    ($this->errorHandler)($exception);

                    continue;
                }

                // 直写 stderr：error_log() 的去向取决于部署方的 ini（CLI 默认落 stdout，
                // 会被测试框架当成「意外输出」，也会混进响应体）
                \fwrite(\STDERR, \sprintf(
                    '无人接收的协程异常：%s: %s',
                    $exception::class,
                    $exception->getMessage()
                ) . \PHP_EOL);
            } catch (\Throwable) {
                // 上报通道自身失败不能拖垮事件循环
            }
        }
    }
}
