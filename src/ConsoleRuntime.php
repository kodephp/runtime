<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Console 运行时适配器
 *
 * 装饰器实现：并发能力完全委托给当前环境下最佳的运行时
 * （Swoole / Swow / Fiber / CLI），自身仅叠加 kode/console 的输出能力。
 *
 * v3.0 起 Console 不再抢占自动探测结果，因此在命令行程序中使用控制台输出
 * 不会再导致协程能力被降级为同步执行
 */
final class ConsoleRuntime implements RuntimeInterface
{
    private static ?\Kode\Console\Output $output = null;

    private readonly RuntimeInterface $inner;

    /**
     * @param RuntimeInterface|null $inner 被装饰的运行时，默认自动探测
     */
    public function __construct(?RuntimeInterface $inner = null)
    {
        $this->inner = $inner ?? RuntimeAdapterFactory::createConcurrent();
    }

    /**
     * 获取被装饰的底层运行时
     *
     * @return RuntimeInterface 底层运行时
     */
    public function innerRuntime(): RuntimeInterface
    {
        return $this->inner;
    }

    #[\Override]
    public function getName(): string
    {
        return RuntimeEnvironment::Console->label();
    }

    #[\Override]
    public function environment(): RuntimeEnvironment
    {
        return RuntimeEnvironment::Console;
    }

    #[\Override]
    public function supportsConcurrency(): bool
    {
        return $this->inner->supportsConcurrency();
    }

    #[\Override]
    public function async(callable $callback): mixed
    {
        return $this->inner->async($callback);
    }

    #[\Override]
    public function run(callable $main): mixed
    {
        return $this->inner->run($main);
    }

    #[\Override]
    public function parallel(iterable $tasks): array
    {
        return $this->inner->parallel($tasks);
    }

    #[\Override]
    public function sleep(float $seconds): void
    {
        $this->inner->sleep($seconds);
    }

    #[\Override]
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        return $this->inner->createChannel($capacity);
    }

    #[\Override]
    public function defer(callable $callback): void
    {
        $this->inner->defer($callback);
    }

    #[\Override]
    public function wait(): void
    {
        $this->inner->wait();
    }

    /**
     * 输出信息到控制台
     *
     * @param string $message 信息内容
     */
    public static function info(string $message): void
    {
        self::getOutput()->info($message);
    }

    /**
     * 输出警告到控制台
     *
     * @param string $message 警告内容
     */
    public static function warn(string $message): void
    {
        self::getOutput()->warn($message);
    }

    /**
     * 输出错误到控制台
     *
     * @param string $message 错误内容
     */
    public static function error(string $message): void
    {
        self::getOutput()->error($message);
    }

    /**
     * 输出成功信息到控制台
     *
     * @param string $message 成功内容
     */
    public static function success(string $message): void
    {
        self::getOutput()->success($message);
    }

    /**
     * 输出普通文本到控制台
     *
     * @param string $text 文本内容
     * @param string $color 颜色（可选）
     */
    public static function line(string $text, string $color = ''): void
    {
        self::getOutput()->line($text, $color);
    }

    /**
     * 设置输出器实例
     *
     * @param \Kode\Console\Output $output 输出器
     */
    public static function setOutput(\Kode\Console\Output $output): void
    {
        self::$output = $output;
    }

    /**
     * 获取输出器实例
     *
     * @return \Kode\Console\Output 输出器
     * @throws Exception\UnsupportedOperationException kode/console 未安装时抛出
     */
    private static function getOutput(): \Kode\Console\Output
    {
        if (self::$output === null) {
            if (!RuntimeEnvironment::Console->isAvailable()) {
                throw new Exception\UnsupportedOperationException(
                    RuntimeEnvironment::Console->unavailableMessage()
                );
            }

            self::$output = new \Kode\Console\Output();
        }

        return self::$output;
    }
}
