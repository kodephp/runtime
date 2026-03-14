<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swow 通道实现
 *
 * 基于 Swow\Channel 实现的通道
 */
final class SwowChannel implements ChannelInterface
{
    private readonly \Swow\Channel $channel;

    /**
     * 创建新的 Swow 通道
     *
     * @param int $capacity 通道容量
     */
    public function __construct(int $capacity = 0)
    {
        $this->channel = new \Swow\Channel($capacity);
    }

    /**
     * 向通道推送数据
     *
     * @param mixed $data 要推送的数据
     * @return bool 推送成功返回 true，失败返回 false
     */
    public function push(mixed $data): bool
    {
        try {
            $this->channel->push($data);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 从通道弹出数据
     *
     * @return mixed 通道中的数据
     */
    public function pop(): mixed
    {
        try {
            return $this->channel->pop();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 获取通道容量
     *
     * @return int 通道容量
     */
    public function getCapacity(): int
    {
        return $this->channel->getCapacity();
    }

    /**
     * 获取通道当前长度
     *
     * @return int 当前长度
     */
    public function getLength(): int
    {
        return $this->channel->getLength();
    }

    /**
     * 关闭通道
     */
    public function close(): void
    {
        $this->channel->close();
    }

    /**
     * 检查通道是否已关闭
     *
     * @return bool 已关闭返回 true，否则返回 false
     */
    public function isClosed(): bool
    {
        return !$this->channel->isAvailable();
    }
}
