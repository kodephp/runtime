<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时门面类
 *
 * 提供统一的静态接口访问不同运行时环境
 * 支持 Swoole、Swow、Fiber、Process、Thread、Console 和 CLI 模式
 */
final class Runtime
{
    private static ?RuntimeInterface $adapter = null;

    /**
     * 获取当前运行时环境名称
     *
     * @return string 环境名称
     */
    public static function getName(): string
    {
        return self::adapter()->getName();
    }

    /**
     * 获取当前运行时环境枚举
     *
     * @return RuntimeEnvironment 环境枚举
     */
    public static function environment(): RuntimeEnvironment
    {
        return self::adapter()->environment();
    }

    /**
     * 当前运行时是否具备真正的并发能力
     *
     * @return bool 具备返回 true
     */
    public static function supportsConcurrency(): bool
    {
        return self::adapter()->supportsConcurrency();
    }

    /**
     * 异步执行函数
     *
     * @param callable $callback 要执行的函数
     * @return mixed 协程句柄、进程 ID 或同步执行结果
     */
    public static function async(callable $callback): mixed
    {
        return self::adapter()->async($callback);
    }

    /**
     * 以入口函数方式运行并等待全部异步任务结束
     *
     * @param callable $main 入口函数
     * @return mixed 入口函数返回值
     */
    public static function run(callable $main): mixed
    {
        return self::adapter()->run($main);
    }

    /**
     * 并发执行多个任务并按原始键收集结果
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 执行结果
     */
    public static function parallel(iterable $tasks): array
    {
        return self::adapter()->parallel($tasks);
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    public static function sleep(float $seconds): void
    {
        self::adapter()->sleep($seconds);
    }

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    public static function createChannel(int $capacity = 0): ChannelInterface
    {
        return self::adapter()->createChannel($capacity);
    }

    /**
     * 注册当前作用域退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public static function defer(callable $callback): void
    {
        self::adapter()->defer($callback);
    }

    /**
     * 等待所有异步操作完成
     */
    public static function wait(): void
    {
        self::adapter()->wait();
    }

    /**
     * 创建一个等待组（WaitGroup）
     *
     * @param RuntimeInterface|null $runtime 运行时适配器，null 表示使用当前门面运行时
     * @return WaitGroup 等待组实例
     */
    public static function waitGroup(?RuntimeInterface $runtime = null): WaitGroup
    {
        return new WaitGroup($runtime ?? self::adapter());
    }

    /**
     * 创建一个单飞（Once）原语
     *
     * @param RuntimeInterface|null $runtime 运行时适配器，null 表示使用当前门面运行时
     * @return Once 单飞实例
     */
    public static function once(?RuntimeInterface $runtime = null): Once
    {
        return new Once($runtime ?? self::adapter());
    }

    /**
     * 创建一个信号量（Semaphore）并发限流原语
     *
     * @param int $permits 许可数量（必须 ≥ 1）
     * @param RuntimeInterface|null $runtime 运行时适配器，null 表示使用当前门面运行时
     * @return Semaphore 信号量实例
     * @throws \InvalidArgumentException 许可数量小于 1 时抛出
     */
    public static function semaphore(int $permits, ?RuntimeInterface $runtime = null): Semaphore
    {
        return new Semaphore($permits, $runtime ?? self::adapter());
    }

    /**
     * 竞速：并发执行多个任务，返回第一个完成（成功或失败）的结果
     *
     * 其余任务继续在后台运行，但其结果被丢弃（本库无法真正取消协程/纤程）。
     * 若胜出任务抛出异常，则该异常向上传播。
     *
     * 使用模型（与 WaitGroup / Once 一致）：
     * - Fiber / CLI 运行时：可在顶层直接使用
     * - Swoole / Swow 等事件循环运行时：请在 {@see Runtime::run()} 作用域内使用
     *
     * @param callable ...$tasks 待竞速的任务（至少 1 个）
     * @return mixed 第一个完成任务的结果
     * @throws \InvalidArgumentException 未提供任何任务时抛出
     * @throws \Throwable 胜出任务抛出的异常
     */
    public static function race(callable ...$tasks): mixed
    {
        if ($tasks === []) {
            throw new \InvalidArgumentException('race() 至少需要一个任务');
        }

        $runtime = self::adapter();
        // 容量设为任务数：每个任务只推送一次，容量充足则永不阻塞，
        // 避免「胜出者占满容量 1 通道后其余任务在推送时死锁」（尤其 Swoole 协程）。
        $winner = $runtime->createChannel(count($tasks));

        foreach ($tasks as $task) {
            $runtime->async(function () use ($task, $winner): void {
                try {
                    $result = $task();
                } catch (\Throwable $e) {
                    $result = $e; // 以异常对象标记失败，交由调用方决定
                }
                $winner->push($result);
            });
        }

        $value = $winner->pop();

        if ($value instanceof \Throwable) {
            throw $value;
        }

        return $value;
    }

    /**
     * 选择：等待多个通道中第一个就绪者，返回其通道与数据
     *
     * 任一通道有数据可读时立即返回，格式为 `['channel' => ChannelInterface, 'value' => mixed]`。
     * 其余通道继续保留其数据（不会被消费）。
     *
     * 使用模型（与 WaitGroup / Once 一致）：
     * - Fiber / CLI 运行时：可在顶层直接使用
     * - Swoole / Swow 等事件循环运行时：请在 {@see Runtime::run()} 作用域内使用
     *
     * @param ChannelInterface ...$channels 待监听的通道（至少 1 个）
     * @return array{channel: ChannelInterface, value: mixed} 首个就绪通道及其数据
     * @throws \InvalidArgumentException 未提供任何通道时抛出
     */
    public static function select(ChannelInterface ...$channels): array
    {
        if ($channels === []) {
            throw new \InvalidArgumentException('select() 至少需要一个通道');
        }

        $runtime = self::adapter();
        // 容量设为通道数：每个监听协程只推送一次，容量充足则永不阻塞，避免死锁。
        $signal = $runtime->createChannel(count($channels));

        foreach ($channels as $channel) {
            $runtime->async(function () use ($channel, $signal): void {
                $value = $channel->pop();
                $signal->push(['channel' => $channel, 'value' => $value]);
            });
        }

        return $signal->pop();
    }

    /**
     * 创建子进程（仅在支持 PCNTL 的环境中可用）
     *
     * 子进程执行完回调后自行 exit（成功 0、异常 1），本方法不阻塞父进程。
     * **回收由调用方负责**：拿到 PID 后请 `pcntl_waitpid($pid, $status)`，
     * 否则子进程退出后会以僵尸驻留；{@see self::wait()} 只驱动协程，不回收进程。
     *
     * @param callable $callback 子进程中执行的函数
     * @return int 子进程 PID
     * @throws Exception\UnsupportedOperationException 环境不支持进程创建时抛出
     * @throws Exception\RuntimeException 进程创建失败时抛出
     */
    public static function fork(callable $callback): int
    {
        if (!RuntimeEnvironment::Process->isAvailable()) {
            throw new Exception\UnsupportedOperationException(
                RuntimeEnvironment::Process->unavailableMessage()
            );
        }

        $pid = pcntl_fork();

        if ($pid === -1 || $pid === false || $pid === null) {
            throw new Exception\RuntimeException('进程创建失败（当前环境不支持 fork）');
        }

        if ($pid === 0) {
            self::prepareChildAfterFork();

            $code = 0;

            try {
                $callback();
            } catch (\Throwable $e) {
                // 子进程与父进程共用错误流：先说出去再退，否则失败连痕迹都不留
                \fwrite(\STDERR, \sprintf(
                    'fork 子进程未捕获异常：%s: %s',
                    $e::class,
                    $e->getMessage()
                ) . \PHP_EOL);
                $code = 1;
            }

            exit($code);
        }

        return $pid;
    }

    /**
     * 子进程侧的继承状态清理
     *
     * 只做「本包自己那份进程级状态」：第三方用 register_shutdown_function 注册的
     * 收尾无法撤销，子进程 exit() 仍会执行它们——fork 前请自行确认这一点。
     */
    private static function prepareChildAfterFork(): void
    {
        $adapter = self::$adapter;

        if ($adapter instanceof AbstractRuntime) {
            $adapter->discardInheritedState();
        }

        // 父进程未跑完的协程、定时器与积压异常同样不该出现在子进程里
        FiberScheduler::resetInstance();
    }

    /**
     * 设置特定的运行时环境
     *
     * @param RuntimeEnvironment|string $environment 环境
     * @throws Exception\UnsupportedOperationException 环境非法或不可用时抛出
     */
    public static function setEnvironment(RuntimeEnvironment|string $environment): void
    {
        self::$adapter = RuntimeAdapterFactory::createForEnvironment($environment);
    }

    /**
     * 直接注入运行时适配器（便于测试与依赖注入）
     *
     * @param RuntimeInterface|null $adapter 适配器，null 表示恢复自动探测
     */
    public static function setAdapter(?RuntimeInterface $adapter): void
    {
        self::$adapter = $adapter;
    }

    /**
     * 获取当前运行时适配器
     *
     * @return RuntimeInterface 适配器实例
     */
    public static function adapter(): RuntimeInterface
    {
        return self::$adapter ??= RuntimeAdapterFactory::create();
    }

    /**
     * 重置运行时适配器（用于测试）
     *
     * 适配器与 Fiber 调度器是同一份进程级状态的两侧：只清一边，另一边攒下的
     * 协程/定时器/异常会被下一次 run() 当作本轮结果抛出来（跨请求串味）。
     */
    public static function reset(): void
    {
        self::$adapter = null;
        FiberScheduler::resetInstance();
    }
}
