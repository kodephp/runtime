<?php

declare(strict_types=1);

namespace Kode\Runtime;

if (!function_exists(__NAMESPACE__ . '\\go')) {
    /**
     * 启动一个异步任务（协程 / 进程 / 线程，取决于当前运行时）
     *
     * @param callable $callback 任务函数
     * @return mixed 协程句柄、进程 ID 或同步执行结果
     */
    function go(callable $callback): mixed
    {
        return Runtime::async($callback);
    }

    /**
     * 并发执行多个任务并按原始键收集结果
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 执行结果
     */
    function parallel(iterable $tasks): array
    {
        return Runtime::parallel($tasks);
    }

    /**
     * 以入口函数方式运行并等待全部异步任务结束
     *
     * @param callable $main 入口函数
     * @return mixed 入口函数返回值
     */
    function run(callable $main): mixed
    {
        return Runtime::run($main);
    }

    /**
     * 创建通道
     *
     * @param int $capacity 通道容量，0 表示无限制
     * @return ChannelInterface 通道实例
     */
    function channel(int $capacity = 0): ChannelInterface
    {
        return Runtime::createChannel($capacity);
    }

    /**
     * 注册当前作用域退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    function defer(callable $callback): void
    {
        Runtime::defer($callback);
    }

    /**
     * 休眠指定秒数（协程环境下会让出执行权）
     *
     * @param float $seconds 休眠秒数
     */
    function delay(float $seconds): void
    {
        Runtime::sleep($seconds);
    }

    /**
     * 等待所有异步任务完成
     */
    function wait(): void
    {
        Runtime::wait();
    }
}
