<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * CLI 通道实现
 *
 * 基于 SplQueue 实现的同步通道，用于 CLI 模式和 Fiber 模式
 */
final class CliChannel implements ChannelInterface
{
    private readonly \SplQueue $queue;
    private readonly int $capacity;
    private bool $closed = false;

    /**
     * 创建新的 CLI 通道
     *
     * @param int $capacity 通道容量，0 表示无限制
     */
    public function __construct(int $capacity = 0)
    {
        $this->queue = new \SplQueue();
        $this->capacity = $capacity > 0 ? $capacity : 0;
    }

    /**
     * 向通道推送数据
     *
     * @param mixed $data 要推送的数据
     * @return bool 推送成功返回 true，失败返回 false
     */
    public function push(mixed $data): bool
    {
        if ($this->closed) {
            return false;
        }

        if ($this->capacity > 0 && $this->queue->count() >= $this->capacity) {
            return false;
        }

        $this->queue->enqueue($data);
        return true;
    }

    /**
     * 从通道弹出数据
     *
     * @return mixed 通道中的数据，如果通道为空或已关闭则返回 null
     */
    public function pop(): mixed
    {
        if ($this->queue->isEmpty() || $this->closed) {
            return null;
        }

        return $this->queue->dequeue();
    }

    /**
     * 获取通道容量
     *
     * @return int 通道容量
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * 获取通道当前长度
     *
     * @return int 当前长度
     */
    public function getLength(): int
    {
        return $this->queue->count();
    }

    /**
     * 关闭通道
     */
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * 检查通道是否已关闭
     *
     * @return bool 已关闭返回 true，否则返回 false
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
}
