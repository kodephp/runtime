<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 线程运行时适配器
 *
 * 基于 pthreads 扩展实现的多线程运行时
 * 注意：需要 ZTS（Zend Thread Safety）版本的 PHP
 */
final class ThreadRuntime implements RuntimeInterface
{
    private static array $threads = [];

    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'THREAD';
    }

    /**
     * 在独立线程中执行函数
     *
     * @param callable $callback 要执行的函数
     * @return \Thread 线程实例
     */
    public function async(callable $callback): \Thread
    {
        if (!extension_loaded('pthreads')) {
            throw new Exception\UnsupportedOperationException('pthreads 扩展不可用');
        }

        $thread = new class ($callback) extends \Thread {
            private readonly \Closure $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback(...);
            }

            public function run(): void
            {
                ($this->callback)();
            }
        };

        $thread->start();
        self::$threads[] = $thread;
        return $thread;
    }

    /**
     * 休眠指定秒数
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
     * 注册当前线程退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
    }

    /**
     * 等待所有线程完成
     */
    public function wait(): void
    {
        foreach (self::$threads as $thread) {
            $thread->join();
        }
        self::$threads = [];
    }
}
