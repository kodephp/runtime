<?php

declare(strict_types=1);

namespace Kode\Runtime;

use Kode\Context\Context as KodeContext;

/**
 * Context manager for coroutine/thread-safe storage.
 *
 * This class provides a way to store and retrieve values that are local to the current
 * execution context (coroutine, thread, or global scope).
 *
 * This implementation uses the kode/context package for context management.
 */
class Context
{
    /**
     * Set a value in the context
     *
     * @param string $key The key to store the value under
     * @param mixed $value The value to store
     * @param string $namespace The namespace to store the value in (default: global)
     * @return void
     */
    public static function set(string $key, mixed $value, string $namespace = 'global'): void
    {
        KodeContext::set($key, $value, $namespace);
    }

    /**
     * Get a value from the context
     *
     * @param string $key The key to retrieve the value for
     * @param string $namespace The namespace to retrieve the value from (default: global)
     * @return mixed The value stored under the key, or null if not found
     */
    public static function get(string $key, string $namespace = 'global'): mixed
    {
        return KodeContext::get($key, $namespace);
    }

    /**
     * Delete a value from the context
     *
     * @param string $key The key to delete
     * @param string $namespace The namespace to delete the key from (default: global)
     * @return void
     */
    public static function delete(string $key, string $namespace = 'global'): void
    {
        KodeContext::delete($key, $namespace);
    }
}
