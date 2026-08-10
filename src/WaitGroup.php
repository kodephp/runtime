<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 等待组（WaitGroup）
 *
 * 受 Go `sync.WaitGroup` 启发的并发原语：以「计数器」方式派生任意数量的异步任务，
 * 并在所有任务完成后统一阻塞等待，自动收集各任务的返回值与异常。
 *
 * 与 {@see RuntimeInterface::parallel()} 的区别：
 * - parallel() 需要「一次性」传入任务集合；WaitGroup 可在任意位置动态派生任务
 * - WaitGroup 区分「正常结果」与「任务异常」，不会因单个任务失败而中断其余任务
 *
 * 使用模型（与 channel / parallel 一致）：
 * - Fiber / CLI 运行时：可在顶层直接使用
 * - Swoole / Swow 等事件循环运行时：请在 {@see Runtime::run()} 作用域内使用
 *
 * @example
 * ```php
 * Runtime::run(function () {
 *     $wg = Runtime::waitGroup();
 *     $wg->run(fn () => doWork(1));
 *     $wg->run(fn () => doWork(2));
 *     $wg->wait();           // 阻塞直到两个任务完成
 *     $results = $wg->results();
 * });
 * ```
 */
final class WaitGroup
{
    private RuntimeInterface $runtime;

    private ChannelInterface $channel;

    /**
     * 已派发的任务总数（未完成计数）
     */
    private int $total = 0;

    /**
     * 任务返回值，键为派发序号
     *
     * @var array<int, mixed>
     */
    private array $results = [];

    /**
     * 任务异常，键为派发序号
     *
     * @var array<int, \Throwable>
     */
    private array $errors = [];

    /**
     * 自增派发序号
     */
    private int $seq = 0;

    /**
     * @param RuntimeInterface|null $runtime 运行时适配器，null 表示使用当前门面运行时
     */
    public function __construct(?RuntimeInterface $runtime = null)
    {
        $this->runtime = $runtime ?? Runtime::adapter();
        $this->channel = $this->runtime->createChannel();
    }

    /**
     * 增加待完成任务计数
     *
     * @param int $delta 增量（必须 ≥ 0）
     * @return static 便于链式调用
     * @throws \InvalidArgumentException delta 为负数时抛出
     */
    public function add(int $delta = 1): static
    {
        if ($delta < 0) {
            throw new \InvalidArgumentException('delta 不能为负数');
        }

        $this->total += $delta;

        return $this;
    }

    /**
     * 派生一个异步任务
     *
     * 任务完成后其结果写入 {@see results()}，异常写入 {@see errors()}，
     * 无论成功失败都会递减计数器并通知等待者（等价于此对象内部调用一次 {@see done()}）。
     *
     * @param callable $task 任务函数
     * @return static 便于链式调用
     */
    public function run(callable $task): static
    {
        $id = $this->seq++;
        $this->total++;

        $this->runtime->async(function () use ($task, $id): void {
            try {
                $this->results[$id] = $task();
            } catch (\Throwable $e) {
                $this->errors[$id] = $e;
            } finally {
                // 无论成功失败都递减计数，避免等待者永久阻塞
                $this->done();
            }
        });

        return $this;
    }

    /**
     * 通知一个待完成任务已完成（递减计数器）
     *
     * 与 {@see add()} 配合使用：先 {@see add()} 预登记若干外部派发的任务，
     * 待各任务结束时调用本方法归还计数，避免 {@see wait()} 因无人通知而永久阻塞。
     * 传入的回调返回值/异常不会被收集（请改用 {@see run()} 以获得结果收集）。
     *
     * @return static 便于链式调用
     */
    public function done(): static
    {
        $this->channel->push(1);

        return $this;
    }

    /**
     * 阻塞等待所有已派发任务完成
     *
     * 内部通过完成通道按派发数量回收通知，对协程/事件循环运行时真正让出执行权。
     */
    public function wait(): void
    {
        for ($i = 0; $i < $this->total; $i++) {
            $this->channel->pop();
        }

        $this->total = 0;
    }

    /**
     * 获取所有任务的返回值（按派发顺序排序）
     *
     * @return array<int, mixed> 派发序号 => 返回值
     */
    public function results(): array
    {
        ksort($this->results);

        return $this->results;
    }

    /**
     * 获取执行失败的任务异常（按派发顺序排序）
     *
     * @return array<int, \Throwable> 派发序号 => 异常
     */
    public function errors(): array
    {
        ksort($this->errors);

        return $this->errors;
    }

    /**
     * 是否存在执行失败的任务
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * 尚未完成的任务数量
     */
    public function count(): int
    {
        return $this->total;
    }
}
