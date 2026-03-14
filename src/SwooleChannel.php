<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole 通道实现
 *
 * 基于 Swoole\Coroutine\Channel 实现的通道
 */
final class SwooleChannel implements ChannelInterface
{
    private readonly \Swoole\Coroutine\Channel $channel;

    /**
     * 创建新的 Swoole 通道
     *
     * @param int $capacity 通道容量
     */
    public function __construct(int $capacity = 0)
    {
        $this->channel = new \Swoole\Coroutine\Channel($capacity);
    }

    /**
     * 向通道推送数据
     *
     * @param mixed $data 要推送的数据
     * @return bool 推送成功返回 true，失败返回 false
     */
    public function push(mixed $data): bool
    {
        return $this->channel->push($data);
    }

    /**
     * 从通道弹出数据
     *
     * @return mixed 通道中的数据
     */
    public function pop(): mixed
    {
        return $this->channel->pop();
    }

    /**
     * 获取通道容量
     *
     * @return int 通道容量
     */
    public function getCapacity(): int
    {
        return $this->channel->capacity;
    }

    /**
     * 获取通道当前长度
     *
     * @return int 当前长度
     */
    public function getLength(): int
    {
        return $this->channel->length();
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
        try {
            return $this->channel->errCode === SWOOLE_CHANNEL_CLOSED;
        } catch (\Error) {
            return true;
        }
    }
}
