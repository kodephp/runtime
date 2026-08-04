<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 内存队列通道
 *
 * 基于 SplQueue 实现，供 CLI、Fiber、Process、Thread、Console 环境共用。
 * 在 Fiber 调度器中运行时，push/pop 会让出执行权实现真正的阻塞等待；
 * 在无调度器的同步环境中退化为非阻塞操作，避免自我死锁
 */
final class CliChannel implements ChannelInterface
{
    /**
     * 同步环境下轮询的间隔（微秒）
     */
    private const int POLL_INTERVAL_US = 200;

    /**
     * @var \SplQueue<mixed>
     */
    private readonly \SplQueue $queue;

    private readonly int $capacity;

    private readonly FiberScheduler $scheduler;

    private bool $closed = false;

    private bool $timedOut = false;

    /**
     * 创建新的内存通道
     *
     * @param int $capacity 通道容量，0 表示无限制
     * @param FiberScheduler|null $scheduler 阻塞等待时驱动的调度器，默认使用全局实例
     */
    public function __construct(int $capacity = 0, ?FiberScheduler $scheduler = null)
    {
        $this->queue = new \SplQueue();
        $this->capacity = max(0, $capacity);
        $this->scheduler = $scheduler ?? FiberScheduler::instance();
    }

    #[\Override]
    public function push(mixed $data, float $timeout = self::TIMEOUT_FOREVER): bool
    {
        $this->timedOut = false;

        if ($this->closed) {
            return false;
        }

        if ($this->isFull() && !$this->await(fn (): bool => !$this->isFull(), $timeout)) {
            return false;
        }

        if ($this->closed) {
            return false;
        }

        $this->queue->enqueue($data);

        return true;
    }

    #[\Override]
    public function pop(float $timeout = self::TIMEOUT_FOREVER): mixed
    {
        $this->timedOut = false;

        if ($this->closed) {
            return null;
        }

        if ($this->queue->isEmpty() && !$this->await(fn (): bool => !$this->queue->isEmpty(), $timeout)) {
            return null;
        }

        if ($this->queue->isEmpty()) {
            return null;
        }

        return $this->queue->dequeue();
    }

    #[\Override]
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    #[\Override]
    public function getLength(): int
    {
        return $this->queue->count();
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->queue->isEmpty();
    }

    #[\Override]
    public function isFull(): bool
    {
        return $this->capacity > 0 && $this->queue->count() >= $this->capacity;
    }

    #[\Override]
    public function isTimeout(): bool
    {
        return $this->timedOut;
    }

    #[\Override]
    public function close(): void
    {
        $this->closed = true;
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * 等待条件成立
     *
     * @param callable(): bool $condition 条件判定
     * @param float $timeout 等待秒数，-1 表示一直等待，0 表示不等待
     * @return bool 条件成立返回 true，超时或无法等待返回 false
     */
    private function await(callable $condition, float $timeout): bool
    {
        if ($timeout === self::TIMEOUT_NONE) {
            $this->timedOut = true;
            return false;
        }

        $scheduler = $this->scheduler;
        $inFiber = $scheduler->inFiber();

        // 同步环境且无其他协程可推进条件时，继续等待只会死锁
        if (!$inFiber && !$scheduler->hasPendingWork()) {
            $this->timedOut = true;
            return false;
        }

        $deadline = $timeout > 0 ? microtime(true) + $timeout : null;

        while (!$condition()) {
            if ($this->closed) {
                return false;
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                $this->timedOut = true;
                return false;
            }

            if ($inFiber) {
                if (!$scheduler->hasOtherWork()) {
                    $this->timedOut = true;
                    return false;
                }
                $scheduler->yield();
                continue;
            }

            if (!$scheduler->tick()) {
                $this->timedOut = true;
                return false;
            }
        }

        return true;
    }
}
