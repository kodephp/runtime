<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Interface for channel implementations that provides a unified API for coroutine communication.
 */
interface ChannelInterface
{
    /**
     * Push data to the channel
     *
     * @param mixed $data Data to push
     * @return bool True if data was pushed successfully, false otherwise
     */
    public function push(mixed $data): bool;

    /**
     * Pop data from the channel
     *
     * @return mixed Data from the channel
     */
    public function pop(): mixed;

    /**
     * Get the current capacity of the channel
     *
     * @return int Current capacity
     */
    public function getCapacity(): int;

    /**
     * Get the current length of the channel (number of items in the channel)
     *
     * @return int Current length
     */
    public function getLength(): int;

    /**
     * Close the channel
     *
     * @return void
     */
    public function close(): void;

    /**
     * Check if the channel is closed
     *
     * @return bool True if channel is closed, false otherwise
     */
    public function isClosed(): bool;
}
