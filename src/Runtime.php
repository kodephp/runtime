<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时门面类
 *
 * 提供统一的静态接口访问不同运行时环境
 * 支持 Swoole、Swow、Fiber、Process、Thread 和 CLI 模式
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
        return self::getAdapter()->getName();
    }

    /**
     * 异步执行函数
     *
     * @param callable $callback 要执行的函数
     * @return mixed 协程句柄或 ID
     */
    public static function async(callable $callback): mixed
    {
        return self::getAdapter()->async($callback);
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    public static function sleep(float $seconds): void
    {
        self::getAdapter()->sleep($seconds);
    }

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    public static function createChannel(int $capacity = 0): ChannelInterface
    {
        return self::getAdapter()->createChannel($capacity);
    }

    /**
     * 注册当前上下文退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public static function defer(callable $callback): void
    {
        self::getAdapter()->defer($callback);
    }

    /**
     * 等待所有异步操作完成
     */
    public static function wait(): void
    {
        self::getAdapter()->wait();
    }

    /**
     * 创建子进程（仅在支持进程的环境中可用）
     *
     * @param callable $callback 子进程中执行的函数
     * @return int 子进程 ID
     * @throws Exception\UnsupportedOperationException 如果环境不支持进程创建
     */
    public static function fork(callable $callback): int
    {
        if (!function_exists('pcntl_fork')) {
            throw new Exception\UnsupportedOperationException('当前环境不支持进程创建');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new Exception\RuntimeException('进程创建失败');
        } elseif ($pid === 0) {
            try {
                $callback();
            } finally {
                exit(0);
            }
        } else {
            return $pid;
        }
    }

    /**
     * 设置特定的运行时环境
     *
     * @param string $environment 环境名称
     */
    public static function setEnvironment(string $environment): void
    {
        self::$adapter = RuntimeAdapterFactory::createForEnvironment($environment);
    }

    /**
     * 获取当前运行时适配器
     *
     * @return RuntimeInterface 适配器实例
     */
    private static function getAdapter(): RuntimeInterface
    {
        if (self::$adapter === null) {
            self::$adapter = RuntimeAdapterFactory::create();
        }
        return self::$adapter;
    }

    /**
     * 重置运行时适配器（用于测试）
     */
    public static function reset(): void
    {
        self::$adapter = null;
    }
}
