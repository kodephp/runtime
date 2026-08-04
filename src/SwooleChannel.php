<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole 通道实现
 *
 * 基于 Swoole\Coroutine\Channel 实现，支持协程安全的超时读写
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
        $this->channel = new \Swoole\Coroutine\Channel(max(0, $capacity));
    }

    #[\Override]
    public function push(mixed $data, float $timeout = self::TIMEOUT_FOREVER): bool
    {
        return $this->channel->push($data, $timeout);
    }

    #[\Override]
    public function pop(float $timeout = self::TIMEOUT_FOREVER): mixed
    {
        $data = $this->channel->pop($timeout);

        return $data === false && $this->channel->errCode !== SWOOLE_CHANNEL_OK ? null : $data;
    }

    #[\Override]
    public function getCapacity(): int
    {
        return $this->channel->capacity;
    }

    #[\Override]
    public function getLength(): int
    {
        return $this->channel->length();
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->channel->isEmpty();
    }

    #[\Override]
    public function isFull(): bool
    {
        return $this->channel->isFull();
    }

    #[\Override]
    public function isTimeout(): bool
    {
        return $this->channel->errCode === SWOOLE_CHANNEL_TIMEOUT;
    }

    #[\Override]
    public function close(): void
    {
        $this->channel->close();
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->channel->errCode === SWOOLE_CHANNEL_CLOSED;
    }
}
