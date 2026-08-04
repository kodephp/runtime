<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时接口
 *
 * 定义运行时适配器的统一接口，抽象不同运行环境的并发模型差异
 */
interface RuntimeInterface
{
    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称（SWOOLE|SWOW|FIBER|PROCESS|THREAD|CONSOLE|CLI）
     */
    public function getName(): string;

    /**
     * 获取运行时环境枚举
     *
     * @return RuntimeEnvironment 环境枚举
     */
    public function environment(): RuntimeEnvironment;

    /**
     * 当前运行时是否具备真正的并发能力
     *
     * @return bool 具备返回 true
     */
    public function supportsConcurrency(): bool;

    /**
     * 异步执行一个函数
     *
     * @param callable $callback 要执行的函数
     * @return mixed 协程句柄、进程 ID 或同步执行结果
     */
    public function async(callable $callback): mixed;

    /**
     * 以入口函数的方式运行并等待全部异步任务结束
     *
     * @param callable $main 入口函数
     * @return mixed 入口函数返回值
     */
    public function run(callable $main): mixed;

    /**
     * 并发执行多个任务并按原始键收集结果
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 与任务键一一对应的结果
     */
    public function parallel(iterable $tasks): array;

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
     * 注册当前作用域退出时执行的回调（后进先出）
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void;

    /**
     * 等待所有异步任务完成
     */
    public function wait(): void;
}
