<?php

declare(strict_types=1);

namespace Kode\Runtime;

use Kode\Context\Context as KodeContext;

/**
 * 上下文管理器
 *
 * 提供协程/线程安全的上下文存储
 * 基于 kode/context 包实现
 */
final class Context
{
    /**
     * 设置上下文值
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @param string $namespace 命名空间（默认：global）
     */
    public static function set(string $key, mixed $value, string $namespace = 'global'): void
    {
        KodeContext::set($key, $value, $namespace);
    }

    /**
     * 获取上下文值
     *
     * @param string $key 键名
     * @param string $namespace 命名空间（默认：global）
     * @return mixed 值，不存在则返回 null
     */
    public static function get(string $key, string $namespace = 'global'): mixed
    {
        return KodeContext::get($key, $namespace);
    }

    /**
     * 删除上下文值
     *
     * @param string $key 键名
     * @param string $namespace 命名空间（默认：global）
     */
    public static function delete(string $key, string $namespace = 'global'): void
    {
        KodeContext::delete($key, $namespace);
    }
}
