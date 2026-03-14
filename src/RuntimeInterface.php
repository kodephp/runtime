<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时接口
 *
 * 定义运行时适配器的统一接口，支持不同运行环境的抽象
 */
interface RuntimeInterface
{
    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称（SWOOLE|SWOW|FIBER|PROCESS|THREAD|CLI）
     */
    public function getName(): string;

    /**
     * 异步执行一个函数
     *
     * @param callable $callback 要执行的函数
     * @return mixed 协程句柄或 ID
     */
    public function async(callable $callback): mixed;

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数（支持小数）
     */
    public function sleep(float $seconds): void;

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量，0 表示无限制
     * @return ChannelInterface 通道实例
     */
    public function createChannel(int $capacity = 0): ChannelInterface;

    /**
     * 注册当前协程退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void;

    /**
     * 等待所有协程完成
     */
    public function wait(): void;
}
