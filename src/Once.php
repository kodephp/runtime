<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 单飞（Once）
 *
 * 受 Go `sync.Once` 与单飞（singleflight）模式启发的并发原语：确保被包裹的回调
 * **仅执行一次**，并发调用者共享同一次执行的结果（或异常）。
 *
 * 适用于：
 * - 缓存一次性昂贵的初始化 / 配置加载（多协程并发触发只算一次）
 * - 防止重复副作用（如重复注册、重复连接）
 *
 * 实现要点：
 * - 使用容量为 1 的通道充当「二元信号量」，串行化「是否已完成」的判定与执行，
 *   保证在 Fiber / Swoole / Swow 等协作式或抢占式并发下都只执行一次。
 * - 首个调用者负责执行回调；其余并发调用者获取锁后发现已完成，直接返回缓存结果，
 *   不会重复执行（即「单飞」语义）。
 * - 无论成功或失败，回调都只执行一次（`done` 在异常时同样置位，符合 Once 语义）。
 *
 * 使用模型（与 WaitGroup / channel 一致）：
 * - Fiber / CLI 运行时：可在顶层直接使用
 * - Swoole / Swow 等事件循环运行时：请在 {@see Runtime::run()} 作用域内使用
 *
 * @example
 * ```php
 * $once = Runtime::once();
 * Runtime::run(function () use ($once) {
 *     $value = $once->do(fn () => expensiveLookup());
 * });
 * ```
 */
final class Once
{
    private RuntimeInterface $runtime;

    /**
     * 二元信号量（容量 1 通道），用于串行化临界区
     */
    private ChannelInterface $mutex;

    /**
     * 是否已执行过（无论成功或失败）
     */
    private bool $done = false;

    /**
     * 回调返回值
     */
    private mixed $result = null;

    /**
     * 回调抛出的异常（若有）
     */
    private ?\Throwable $error = null;

    /**
     * @param RuntimeInterface|null $runtime 运行时适配器，null 表示使用当前门面运行时
     */
    public function __construct(?RuntimeInterface $runtime = null)
    {
        $this->runtime = $runtime ?? Runtime::adapter();
        $this->mutex = $this->runtime->createChannel(1);
        $this->mutex->push(true); // 初始令牌可用
    }

    /**
     * 执行被包裹的回调（仅一次），返回其结果
     *
     * 并发调用者共享同一次执行的结果；若首次执行抛异常，则后续调用同样抛出该异常，
     * 且不会重新执行回调。
     *
     * @param callable $fn 仅执行一次的回调
     * @return mixed 回调返回值
     * @throws \Throwable 回调首次执行抛出的异常
     */
    public function do(callable $fn): mixed
    {
        // 获取锁（临界区开始）
        $this->mutex->pop();

        try {
            if (!$this->done) {
                try {
                    $this->result = $fn();
                } catch (\Throwable $e) {
                    $this->error = $e;
                }
                // 无论成功或失败都标记完成，保证仅执行一次
                $this->done = true;
            }

            if ($this->error !== null) {
                throw $this->error;
            }

            return $this->result;
        } finally {
            // 释放锁（临界区结束）
            $this->mutex->push(true);
        }
    }

    /**
     * 回调是否已经执行过（无论成功或失败）
     */
    public function hasRun(): bool
    {
        return $this->done;
    }

    /**
     * 重置状态，允许下次调用重新执行回调
     *
     * 适用于长生命周期进程中需要周期性重新初始化的场景。
     */
    public function reset(): void
    {
        $this->done = false;
        $this->result = null;
        $this->error = null;
    }
}
