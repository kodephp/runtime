<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 线程运行时适配器
 *
 * 基于 ext-parallel 实现（PHP 8 的官方线程扩展，需 ZTS 版本 PHP）。
 * 历史 pthreads 扩展不支持 PHP 8，已不再作为备选方案
 */
final class ThreadRuntime extends AbstractRuntime
{
    /**
     * \parallel\Runtime 类名
     */
    private const string RUNTIME_CLASS = '\parallel\Runtime';

    /**
     * 已创建的 \parallel\Future 句柄
     *
     * @var list<object>
     */
    private array $futures = [];

    private ?object $threadRuntime = null;

    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Thread;
    }

    /**
     * 在独立线程中执行函数
     *
     * @param callable $callback 要执行的函数
     * @return object \parallel\Future 实例
     * @throws Exception\UnsupportedOperationException parallel 扩展不可用时抛出
     */
    #[\Override]
    public function async(callable $callback): object
    {
        $future = $this->submit($callback);
        $this->futures[] = $future;

        return $future;
    }

    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }

    /**
     * 并发执行任务并收集线程返回值
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 与任务键一一对应的结果
     */
    #[\Override]
    public function parallel(iterable $tasks): array
    {
        $futures = [];
        foreach ($tasks as $key => $task) {
            $futures[$key] = $this->submit($task);
        }

        $results = [];
        foreach ($futures as $key => $future) {
            $results[$key] = $future->value();
        }

        return $results;
    }

    /**
     * 等待所有线程结束
     */
    #[\Override]
    public function wait(): void
    {
        foreach ($this->futures as $future) {
            $future->value();
        }

        $this->futures = [];

        parent::wait();
    }

    /**
     * 提交任务到线程运行时
     *
     * @param callable $task 任务
     * @return object \parallel\Future 实例
     * @throws Exception\UnsupportedOperationException parallel 扩展不可用时抛出
     */
    private function submit(callable $task): object
    {
        if (!RuntimeEnvironment::Thread->isAvailable()) {
            throw new Exception\UnsupportedOperationException(
                RuntimeEnvironment::Thread->unavailableMessage()
            );
        }

        /** @var class-string $runtimeClass */
        $runtimeClass = self::RUNTIME_CLASS;
        $this->threadRuntime ??= new $runtimeClass();

        return $this->threadRuntime->run($task(...));
    }
}
