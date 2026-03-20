<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Console 运行时适配器
 *
 * 基于 kode/console 包实现的运行时，集成控制台输入输出功能
 */
final class ConsoleRuntime implements RuntimeInterface
{
    private static array $deferCallbacks = [];
    private static ?\Kode\Console\Output $output = null;

    /**
     * 获取运行时环境名称
     *
     * @return string 环境名称
     */
    public function getName(): string
    {
        return 'CONSOLE';
    }

    /**
     * 异步执行函数
     *
     * @param callable $callback 要执行的函数
     * @return mixed 函数返回值
     */
    public function async(callable $callback): mixed
    {
        try {
            return $callback();
        } finally {
            foreach (array_reverse(self::$deferCallbacks) as $deferCallback) {
                try {
                    $deferCallback();
                } catch (\Throwable) {
                }
            }
            self::$deferCallbacks = [];
        }
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
        return new ConsoleChannel($capacity);
    }

    /**
     * 注册当前上下文退出时执行的回调
     *
     * @param callable $callback 清理函数
     */
    public function defer(callable $callback): void
    {
        self::$deferCallbacks[] = $callback;
    }

    /**
     * 等待所有协程完成
     */
    public function wait(): void
    {
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
     * 获取输出器实例
     *
     * @return \Kode\Console\Output
     */
    private static function getOutput(): \Kode\Console\Output
    {
        if (self::$output === null) {
            self::$output = new \Kode\Console\Output();
        }
        return self::$output;
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
}
