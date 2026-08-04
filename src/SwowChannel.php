<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swow 通道实现
 *
 * 基于 Swow\Channel 实现，超时单位由秒换算为 Swow 使用的毫秒
 */
final class SwowChannel implements ChannelInterface
{
    private readonly \Swow\Channel $channel;

    private bool $timedOut = false;

    /**
     * 创建新的 Swow 通道
     *
     * @param int $capacity 通道容量
     */
    public function __construct(int $capacity = 0)
    {
        $this->channel = new \Swow\Channel(max(0, $capacity));
    }

    #[\Override]
    public function push(mixed $data, float $timeout = self::TIMEOUT_FOREVER): bool
    {
        $this->timedOut = false;

        try {
            $this->channel->push($data, $this->toMilliseconds($timeout));
            return true;
        } catch (\Throwable) {
            $this->timedOut = $this->channel->isAvailable();
            return false;
        }
    }

    #[\Override]
    public function pop(float $timeout = self::TIMEOUT_FOREVER): mixed
    {
        $this->timedOut = false;

        try {
            return $this->channel->pop($this->toMilliseconds($timeout));
        } catch (\Throwable) {
            $this->timedOut = $this->channel->isAvailable();
            return null;
        }
    }

    #[\Override]
    public function getCapacity(): int
    {
        return $this->channel->getCapacity();
    }

    #[\Override]
    public function getLength(): int
    {
        return $this->channel->getLength();
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
        return $this->timedOut;
    }

    #[\Override]
    public function close(): void
    {
        $this->channel->close();
    }

    #[\Override]
    public function isClosed(): bool
    {
        return !$this->channel->isAvailable();
    }

    /**
     * 将秒级超时换算为 Swow 使用的毫秒
     *
     * @param float $timeout 秒
     * @return int 毫秒，-1 表示永不超时
     */
    private function toMilliseconds(float $timeout): int
    {
        return $timeout < 0 ? -1 : (int) round($timeout * 1000);
    }
}
