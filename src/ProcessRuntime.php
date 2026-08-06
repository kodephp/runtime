<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 进程运行时适配器
 *
 * 基于 pcntl_fork 实现的多进程运行时，
 * parallel() 通过 socket pair 回收子进程的执行结果
 */
final class ProcessRuntime extends AbstractRuntime
{
    /**
     * 已创建的子进程 PID
     *
     * @var list<int>
     */
    private array $children = [];

    /**
     * 子进程退出码：PID => 退出码
     *
     * @var array<int, int>
     */
    private array $exitCodes = [];

    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Process;
    }

    /**
     * 在独立进程中执行函数
     *
     * @param callable $callback 要执行的函数
     * @return int 子进程 PID
     * @throws Exception\UnsupportedOperationException PCNTL 不可用时抛出
     * @throws Exception\RuntimeException 进程创建失败时抛出
     */
    #[\Override]
    public function async(callable $callback): int
    {
        $this->assertSupported();

        $pid = pcntl_fork();

        if ($pid === -1 || $pid === false || $pid === null) {
            throw new Exception\RuntimeException('进程创建失败（当前环境不支持 fork）');
        }

        if ($pid === 0) {
            $this->runChild($callback);
        }

        $this->children[] = $pid;

        return $pid;
    }

    #[\Override]
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }

    /**
     * 并发执行任务并回收各子进程的返回值
     *
     * @param iterable<array-key, callable> $tasks 任务列表
     * @return array<array-key, mixed> 与任务键一一对应的结果
     * @throws Exception\RuntimeException 子进程执行失败时抛出
     */
    #[\Override]
    public function parallel(iterable $tasks): array
    {
        $this->assertSupported();

        $pending = [];

        foreach ($tasks as $key => $task) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pair === false) {
                throw new Exception\RuntimeException('无法创建进程间通信管道');
            }

            [$parent, $child] = $pair;
            $pid = pcntl_fork();

            if ($pid === -1 || $pid === false || $pid === null) {
                fclose($parent);
                fclose($child);
                throw new Exception\RuntimeException('进程创建失败（当前环境不支持 fork）');
            }

            if ($pid === 0) {
                fclose($parent);
                $this->runChild($task, $child);
            }

            fclose($child);
            $pending[$key] = ['pid' => $pid, 'stream' => $parent];
        }

        return $this->collect($pending);
    }

    /**
     * 等待所有子进程结束
     */
    #[\Override]
    public function wait(): void
    {
        foreach ($this->children as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            $this->exitCodes[$pid] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
        }

        $this->children = [];

        parent::wait();
    }

    /**
     * 获取已结束子进程的退出码
     *
     * @return array<int, int> PID => 退出码
     */
    public function exitCodes(): array
    {
        return $this->exitCodes;
    }

    /**
     * 回收子进程结果
     *
     * @param array<array-key, array{pid: int, stream: resource}> $pending 待回收的子进程
     * @return array<array-key, mixed> 执行结果
     * @throws Exception\RuntimeException 子进程执行失败时抛出
     */
    private function collect(array $pending): array
    {
        $results = [];

        foreach ($pending as $key => $child) {
            $raw = stream_get_contents($child['stream']);
            fclose($child['stream']);

            $status = 0;
            pcntl_waitpid($child['pid'], $status);
            $this->exitCodes[$child['pid']] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;

            $payload = is_string($raw) && $raw !== '' ? @unserialize($raw) : null;

            if (!is_array($payload) || !array_key_exists('ok', $payload)) {
                throw new Exception\RuntimeException("子进程 {$child['pid']} 未返回有效结果");
            }

            if ($payload['ok'] !== true) {
                throw new Exception\RuntimeException("子进程 {$child['pid']} 执行失败: {$payload['error']}");
            }

            $results[$key] = $payload['value'];
        }

        return $results;
    }

    /**
     * 子进程执行体（永不返回）
     *
     * @param callable $task 任务
     * @param resource|null $stream 结果回写管道
     */
    private function runChild(callable $task, mixed $stream = null): never
    {
        $payload = ['ok' => true, 'value' => null, 'error' => null];

        try {
            $value = $this->inScope($task);
            if ($stream !== null) {
                $payload['value'] = $value;
            }
        } catch (\Throwable $e) {
            $payload = ['ok' => false, 'value' => null, 'error' => $e->getMessage()];
        }

        if ($stream !== null) {
            try {
                $encoded = serialize($payload);
            } catch (\Throwable $e) {
                $encoded = serialize(['ok' => false, 'value' => null, 'error' => '结果无法序列化: ' . $e->getMessage()]);
            }

            fwrite($stream, $encoded);
            fclose($stream);
        }

        exit($payload['ok'] === true ? 0 : 1);
    }

    /**
     * 断言当前环境支持多进程
     *
     * @throws Exception\UnsupportedOperationException 不支持时抛出
     */
    private function assertSupported(): void
    {
        if (!RuntimeEnvironment::Process->isAvailable()) {
            throw new Exception\UnsupportedOperationException(
                RuntimeEnvironment::Process->unavailableMessage()
            );
        }
    }
}
