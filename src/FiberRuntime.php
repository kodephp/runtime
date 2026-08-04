<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Fiber 运行时适配器
 *
 * 基于 PHP 原生 Fiber + FiberScheduler 实现的协作式协程运行时，
 * sleep()、Channel 阻塞等操作会让出执行权，实现真正的并发调度
 */
final class FiberRuntime extends AbstractRuntime
{
    private readonly FiberScheduler $scheduler;

    /**
     * @param FiberScheduler|null $scheduler 自定义调度器，默认使用全局实例
     */
    public function __construct(?FiberScheduler $scheduler = null)
    {
        $this->scheduler = $scheduler ?? FiberScheduler::instance();
    }

    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Fiber;
    }

    /**
     * 获取底层调度器
     *
     * @return FiberScheduler 调度器
     */
    public function scheduler(): FiberScheduler
    {
        return $this->scheduler;
    }

    /**
     * 创建绑定当前调度器的通道，使阻塞读写可让出协程执行权
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    #[\Override]
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new CliChannel($capacity, $this->scheduler);
    }

    /**
     * 启动一个协程（立即执行至首次挂起）
     *
     * @param callable $callback 协程函数
     * @return \Fiber Fiber 实例
     */
    #[\Override]
    public function async(callable $callback): \Fiber
    {
        $scopeKey = null;

        return $this->scheduler->spawn(
            function () use ($callback, &$scopeKey): void {
                $current = \Fiber::getCurrent();
                $scopeKey = $current !== null ? $this->fiberScope($current) : null;
                $callback();
            },
            function () use (&$scopeKey): void {
                if ($scopeKey !== null) {
                    $this->flushScope($scopeKey);
                }
            }
        );
    }

    /**
     * 协程内休眠会让出执行权，主流程休眠时顺带驱动调度器
     *
     * @param float $seconds 休眠秒数
     */
    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->scheduler->sleep($seconds);
    }

    /**
     * 并发执行任务并收集结果
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 与任务键一一对应的结果
     */
    #[\Override]
    public function parallel(iterable $tasks): array
    {
        $results = [];

        foreach ($tasks as $key => $task) {
            $results[$key] = null;
            $this->async(static function () use ($task, $key, &$results): void {
                $results[$key] = $task();
            });
        }

        $this->wait();

        return $results;
    }

    /**
     * 驱动调度器直到所有协程结束
     *
     * @throws \Throwable 协程内未捕获的第一个异常
     */
    #[\Override]
    public function wait(): void
    {
        try {
            $this->scheduler->run();
        } finally {
            parent::wait();
        }
    }
}
