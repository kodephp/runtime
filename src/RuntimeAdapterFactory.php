<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Factory class for creating runtime adapters.
 *
 * This factory handles the creation of appropriate runtime adapters
 * based on the current environment or explicit configuration.
 */
class RuntimeAdapterFactory
{
    /**
     * Runtime environment constants
     */
    public const ENV_SWOOLE = 'swoole';
    public const ENV_SWOW = 'swow';
    public const ENV_FIBER = 'fiber';
    public const ENV_PROCESS = 'process';
    public const ENV_THREAD = 'thread';
    public const ENV_CLI = 'cli';

    /**
     * Create a runtime adapter based on the current environment
     *
     * @param string|null $environment Optional environment to force
     * @return RuntimeInterface
     */
    public static function create(?string $environment = null): RuntimeInterface
    {
        // If environment is explicitly specified, use it
        if ($environment !== null) {
            return self::createForEnvironment($environment);
        }

        // Auto-detect environment
        // Check for Swoole environment
        if (extension_loaded('swoole')) {
            return new SwooleRuntime();
        }

        // Check for Swow environment
        if (extension_loaded('swow')) {
            return new SwowRuntime();
        }

        // Check for Fiber support (PHP 8.1+)
        if (class_exists(\Fiber::class)) {
            return new FiberRuntime();
        }

        // Default to CLI runtime
        return new CliRuntime();
    }

    /**
     * Create a runtime adapter for a specific environment
     *
     * @param string $environment Environment name
     * @return RuntimeInterface
     * @throws Exception\UnsupportedOperationException
     */
    public static function createForEnvironment(string $environment): RuntimeInterface
    {
        switch ($environment) {
            case self::ENV_SWOOLE:
                if (!extension_loaded('swoole')) {
                    throw new Exception\UnsupportedOperationException('Swoole extension is not available');
                }
                return new SwooleRuntime();

            case self::ENV_SWOW:
                if (!extension_loaded('swow')) {
                    throw new Exception\UnsupportedOperationException('Swow extension is not available');
                }
                return new SwowRuntime();

            case self::ENV_FIBER:
                if (!class_exists(\Fiber::class)) {
                    throw new Exception\UnsupportedOperationException('Fiber is not available in this PHP version');
                }
                return new FiberRuntime();

            case self::ENV_PROCESS:
                if (!function_exists('pcntl_fork')) {
                    throw new Exception\UnsupportedOperationException('PCNTL extension is not available');
                }
                return new ProcessRuntime();

            case self::ENV_THREAD:
                if (!extension_loaded('pthreads')) {
                    throw new Exception\UnsupportedOperationException('pthreads extension is not available');
                }
                return new ThreadRuntime();

            case self::ENV_CLI:
                return new CliRuntime();

            default:
                throw new Exception\UnsupportedOperationException("Unsupported runtime environment: {$environment}");
        }
    }

    /**
     * Check if Swoole is available
     *
     * @return bool
     */
    public static function isSwooleAvailable(): bool
    {
        return extension_loaded('swoole') && defined('SWOOLE_VERSION');
    }

    /**
     * Check if Swow is available
     *
     * @return bool
     */
    public static function isSwowAvailable(): bool
    {
        return extension_loaded('swow');
    }

    /**
     * Check if Fiber is supported
     *
     * @return bool
     */
    public static function isFiberSupported(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }

    /**
     * Create a Swoole runtime adapter
     *
     * @return RuntimeInterface
     */
    public static function createSwooleAdapter(): RuntimeInterface
    {
        return new SwooleRuntime();
    }

    /**
     * Create a Swow runtime adapter
     *
     * @return RuntimeInterface
     */
    public static function createSwowAdapter(): RuntimeInterface
    {
        return new SwowRuntime();
    }

    /**
     * Create a Fiber runtime adapter
     *
     * @return RuntimeInterface
     */
    public static function createFiberAdapter(): RuntimeInterface
    {
        return new FiberRuntime();
    }

    /**
     * Create a CLI runtime adapter
     *
     * @return RuntimeInterface
     */
    public static function createCliAdapter(): RuntimeInterface
    {
        return new CliRuntime();
    }
}
