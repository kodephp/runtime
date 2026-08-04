<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swow 运行时适配器
 *
 * 基于 Swow 协程引擎实现的运行时。
 * Swow 已在扩展层 hook 了 sleep / usleep 等阻塞函数，因此可直接使用标准函数休眠
 */
final class SwowRuntime extends AbstractRuntime
{
    /**
     * 已创建的协程
     *
     * @var list<\Swow\Coroutine>
     */
    private array $coroutines = [];

    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Swow;
    }

    /**
     * 启动协程
     *
     * @param callable $callback 协程函数
     * @return \Swow\Coroutine 协程实例
     */
    #[\Override]
    public function async(callable $callback): \Swow\Coroutine
    {
        $coroutine = \Swow\Coroutine::run($callback);
        $this->coroutines[] = $coroutine;

        return $coroutine;
    }

    /**
     * 休眠指定秒数（Swow 已 hook usleep，协程内不会阻塞线程）
     *
     * @param float $seconds 休眠秒数
     */
    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }

    #[\Override]
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwowChannel($capacity);
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
        $results = [];
        $spawned = [];

        foreach ($tasks as $key => $task) {
            $results[$key] = null;
            $spawned[] = \Swow\Coroutine::run(static function () use ($task, $key, &$results): void {
                $results[$key] = $task();
            });
        }

        $this->join($spawned);

        return $results;
    }

    /**
     * 注册协程关闭回调
     *
     * @param callable $callback 清理函数
     */
    #[\Override]
    public function defer(callable $callback): void
    {
        $coroutine = \Swow\Coroutine::getCurrent();

        if ($coroutine !== \Swow\Coroutine::getMain()) {
            $coroutine->addOnCloseCallback($callback);
            return;
        }

        parent::defer($callback);
    }

    /**
     * 等待所有由本适配器创建的协程结束
     */
    #[\Override]
    public function wait(): void
    {
        $coroutines = $this->coroutines;
        $this->coroutines = [];

        $this->join($coroutines);

        parent::wait();
    }

    /**
     * 等待指定协程全部结束
     *
     * @param list<\Swow\Coroutine> $coroutines 协程列表
     */
    private function join(array $coroutines): void
    {
        while (true) {
            $running = false;

            foreach ($coroutines as $coroutine) {
                if ($coroutine->isAvailable()) {
                    $running = true;
                    break;
                }
            }

            if (!$running) {
                return;
            }

            \Swow\Coroutine::yield();
        }
    }
}
