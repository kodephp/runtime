<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Runtime facade class that provides a unified interface for different runtime environments.
 *
 * This class detects the current environment and delegates operations to the appropriate adapter.
 */
final class Runtime
{
    /**
     * Current runtime adapter instance
     *
     * @var RuntimeInterface|null
     */
    private static ?RuntimeInterface $adapter = null;

    /**
     * Get the current runtime environment
     *
     * @return string Environment name (SWOOLE|SWOW|FIBER|CLI)
     */
    public static function getEnvironment(): string
    {
        return self::getAdapter()->getName();
    }

    /**
     * Execute a coroutine asynchronously
     *
     * @param callable $callback The coroutine function to execute
     * @return mixed Coroutine handle or ID
     */
    public static function async(callable $callback)
    {
        return self::getAdapter()->async($callback);
    }

    /**
     * Sleep for the specified number of seconds
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
     * @param int $capacity Channel capacity (0 for unlimited)
     * @return ChannelInterface
     */
    public static function createChannel(int $capacity = 0): ChannelInterface
    {
        return self::getAdapter()->createChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current coroutine exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public static function defer(callable $callback): void
    {
        self::getAdapter()->defer($callback);
    }

    /**
     * Wait for all coroutines to complete
     *
     * @return void
     */
    public static function wait(): void
    {
        self::getAdapter()->wait();
    }

    /**
     * Get the appropriate runtime adapter for the current environment
     *
     * @return RuntimeInterface
     */
    private static function getAdapter(): RuntimeInterface
    {
        if (self::$adapter === null) {
            self::$adapter = self::detectEnvironment();
        }

        return self::$adapter;
    }

    /**
     * Detect the current runtime environment and return the appropriate adapter
     *
     * @return RuntimeInterface
     */
    private static function detectEnvironment(): RuntimeInterface
    {
        // Check for Swoole
        if (extension_loaded('swoole') && defined('SWOOLE_VERSION')) {
            return new SwooleRuntime();
        }

        // Check for Swow
        if (extension_loaded('swow')) {
            return new SwowRuntime();
        }

        // Check for Fiber support
        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            return new FiberRuntime();
        }

        // Fallback to CLI mode
        return new CliRuntime();
    }
}
