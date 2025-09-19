<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swow runtime adapter implementation.
 */
class SwowRuntime implements RuntimeInterface
{
    /**
     * Get the name of the runtime environment
     *
     * @return string Environment name
     */
    public function getName(): string
    {
        return 'SWOW';
    }

    /**
     * Execute a coroutine asynchronously
     *
     * @param callable $callback The coroutine function to execute
     * @return \Swow\Coroutine Coroutine instance
     */
    public function async(callable $callback)
    {
        return new \Swow\Coroutine($callback);
    }

    /**
     * Sleep for the specified number of seconds
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public function sleep(float $seconds): void
    {
        \Swow\Coroutine::run(function () use ($seconds) {
            \Swow\Coroutine::sleep((int)($seconds * 1000));
        })->join();
    }

    /**
     * Create a new channel with the specified capacity
     *
     * @param int $capacity Channel capacity
     * @return ChannelInterface
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwowChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current coroutine exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void
    {
        // Swow uses finally blocks or explicit cleanup
        // This is a simplified implementation
        \Swow\Coroutine::getCurrent()->addOnCloseCallback($callback);
    }

    /**
     * Wait for all coroutines to complete
     *
     * @return void
     */
    public function wait(): void
    {
        // In Swow, we might need to keep track of coroutines manually
        // This is a simplified implementation
        while (\Swow\Coroutine::count() > 1) {
            \Swow\Coroutine::yield();
        }
    }
}
