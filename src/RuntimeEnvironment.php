<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时环境枚举
 *
 * 以类型安全的方式描述所有受支持的运行环境，
 * 并提供可用性探测与自动降级检测能力
 */
enum RuntimeEnvironment: string
{
    case Swoole = 'swoole';
    case Swow = 'swow';
    case Fiber = 'fiber';
    case Process = 'process';
    case Thread = 'thread';
    case Console = 'console';
    case Cli = 'cli';

    /**
     * 自动检测顺序（并发能力由强到弱）
     *
     * @var list<string>
     */
    private const array DETECT_ORDER = ['swoole', 'swow', 'fiber', 'cli'];

    /**
     * 获取环境显示名称（大写）
     *
     * @return string 显示名称
     */
    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * 当前环境是否可用
     *
     * @return bool 可用返回 true
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::Swoole => extension_loaded('swoole'),
            self::Swow => extension_loaded('swow'),
            self::Fiber => class_exists(\Fiber::class),
            self::Process => function_exists('pcntl_fork'),
            self::Thread => extension_loaded('parallel'),
            self::Console => class_exists(\Kode\Console\Output::class),
            self::Cli => true,
        };
    }

    /**
     * 当前环境是否具备真正的并发（非阻塞）能力
     *
     * @return bool 具备返回 true
     */
    public function supportsConcurrency(): bool
    {
        return match ($this) {
            self::Swoole, self::Swow, self::Fiber, self::Process, self::Thread => true,
            self::Console, self::Cli => false,
        };
    }

    /**
     * 该环境不可用时的提示信息
     *
     * @return string 提示信息
     */
    public function unavailableMessage(): string
    {
        return match ($this) {
            self::Swoole => 'Swoole 扩展不可用',
            self::Swow => 'Swow 扩展不可用',
            self::Fiber => '当前 PHP 版本不支持 Fiber',
            self::Process => 'PCNTL 扩展不可用',
            self::Thread => 'parallel 扩展不可用（需 ZTS 版本 PHP + ext-parallel）',
            self::Console => 'kode/console 包不可用',
            self::Cli => 'CLI 环境不可用',
        };
    }

    /**
     * 解析环境名称（大小写不敏感）
     *
     * @param string|self $environment 环境名称或枚举
     * @return self 枚举实例
     * @throws Exception\UnsupportedOperationException 名称非法时抛出
     */
    public static function resolve(string|self $environment): self
    {
        if ($environment instanceof self) {
            return $environment;
        }

        return self::tryFrom(strtolower(trim($environment)))
            ?? throw new Exception\UnsupportedOperationException(
                "不支持的运行时环境: {$environment}"
            );
    }

    /**
     * 自动检测当前最佳并发运行环境
     *
     * @return self 检测结果，最低降级为 CLI
     */
    public static function detect(): self
    {
        foreach (self::DETECT_ORDER as $value) {
            $environment = self::from($value);
            if ($environment->isAvailable()) {
                return $environment;
            }
        }

        return self::Cli;
    }

    /**
     * 获取当前所有可用环境
     *
     * @return list<self> 可用环境列表
     */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $env): bool => $env->isAvailable()));
    }
}
