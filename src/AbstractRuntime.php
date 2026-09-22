<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 运行时适配器抽象基类
 *
 * 统一实现 defer 作用域语义与通用能力：
 * - defer 注册到「当前作用域」，作用域结束时按后进先出执行
 * - 协程内注册的 defer 跟随所属协程，互不串扰
 * - 主流程（根作用域）注册的 defer 在 wait() 或脚本结束时执行
 */
abstract class AbstractRuntime implements RuntimeInterface
{
    /**
     * 根作用域标识
     */
    protected const string ROOT_SCOPE = 'root';

    /**
     * 作用域 => 清理回调列表
     *
     * @var array<string, list<callable>>
     */
    private array $scopes = [];

    /**
     * 同步嵌套作用域栈
     *
     * @var list<string>
     */
    private array $syncScopes = [];

    private int $scopeSequence = 0;

    private bool $shutdownHooked = false;

    #[\Override]
    public function getName(): string
    {
        return $this->environment()->label();
    }

    #[\Override]
    public function supportsConcurrency(): bool
    {
        return $this->environment()->supportsConcurrency();
    }

    #[\Override]
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new CliChannel($capacity);
    }

    #[\Override]
    public function defer(callable $callback): void
    {
        $key = $this->currentScope();

        if ($key === self::ROOT_SCOPE) {
            $this->hookShutdown();
        }

        $this->scopes[$key][] = $callback;
    }

    #[\Override]
    public function run(callable $main): mixed
    {
        $result = $this->inScope($main);
        $this->wait();

        return $result;
    }

    /**
     * 默认按顺序执行任务，具备并发能力的适配器需覆写本方法
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 执行结果
     */
    #[\Override]
    public function parallel(iterable $tasks): array
    {
        $results = [];
        foreach ($tasks as $key => $task) {
            $results[$key] = $this->inScope($task);
        }

        return $results;
    }

    #[\Override]
    public function wait(): void
    {
        $this->flushScope(self::ROOT_SCOPE);
    }

    /**
     * 在独立的 defer 作用域中执行回调
     *
     * @param callable $callback 回调
     * @return mixed 回调返回值
     */
    protected function inScope(callable $callback): mixed
    {
        $key = $this->pushScope();

        try {
            return $callback();
        } finally {
            $this->popScope($key);
        }
    }

    /**
     * 获取当前作用域标识
     *
     * @return string 作用域标识
     */
    protected function currentScope(): string
    {
        $fiber = \Fiber::getCurrent();
        if ($fiber !== null) {
            return 'fiber:' . spl_object_id($fiber);
        }

        return $this->syncScopes === [] ? self::ROOT_SCOPE : $this->syncScopes[array_key_last($this->syncScopes)];
    }

    /**
     * 生成协程作用域标识
     *
     * @param \Fiber $fiber 协程
     * @return string 作用域标识
     */
    protected function fiberScope(\Fiber $fiber): string
    {
        return 'fiber:' . spl_object_id($fiber);
    }

    /**
     * 压入一个新的同步作用域
     *
     * @return string 作用域标识
     */
    protected function pushScope(): string
    {
        $key = 'sync:' . (++$this->scopeSequence);
        $this->syncScopes[] = $key;

        return $key;
    }

    /**
     * 弹出同步作用域并执行其清理回调
     *
     * @param string $key 作用域标识
     */
    protected function popScope(string $key): void
    {
        $index = array_search($key, $this->syncScopes, true);
        if ($index !== false) {
            array_splice($this->syncScopes, (int) $index);
        }

        $this->flushScope($key);
    }

    /**
     * 执行并清空指定作用域的清理回调（后进先出）
     *
     * @param string $key 作用域标识
     */
    protected function flushScope(string $key): void
    {
        $callbacks = $this->scopes[$key] ?? [];
        unset($this->scopes[$key]);

        foreach (array_reverse($callbacks) as $callback) {
            try {
                $callback();
            } catch (\Throwable) {
                // 清理阶段的异常不应影响主流程
            }
        }
    }

    /**
     * 丢弃从父进程继承来的待执行清理（fork 出的子进程专用）
     *
     * 子进程拿到的是父进程的完整内存镜像，其中包括「父进程自己的收尾回调」与
     * 「父进程挂起的协程」。子进程 exit() 会照常走 shutdown 链，原样保留就等于把
     * 父进程的清理重放一遍——解锁、提交、发响应这类副作用会凭空多做一次。
     *
     * @internal 仅供 Runtime::fork() 在子进程侧调用
     */
    public function discardInheritedState(): void
    {
        $this->scopes = [];
    }

    /**
     * 注册脚本结束时的根作用域清理
     */
    private function hookShutdown(): void
    {
        if ($this->shutdownHooked) {
            return;
        }

        $this->shutdownHooked = true;
        register_shutdown_function(function (): void {
            $this->flushScope(self::ROOT_SCOPE);
        });
    }
}
