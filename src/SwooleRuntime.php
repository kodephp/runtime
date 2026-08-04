<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole 运行时适配器
 *
 * 基于 Swoole 协程引擎实现的运行时
 */
final class SwooleRuntime extends AbstractRuntime
{
    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Swoole;
    }

    /**
     * 启动协程
     *
     * @param callable $callback 协程函数
     * @return int 协程 ID
     */
    #[\Override]
    public function async(callable $callback): int
    {
        return \Swoole\Coroutine::create($callback);
    }

    /**
     * 在协程容器中执行入口函数并等待其完成
     *
     * @param callable $main 入口函数
     * @return mixed 入口函数返回值
     */
    #[\Override]
    public function run(callable $main): mixed
    {
        if (\Swoole\Coroutine::getCid() > 0) {
            return $main();
        }

        $result = null;
        \Swoole\Coroutine\run(static function () use ($main, &$result): void {
            $result = $main();
        });

        return $result;
    }

    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if (\Swoole\Coroutine::getCid() > 0) {
            \Swoole\Coroutine::sleep($seconds);
            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }

    #[\Override]
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwooleChannel($capacity);
    }

    /**
     * 并发执行任务并收集结果
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 与任务键一一对应的结果
     */
    #[\Override]
    public function parallel(iterable $tasks): array
    {
        $callables = is_array($tasks) ? $tasks : iterator_to_array($tasks);

        if ($callables === []) {
            return [];
        }

        if (function_exists('\Swoole\Coroutine\batch')) {
            return $this->run(static fn (): array => \Swoole\Coroutine\batch($callables));
        }

        return $this->run(function () use ($callables): array {
            $results = [];
            $channel = new \Swoole\Coroutine\Channel(count($callables));

            foreach ($callables as $key => $task) {
                \Swoole\Coroutine::create(static function () use ($task, $key, $channel): void {
                    $channel->push([$key, $task()]);
                });
            }

            for ($i = 0, $total = count($callables); $i < $total; $i++) {
                [$key, $value] = $channel->pop();
                $results[$key] = $value;
            }

            return $results;
        });
    }

    /**
     * 注册协程退出回调
     *
     * @param callable $callback 清理函数
     */
    #[\Override]
    public function defer(callable $callback): void
    {
        if (\Swoole\Coroutine::getCid() > 0) {
            \Swoole\Coroutine::defer($callback);
            return;
        }

        parent::defer($callback);
    }
}
