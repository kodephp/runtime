<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Fiber runtime adapter implementation.
 */
class FiberRuntime implements RuntimeInterface
{
    /**
     * @var array[\Fiber]
     */
    private static array $fibers = [];

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
        return 'FIBER';
    }

    /**
     * Execute a coroutine asynchronously
     *
     * @param callable $callback The coroutine function to execute
     * @return \Fiber Fiber instance
     */
    public function async(callable $callback)
    {
        $fiber = new \Fiber(function () use ($callback) {
            try {
                $callback();
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
        });

        self::$fibers[] = $fiber;
        $fiber->start();
        return $fiber;
    }

    /**
     * Sleep for the specified number of seconds
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $start = microtime(true);
        while (microtime(true) - $start < $seconds) {
            \Fiber::suspend();
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
        return new FiberChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current coroutine exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void
    {
        self::$deferCallbacks[] = $callback;
    }

    /**
     * Wait for all coroutines to complete
     *
     * @return void
     */
    public function wait(): void
    {
        // In a pure Fiber implementation, we would need an event loop
        // This is a simplified implementation for demonstration
        foreach (self::$fibers as $fiber) {
            if ($fiber->isTerminated() === false) {
                try {
                    $fiber->resume();
                } catch (\FiberError $e) {
                    // Fiber is already suspended or terminated
                }
            }
        }
    }
}
