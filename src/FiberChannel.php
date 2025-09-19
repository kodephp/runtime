<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Fiber channel implementation using SplQueue.
 */
class FiberChannel implements ChannelInterface
{
    /**
     * @var \SplQueue
     */
    private \SplQueue $queue;

    /**
     * @var int
     */
    private int $capacity;

    /**
     * @var bool
     */
    private bool $closed = false;

    /**
     * Create a new Fiber channel
     *
     * @param int $capacity Channel capacity
     */
    public function __construct(int $capacity = 0)
    {
        $this->queue = new \SplQueue();
        $this->capacity = $capacity > 0 ? $capacity : 0;
    }

    /**
     * Push data to the channel
     *
     * @param mixed $data Data to push
     * @return bool True if data was pushed successfully, false otherwise
     */
    public function push(mixed $data): bool
    {
        if ($this->closed) {
            return false;
        }

        // For simplicity, we're not implementing blocking behavior
        // In a real implementation, this would block when capacity is reached
        if ($this->capacity > 0 && $this->queue->count() >= $this->capacity) {
            return false;
        }

        $this->queue->enqueue($data);
        return true;
    }

    /**
     * Pop data from the channel
     *
     * @return mixed Data from the channel
     */
    public function pop(): mixed
    {
        if ($this->queue->isEmpty() || $this->closed) {
            return null;
        }

        return $this->queue->dequeue();
    }

    /**
     * Get the current capacity of the channel
     *
     * @return int Current capacity
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * Get the current length of the channel (number of items in the channel)
     *
     * @return int Current length
     */
    public function getLength(): int
    {
        return $this->queue->count();
    }

    /**
     * Close the channel
     *
     * @return void
     */
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Check if the channel is closed
     *
     * @return bool True if channel is closed, false otherwise
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
}
