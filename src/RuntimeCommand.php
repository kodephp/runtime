<?php

declare(strict_types=1);

namespace Kode\Runtime;

use Kode\Console\Command;
use Kode\Console\Input;
use Kode\Console\Output;

/**
 * 运行时命令基类
 *
 * 继承自 Kode\Console\Command，集成了 kode/runtime 运行时支持
 * 提供异步任务执行、协程支持等运行时特性
 */
abstract class RuntimeCommand extends Command
{
    /**
     * 异步任务通道
     */
    private ?ChannelInterface $channel = null;

    /**
     * 输出器实例
     */
    private ?Output $output = null;

    /**
     * 构造函数
     *
     * @param string $name 命令名称
     * @param string $desc 命令描述
     * @param string $usage 用法说明
     */
    public function __construct(
        string $name = '',
        string $desc = '',
        string $usage = ''
    ) {
        parent::__construct($name, $desc, $usage);
    }

    /**
     * 执行异步任务
     *
     * @param callable $callback 异步回调函数
     * @return mixed 异步任务结果
     */
    protected function async(callable $callback): mixed
    {
        return Runtime::async($callback);
    }

    /**
     * 休眠指定秒数
     *
     * @param float $seconds 休眠秒数
     */
    protected function sleep(float $seconds): void
    {
        Runtime::sleep($seconds);
    }

    /**
     * 注册清理回调
     *
     * @param callable $callback 清理函数
     */
    protected function defer(callable $callback): void
    {
        Runtime::defer($callback);
    }

    /**
     * 等待所有异步任务完成
     */
    protected function wait(): void
    {
        Runtime::wait();
    }

    /**
     * 创建通道
     *
     * @param int $capacity 通道容量
     * @return ChannelInterface 通道实例
     */
    protected function createChannel(int $capacity = 0): ChannelInterface
    {
        $this->channel = Runtime::createChannel($capacity);
        return $this->channel;
    }

    /**
     * 获取通道
     *
     * @return ChannelInterface|null 通道实例
     */
    protected function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    /**
     * 获取当前运行时名称
     *
     * @return string 运行时名称
     */
    protected function getRuntimeName(): string
    {
        return Runtime::getName();
    }

    /**
     * 检查是否在 Console 环境
     *
     * @return bool 是返回 true
     */
    protected function isConsoleRuntime(): bool
    {
        return $this->getRuntimeName() === 'CONSOLE';
    }

    /**
     * 获取输出器
     *
     * @return Output 输出器实例
     */
    protected function getOutput(): Output
    {
        if ($this->output === null) {
            $this->output = new Output();
        }
        return $this->output;
    }

    /**
     * 设置输出器
     *
     * @param Output $output 输出器实例
     */
    protected function setOutput(Output $output): void
    {
        $this->output = $output;
    }

    /**
     * 输出信息
     *
     * @param string $message 信息内容
     */
    protected function info(string $message): void
    {
        $this->getOutput()->info($message);
    }

    /**
     * 输出警告
     *
     * @param string $message 警告内容
     */
    protected function warn(string $message): void
    {
        $this->getOutput()->warn($message);
    }

    /**
     * 输出错误
     *
     * @param string $message 错误内容
     */
    protected function error(string $message): void
    {
        $this->getOutput()->error($message);
    }

    /**
     * 输出成功
     *
     * @param string $message 成功内容
     */
    protected function success(string $message): void
    {
        $this->getOutput()->success($message);
    }

    /**
     * 输出普通文本
     *
     * @param string $text 文本内容
     * @param string $color 颜色
     */
    protected function line(string $text, string $color = ''): void
    {
        $this->getOutput()->line($text, $color);
    }

    /**
     * 输出表格
     *
     * @param array $headers 表头
     * @param array $rows 表格行
     */
    protected function table(array $headers, array $rows): void
    {
        $this->getOutput()->table($headers, $rows);
    }

    /**
     * 输出进度条
     *
     * @param int $current 当前进度
     * @param int $total 总进度
     * @param int $width 进度条宽度
     */
    protected function progress(int $current, int $total, int $width = 50): void
    {
        $this->getOutput()->progress($current, $total, $width);
    }
}
