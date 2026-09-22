<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Swoole 运行时适配器
 *
 * 基于 Swoole 协程引擎实现的运行时
 */
final class SwooleRuntime extends AbstractRuntime
{
    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Swoole;
    }

    /**
     * 启动协程
     *
     * @param callable $callback 协程函数
     * @return int 协程 ID
     */
    #[\Override]
    public function async(callable $callback): int
    {
        return \Swoole\Coroutine::create($callback);
    }

    /**
     * 在协程容器中执行入口函数并等待其完成
     *
     * @param callable $main 入口函数
     * @return mixed 入口函数返回值
     */
    #[\Override]
    public function run(callable $main): mixed
    {
        if (\Swoole\Coroutine::getCid() > 0) {
            return $main();
        }

        $result = null;
        $error = null;
        \Swoole\Coroutine\run(static function () use ($main, &$result, &$error): void {
            try {
                $result = $main();
            } catch (\Throwable $e) {
                // 捕获协程内异常，在协程结束后向上抛出，避免成为致命错误
                $error = $e;
            }
        });

        if ($error !== null) {
            throw $error;
        }

        return $result;
    }

    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if (\Swoole\Coroutine::getCid() > 0) {
            \Swoole\Coroutine::sleep($seconds);
            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }

    #[\Override]
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new SwooleChannel($capacity);
    }

    /**
     * 并发执行任务并收集结果
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 与任务键一一对应的结果
     */
    #[\Override]
    public function parallel(iterable $tasks): array
    {
        $callables = is_array($tasks) ? $tasks : iterator_to_array($tasks);

        if ($callables === []) {
            return [];
        }

        if (function_exists('\Swoole\Coroutine\batch')) {
            return $this->run(static fn (): array => \Swoole\Coroutine\batch($callables));
        }

        return $this->run(function () use ($callables): array {
            $results = [];
            $channel = new \Swoole\Coroutine\Channel(count($callables));

            foreach ($callables as $key => $task) {
                \Swoole\Coroutine::create(static function () use ($task, $key, $channel): void {
                    $channel->push([$key, $task()]);
                });
            }

            for ($i = 0, $total = count($callables); $i < $total; $i++) {
                [$key, $value] = $channel->pop();
                $results[$key] = $value;
            }

            return $results;
        });
    }

    /**
     * 驱动事件循环，直到 async() 派发的协程全部结束
     *
     * 协程一旦挂起（sleep / channel / IO），只清作用域是等不到它收尾的：
     * 那截尾巴会漂到 Swoole 的 rshutdown 里执行（6.x 已对此发 deprecated 警告），
     * 调用方在 wait() 之后看到的状态就成了「还没做完」。
     */
    #[\Override]
    public function wait(): void
    {
        // 只在主流程收口：已经在协程里时，外层 run()/服务端循环才是驱动者，嵌套驱动会自锁
        if (\Swoole\Coroutine::getCid() <= 0 && iterator_count(\Swoole\Coroutine::listCoroutines()) > 0) {
            \Swoole\Event::wait();
        }

        parent::wait();
    }

    /**
     * 注册协程退出回调
     *
     * @param callable $callback 清理函数
     */
    #[\Override]
    public function defer(callable $callback): void
    {
        if (\Swoole\Coroutine::getCid() > 0) {
            \Swoole\Coroutine::defer($callback);
            return;
        }

        parent::defer($callback);
    }
}
