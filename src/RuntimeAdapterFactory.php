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
    public const string ENV_SWOOLE = 'swoole';
    public const string ENV_SWOW = 'swow';
    public const string ENV_FIBER = 'fiber';
    public const string ENV_PROCESS = 'process';
    public const string ENV_THREAD = 'thread';
    public const string ENV_CLI = 'cli';
    public const string ENV_CONSOLE = 'console';

    /**
     * 根据当前环境创建运行时适配器
     *
     * 自动探测顺序：Swoole → Swow → Fiber → CLI。
     * Console 属于输出增强而非并发模型，需显式指定
     *
     * @param RuntimeEnvironment|string|null $environment 可选的环境，用于强制指定
     * @return RuntimeInterface 适配器实例
     */
    public static function create(RuntimeEnvironment|string|null $environment = null): RuntimeInterface
    {
        if ($environment !== null) {
            return self::createForEnvironment($environment);
        }

        return self::createConcurrent();
    }

    /**
     * 创建当前环境下并发能力最强的运行时适配器
     *
     * @return RuntimeInterface 适配器实例
     */
    public static function createConcurrent(): RuntimeInterface
    {
        return self::createForEnvironment(RuntimeEnvironment::detect());
    }

    /**
     * 为指定环境创建运行时适配器
     *
     * @param RuntimeEnvironment|string $environment 环境
     * @return RuntimeInterface 适配器实例
     * @throws Exception\UnsupportedOperationException 环境非法或不可用时抛出
     */
    public static function createForEnvironment(RuntimeEnvironment|string $environment): RuntimeInterface
    {
        $env = RuntimeEnvironment::resolve($environment);

        if (!$env->isAvailable()) {
            throw new Exception\UnsupportedOperationException($env->unavailableMessage());
        }

        return match ($env) {
            RuntimeEnvironment::Swoole => new SwooleRuntime(),
            RuntimeEnvironment::Swow => new SwowRuntime(),
            RuntimeEnvironment::Fiber => new FiberRuntime(),
            RuntimeEnvironment::Process => new ProcessRuntime(),
            RuntimeEnvironment::Thread => new ThreadRuntime(),
            RuntimeEnvironment::Console => new ConsoleRuntime(),
            RuntimeEnvironment::Cli => new CliRuntime(),
        };
    }

    /**
     * 获取当前所有可用环境
     *
     * @return list<RuntimeEnvironment> 可用环境列表
     */
    public static function availableEnvironments(): array
    {
        return RuntimeEnvironment::available();
    }

    /**
     * 检查指定环境是否可用
     *
     * @param RuntimeEnvironment|string $environment 环境
     * @return bool 可用返回 true
     */
    public static function isAvailable(RuntimeEnvironment|string $environment): bool
    {
        return RuntimeEnvironment::resolve($environment)->isAvailable();
    }

    /**
     * 检查 Swoole 是否可用
     *
     * @return bool 可用返回 true
     */
    public static function isSwooleAvailable(): bool
    {
        return RuntimeEnvironment::Swoole->isAvailable();
    }

    /**
     * 检查 Swow 是否可用
     *
     * @return bool 可用返回 true
     */
    public static function isSwowAvailable(): bool
    {
        return RuntimeEnvironment::Swow->isAvailable();
    }

    /**
     * 检查 Fiber 是否支持
     *
     * @return bool 支持返回 true
     */
    public static function isFiberSupported(): bool
    {
        return RuntimeEnvironment::Fiber->isAvailable();
    }

    /**
     * 检查 Console 是否可用
     *
     * @return bool 可用返回 true
     */
    public static function isConsoleAvailable(): bool
    {
        return RuntimeEnvironment::Console->isAvailable();
    }
}
