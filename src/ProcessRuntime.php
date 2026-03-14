<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 进程运行时适配器
 *
 * 基于 pcntl_fork 实现的多进程运行时
 */
final class ProcessRuntime implements RuntimeInterface
{
    private static array $childProcesses = [];

    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'PROCESS';
    }

    /**
     * 在独立进程中执行函数
     *
     * @param callable $callback 要执行的函数
     * @return int 进程 ID
     */
    public function async(callable $callback): int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new Exception\RuntimeException('进程创建失败');
        } elseif ($pid === 0) {
            try {
                $callback();
            } finally {
                exit(0);
            }
        } else {
            self::$childProcesses[] = $pid;
            return $pid;
        }
    }

    /**
     * 休眠指定秒数（支持微秒级精度）
     *
     * @param float $seconds 休眠秒数
     */
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int)($seconds * 1_000_000));
        }
    }

    /**
     * 创建一个通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return new CliChannel($capacity);
    }

    /**
     * 注册当前进程退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
        register_shutdown_function($callback);
    }

    /**
     * 等待所有子进程完成
     */
    public function wait(): void
    {
        foreach (self::$childProcesses as $pid) {
            pcntl_waitpid($pid, $status);
        }
        self::$childProcesses = [];
    }
}
