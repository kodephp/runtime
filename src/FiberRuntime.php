<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Fiber 运行时适配器
 *
 * 基于 PHP 8.1+ 原生 Fiber 实现的协程运行时
 */
final class FiberRuntime implements RuntimeInterface
{
    private static array $fibers = [];
    private static array $deferCallbacks = [];

    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'FIBER';
    }

    /**
     * 异步执行协程
     *
     * @param callable $callback 协程函数
     * @return \Fiber Fiber 实例
     */
    public function async(callable $callback): \Fiber
    {
        $fiber = new \Fiber(function () use ($callback): void {
            try {
                $callback();
            } finally {
                foreach (array_reverse(self::$deferCallbacks) as $deferCallback) {
                    try {
                        $deferCallback();
                    } catch (\Throwable) {
                    }
                }
                self::$deferCallbacks = [];
            }
        });

        self::$fibers[] = $fiber;
        $fiber->start();
        return $fiber;
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $start = microtime(true);
        while (microtime(true) - $start < $seconds) {
            \Fiber::suspend();
        }
    }

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new CliChannel($capacity);
    }

    /**
     * 注册当前协程退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
        self::$deferCallbacks[] = $callback;
    }

    /**
     * 等待所有协程完成
     */
    public function wait(): void
    {
        foreach (self::$fibers as $fiber) {
            if (!$fiber->isTerminated()) {
                try {
                    $fiber->resume();
                } catch (\FiberError) {
                }
            }
        }
    }
}
