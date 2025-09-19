<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Interface for runtime adapters that provides a unified API across different environments.
 */
interface RuntimeInterface
{
    /**
     * Get the name of the runtime environment
     *
     * @return string Environment name (SWOOLE|SWOW|FIBER|CLI)
     */
    public function getName(): string;

    /**
     * Execute a coroutine asynchronously
     *
     * @param callable $callback The coroutine function to execute
     * @return mixed Coroutine handle or ID
     */
    public function async(callable $callback);

    /**
     * Sleep for the specified number of seconds
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public function sleep(float $seconds): void;

    /**
     * Create a new channel with the specified capacity
     *
     * @param int $capacity Channel capacity (0 for unlimited)
     * @return ChannelInterface
     */
    public function createChannel(int $capacity = 0): ChannelInterface;

    /**
     * Register a callback to be executed when the current coroutine exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void;

    /**
     * Wait for all coroutines to complete
     *
     * @return void
     */
    public function wait(): void;
}
