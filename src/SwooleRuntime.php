<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole 运行时适配器
 *
 * 基于 Swoole 协程引擎实现的运行时
 */
final class SwooleRuntime implements RuntimeInterface
{
    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'SWOOLE';
    }

    /**
     * 异步执行协程
     *
     * @param callable $callback 协程函数
     * @return int 协程 ID
     */
    public function async(callable $callback): int
    {
        return \Swoole\Coroutine::create($callback);
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    public function sleep(float $seconds): void
    {
        \Swoole\Coroutine::sleep($seconds);
    }

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwooleChannel($capacity);
    }

    /**
     * 注册当前协程退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
        \Swoole\Coroutine::defer($callback);
    }

    /**
     * 等待所有协程完成（Swoole 模式下自动处理）
     */
    public function wait(): void
    {
    }
}
