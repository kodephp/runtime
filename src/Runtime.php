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
     * 创建子进程（仅在支持 PCNTL 的环境中可用）
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
            $code = 0;
            try {
                $callback();
            } catch (\Throwable) {
                $code = 1;
            }
            exit($code);
        }

        return $pid;
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
     */
    public static function reset(): void
    {
        self::$adapter = null;
    }
}
