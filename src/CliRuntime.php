<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * CLI runtime adapter implementation for synchronous execution.
 */
class CliRuntime implements RuntimeInterface
{
    /**
     * @var array[callable]
     */
    private static array $deferCallbacks = [];

    /**
     * Get the name of the runtime environment
     *
     * @return string Environment name
     */
    public function getName(): string
    {
        return 'CLI';
    }

    /**
     * Execute a coroutine synchronously (blocking)
     *
     * @param callable $callback The function to execute
     * @return mixed Return value of the callback
     */
    public function async(callable $callback)
    {
        try {
            return $callback();
        } finally {
            // Execute defer callbacks
            foreach (array_reverse(self::$deferCallbacks) as $deferCallback) {
                try {
                    $deferCallback();
                } catch (\Throwable $e) {
                    // Log error but continue
                }
            }
            self::$deferCallbacks = [];
        }
    }

    /**
     * Sleep for the specified number of seconds
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int)($seconds * 1000000));
        }
    }

    /**
     * Create a new channel with the specified capacity
     *
     * @param int $capacity Channel capacity
     * @return ChannelInterface
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new CliChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current "coroutine" exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void
    {
        self::$deferCallbacks[] = $callback;
    }

    /**
     * Wait for all coroutines to complete (no-op in CLI mode)
     *
     * @return void
     */
    public function wait(): void
    {
        // In CLI mode, async operations are synchronous, so no waiting is needed
    }
}
