<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swow 运行时适配器
 *
 * 基于 Swow 协程引擎实现的运行时
 */
final class SwowRuntime implements RuntimeInterface
{
    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'SWOW';
    }

    /**
     * 异步执行协程
     *
     * @param callable $callback 协程函数
     * @return \Swow\Coroutine 协程实例
     */
    public function async(callable $callback): \Swow\Coroutine
    {
        return \Swow\Coroutine::run($callback);
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    public function sleep(float $seconds): void
    {
        \Swow\Coroutine::sleep((int)($seconds * 1000));
    }

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwowChannel($capacity);
    }

    /**
     * 注册当前协程退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
        $coroutine = \Swow\Coroutine::getCurrent();
        if ($coroutine !== null) {
            $coroutine->addOnCloseCallback($callback);
        }
    }

    /**
     * 等待所有协程完成
     */
    public function wait(): void
    {
        while (\Swow\Coroutine::count() > 1) {
            \Swow\Coroutine::yield();
        }
    }
}
