<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时适配器工厂类
 *
 * 根据当前环境创建合适的运行时适配器实例
 */
final class RuntimeAdapterFactory
{
    public const ENV_SWOOLE = 'swoole';
    public const ENV_SWOW = 'swow';
    public const ENV_FIBER = 'fiber';
    public const ENV_PROCESS = 'process';
    public const ENV_THREAD = 'thread';
    public const ENV_CLI = 'cli';
    public const ENV_CONSOLE = 'console';

    /**
     * 根据当前环境创建运行时适配器
     *
     * @param string|null $environment 可选的环境名称，用于强制指定
     * @return RuntimeInterface 适配器实例
     */
    public static function create(?string $environment = null): RuntimeInterface
    {
        if ($environment !== null) {
            return self::createForEnvironment($environment);
        }

        if (extension_loaded('swoole')) {
            return new SwooleRuntime();
        }

        if (extension_loaded('swow')) {
            return new SwowRuntime();
        }

        if (class_exists(\Fiber::class)) {
            return new FiberRuntime();
        }

        return new CliRuntime();
    }

    /**
     * 为指定环境创建运行时适配器
     *
     * @param string $environment 环境名称
     * @return RuntimeInterface 适配器实例
     * @throws Exception\UnsupportedOperationException 如果环境不支持
     */
    public static function createForEnvironment(string $environment): RuntimeInterface
    {
        return match ($environment) {
            self::ENV_SWOOLE => extension_loaded('swoole')
                ? new SwooleRuntime()
                : throw new Exception\UnsupportedOperationException('Swoole 扩展不可用'),
            self::ENV_SWOW => extension_loaded('swow')
                ? new SwowRuntime()
                : throw new Exception\UnsupportedOperationException('Swow 扩展不可用'),
            self::ENV_FIBER => class_exists(\Fiber::class)
                ? new FiberRuntime()
                : throw new Exception\UnsupportedOperationException('当前 PHP 版本不支持 Fiber'),
            self::ENV_PROCESS => function_exists('pcntl_fork')
                ? new ProcessRuntime()
                : throw new Exception\UnsupportedOperationException('PCNTL 扩展不可用'),
            self::ENV_THREAD => extension_loaded('pthreads')
                ? new ThreadRuntime()
                : throw new Exception\UnsupportedOperationException('pthreads 扩展不可用'),
            self::ENV_CLI => new CliRuntime(),
            self::ENV_CONSOLE => class_exists(\Kode\Console\Output::class)
                ? new ConsoleRuntime()
                : throw new Exception\UnsupportedOperationException('kode/console 包不可用'),
            default => throw new Exception\UnsupportedOperationException(
                "不支持的运行时环境: {$environment}"
            ),
        };
    }

    /**
     * 检查 Swoole 是否可用
     *
     * @return bool 可用返回 true
     */
    public static function isSwooleAvailable(): bool
    {
        return extension_loaded('swoole') && defined('SWOOLE_VERSION');
    }

    /**
     * 检查 Swow 是否可用
     *
     * @return bool 可用返回 true
     */
    public static function isSwowAvailable(): bool
    {
        return extension_loaded('swow');
    }

    /**
     * 检查 Fiber 是否支持
     *
     * @return bool 支持返回 true
     */
    public static function isFiberSupported(): bool
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }
}
