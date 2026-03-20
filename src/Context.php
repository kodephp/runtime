<?php

declare(strict_types=1);

namespace Kode\Runtime;

use Kode\Context\Context as KodeContext;

/**
 * 上下文管理器
 *
 * 基于 kode/context 包实现的协程/纤程上下文封装
 */
final class Context
{
    /**
     * 设置上下文值
     *
     * @param string $key 键名
     * @param mixed $value 值
     */
    public static function set(string $key, mixed $value): void
    {
        KodeContext::set($key, $value);
    }

    /**
     * 获取上下文值
     *
     * @param string $key 键名
     * @param mixed $default 默认值（当键不存在时返回）
     * @return mixed 值
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return KodeContext::get($key, $default);
    }

    /**
     * 删除上下文值
     *
     * @param string $key 键名
     */
    public static function delete(string $key): void
    {
        KodeContext::delete($key);
    }

    /**
     * 检查键是否存在
     *
     * @param string $key 键名
     * @return bool 存在返回 true
     */
    public static function has(string $key): bool
    {
        return KodeContext::has($key);
    }

    /**
     * 清空当前上下文
     */
    public static function clear(): void
    {
        KodeContext::clear();
    }

    /**
     * 复制当前上下文为数组
     *
     * @return array<string, mixed>
     */
    public static function copy(): array
    {
        return KodeContext::copy();
    }

    /**
     * 获取当前上下文所有键名
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return KodeContext::keys();
    }

    /**
     * 在新上下文作用域中执行回调
     *
     * @template T
     * @param callable(): T $callable 回调函数
     * @return T
     */
    public static function run(callable $callable): mixed
    {
        return KodeContext::run($callable);
    }
}
