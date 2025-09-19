<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole channel implementation.
 */
class SwooleChannel implements ChannelInterface
{
    /**
     * @var \Swoole\Coroutine\Channel
     */
    private \Swoole\Coroutine\Channel $channel;

    /**
     * Create a new Swoole channel
     *
     * @param int $capacity Channel capacity
     */
    public function __construct(int $capacity = 0)
    {
        $this->channel = new \Swoole\Coroutine\Channel($capacity);
    }

    /**
     * Push data to the channel
     *
     * @param mixed $data Data to push
     * @return bool True if data was pushed successfully, false otherwise
     */
    public function push(mixed $data): bool
    {
        return $this->channel->push($data);
    }

    /**
     * Pop data from the channel
     *
     * @return mixed Data from the channel
     */
    public function pop(): mixed
    {
        return $this->channel->pop();
    }

    /**
     * Get the current capacity of the channel
     *
     * @return int Current capacity
     */
    public function getCapacity(): int
    {
        return $this->channel->capacity;
    }

    /**
     * Get the current length of the channel (number of items in the channel)
     *
     * @return int Current length
     */
    public function getLength(): int
    {
        return $this->channel->length();
    }

    /**
     * Close the channel
     *
     * @return void
     */
    public function close(): void
    {
        $this->channel->close();
    }

    /**
     * Check if the channel is closed
     *
     * @return bool True if channel is closed, false otherwise
     */
    public function isClosed(): bool
    {
        // Swoole channels don't have an explicit isClosed method
        // We can check if the channel is null or has been closed
        try {
            return $this->channel->errCode === SWOOLE_CHANNEL_CLOSED;
        } catch (\Error $e) {
            return true;
        }
    }
}
