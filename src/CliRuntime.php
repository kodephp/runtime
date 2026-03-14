<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * CLI 运行时适配器
 *
 * 用于传统 CLI 模式的同步执行适配器
 */
final class CliRuntime implements RuntimeInterface
{
    private static array $deferCallbacks = [];

    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'CLI';
    }

    /**
     * 同步执行函数（阻塞式）
     *
     * @param callable $callback 要执行的函数
     * @return mixed 函数返回值
     */
    public function async(callable $callback): mixed
    {
        try {
            return $callback();
        } finally {
            foreach (array_reverse(self::$deferCallbacks) as $deferCallback) {
                try {
                    $deferCallback();
                } catch (\Throwable) {
                }
            }
            self::$deferCallbacks = [];
        }
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int)($seconds * 1_000_000));
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
     * 注册当前上下文退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
        self::$deferCallbacks[] = $callback;
    }

    /**
     * 等待所有协程完成（CLI 模式下为空操作）
     */
    public function wait(): void
    {
    }
}
