<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Runtime facade for unified access to different runtime environments.
 *
 * This static facade provides a unified interface to access different runtime
 * environments like Swoole, Swow, Fiber, Process, Thread, and traditional CLI mode.
 * It automatically detects the current environment and delegates operations
 * to the appropriate adapter.
 *
 * Supported environments:
 * - Swoole: High-performance coroutine server
 * - Swow: Modern PHP coroutine engine
 * - Fiber: Native PHP 8.1+ fiber support
 * - Process: Multi-process execution using PCNTL
 * - Thread: Multi-threaded execution using pthreads
 * - CLI: Traditional command-line interface mode
 */
class Runtime
{
    /**
     * @var RuntimeInterface|null
     */
    private static ?RuntimeInterface $adapter = null;

    /**
     * Get the name of the current runtime environment
     *
     * @return string Environment name
     */
    public static function getName(): string
    {
        return self::getAdapter()->getName();
    }

    /**
     * Execute a function asynchronously in the appropriate runtime environment
     *
     * @param callable $callback The function to execute
     * @return mixed
     */
    public static function async(callable $callback)
    {
        return self::getAdapter()->async($callback);
    }

    /**
     * Sleep for the specified number of seconds in the appropriate runtime environment
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public static function sleep(float $seconds): void
    {
        self::getAdapter()->sleep($seconds);
    }

    /**
     * Create a new channel with the specified capacity
     *
     * @param int $capacity Channel capacity
     * @return ChannelInterface
     */
    public static function createChannel(int $capacity = 0): ChannelInterface
    {
        return self::getAdapter()->createChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current context exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public static function defer(callable $callback): void
    {
        self::getAdapter()->defer($callback);
    }

    /**
     * Wait for all asynchronous operations to complete
     *
     * @return void
     */
    public static function wait(): void
    {
        self::getAdapter()->wait();
    }

    /**
     * Fork a new process (only available in process-capable environments)
     *
     * @param callable $callback Function to execute in the child process
     * @return int Process ID of the child process
     * @throws Exception\UnsupportedOperationException If process forking is not supported
     */
    public static function fork(callable $callback): int
    {
        // Check if PCNTL is available
        if (!function_exists('pcntl_fork')) {
            throw new Exception\UnsupportedOperationException('Process forking is not supported in this environment');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            // Fork failed
            throw new Exception\RuntimeException('Failed to fork process');
        } elseif ($pid === 0) {
            // Child process
            try {
                $callback();
            } finally {
                exit(0);
            }
        } else {
            // Parent process
            return $pid;
        }
    }

    /**
     * Set a specific runtime environment
     *
     * @param string $environment Environment name
     * @return void
     */
    public static function setEnvironment(string $environment): void
    {
        self::$adapter = RuntimeAdapterFactory::createForEnvironment($environment);
    }

    /**
     * Get the appropriate runtime adapter for the current environment
     *
     * @return RuntimeInterface
     */
    private static function getAdapter(): RuntimeInterface
    {
        if (self::$adapter === null) {
            self::$adapter = RuntimeAdapterFactory::create();
        }
        return self::$adapter;
    }

    /**
     * Reset the runtime adapter (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$adapter = null;
    }
}
