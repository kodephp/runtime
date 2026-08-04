<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * CLI 运行时适配器
 *
 * 传统 CLI 模式下的同步执行适配器，作为所有环境的最终降级方案
 */
final class CliRuntime extends AbstractRuntime
{
    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Cli;
    }

    /**
     * 同步执行函数（阻塞式），并在执行结束时触发该作用域的 defer
     *
     * @param callable $callback 要执行的函数
     * @return mixed 函数返回值
     */
    #[\Override]
    public function async(callable $callback): mixed
    {
        return $this->inScope($callback);
    }

    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }
}
