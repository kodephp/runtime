<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole runtime adapter implementation.
 */
class SwooleRuntime implements RuntimeInterface
{
    /**
     * Get the name of the runtime environment
     *
     * @return string Environment name
     */
    public function getName(): string
    {
        return 'SWOOLE';
    }

    /**
     * Execute a coroutine asynchronously
     *
     * @param callable $callback The coroutine function to execute
     * @return int Coroutine ID
     */
    public function async(callable $callback)
    {
        return \Swoole\Coroutine::create($callback);
    }

    /**
     * Sleep for the specified number of seconds
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public function sleep(float $seconds): void
    {
        \Swoole\Coroutine::sleep($seconds);
    }

    /**
     * Create a new channel with the specified capacity
     *
     * @param int $capacity Channel capacity
     * @return ChannelInterface
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwooleChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current coroutine exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void
    {
        \Swoole\Coroutine::defer($callback);
    }

    /**
     * Wait for all coroutines to complete
     *
     * @return void
     */
    public function wait(): void
    {
        // Swoole handles this automatically in coroutine mode
    }
}
