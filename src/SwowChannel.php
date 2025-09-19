<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swow channel implementation.
 */
class SwowChannel implements ChannelInterface
{
    /**
     * @var \Swow\Channel
     */
    private \Swow\Channel $channel;

    /**
     * Create a new Swow channel
     *
     * @param int $capacity Channel capacity
     */
    public function __construct(int $capacity = 0)
    {
        $this->channel = new \Swow\Channel($capacity);
    }

    /**
     * Push data to the channel
     *
     * @param mixed $data Data to push
     * @return bool True if data was pushed successfully, false otherwise
     */
    public function push(mixed $data): bool
    {
        try {
            $this->channel->push($data);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Pop data from the channel
     *
     * @return mixed Data from the channel
     */
    public function pop(): mixed
    {
        try {
            return $this->channel->pop();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the current capacity of the channel
     *
     * @return int Current capacity
     */
    public function getCapacity(): int
    {
        return $this->channel->getCapacity();
    }

    /**
     * Get the current length of the channel (number of items in the channel)
     *
     * @return int Current length
     */
    public function getLength(): int
    {
        return $this->channel->getLength();
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
        return $this->channel->isAvailable() === false;
    }
}
