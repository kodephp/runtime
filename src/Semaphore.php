<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 信号量（Semaphore）
 *
 * 受 Go `golang.org/x/sync/semaphore` 与操作系统信号量启发的并发限流原语：
 * 持有一组「许可（permit）」，任意时刻最多允许 {@see capacity()} 个任务进入临界区，
 * 其余调用者阻塞等待直到有许可被释放。
 *
 * 适用场景：
 * - 限制对下游服务 / 数据库的连接并发数（防止被打爆）
 * - 限制 CPU / IO 密集任务的并行度
 * - 与 {@see WaitGroup}（等待全部完成）、{@see Once}（只执行一次）、
 *   {@see Runtime::race()}（竞速）形成并发原语家族
 *
 * 实现要点：
 * - 内部用容量为许可数的通道充当「许可池」，{@see acquire()} 取走一个令牌，
 *   {@see release()} 归还一个令牌；令牌耗尽时 acquire 阻塞直至被释放，
 *   在 Fiber / Swoole / Swow 等协作式或抢占式并发下都能正确让出执行权。
 * - {@see run()} 等价于「acquire → 执行 → release（无论成功失败都在 finally 中释放）」，
 *   异常会向上传播且不会泄漏许可。
 *
 * 使用模型（与 WaitGroup / channel 一致）：
 * - Fiber / CLI 运行时：可在顶层直接使用
 * - Swoole / Swow 等事件循环运行时：请在 {@see Runtime::run()} 作用域内使用
 *
 * @example
 * ```php
 * Runtime::run(function () {
 *     $sem = Runtime::semaphore(2); // 最多 2 个并发
 *     $results = Runtime::parallel(array_map(
 *         static fn (int $i) => static fn () => $sem->run(static fn () => doWork($i)),
 *         range(0, 9)
 *     ));
 * });
 * ```
 */
final class Semaphore
{
    private RuntimeInterface $runtime;

    /**
     * 许可池（容量 = 总许可数，初始装满令牌）
     */
    private ChannelInterface $permits;

    /**
     * 总许可数
     */
    private int $capacity;

    /**
     * @param int $permits 许可数量（必须 ≥ 1）
     * @param RuntimeInterface|null $runtime 运行时适配器，null 表示使用当前门面运行时
     * @throws \InvalidArgumentException 许可数量小于 1 时抛出
     */
    public function __construct(int $permits, ?RuntimeInterface $runtime = null)
    {
        if ($permits < 1) {
            throw new \InvalidArgumentException('信号量许可数量必须 ≥ 1');
        }

        $this->runtime = $runtime ?? Runtime::adapter();
        $this->capacity = $permits;
        $this->permits = $this->runtime->createChannel($permits);

        for ($i = 0; $i < $permits; $i++) {
            $this->permits->push(true);
        }
    }

    /**
     * 获取一个许可（若无可用许可则阻塞等待）
     */
    public function acquire(): void
    {
        $this->permits->pop();
    }

    /**
     * 释放一个许可，使一个等待者得以继续
     *
     * 注意：应仅在 {@see acquire()} 成功后调用，且释放次数不得超过 {@see capacity()}，
     * 否则会凭空增加可用许可。
     */
    public function release(): void
    {
        $this->permits->push(true);
    }

    /**
     * 在临界区内执行回调（自动获取与释放许可）
     *
     * 无论回调成功或抛异常，许可都会在 finally 中被释放，不会泄漏。
     *
     * @param callable $task 临界区任务
     * @return mixed 回调返回值
     * @throws \Throwable 回调抛出的异常（许可已释放）
     */
    public function run(callable $task): mixed
    {
        $this->acquire();

        try {
            return $task();
        } finally {
            $this->release();
        }
    }

    /**
     * 当前可用许可数量
     */
    public function available(): int
    {
        return $this->permits->getLength();
    }

    /**
     * 信号量总许可数量
     */
    public function capacity(): int
    {
        return $this->capacity;
    }
}
