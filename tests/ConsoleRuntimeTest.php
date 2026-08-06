<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Console\Output;
use Kode\Runtime\ConsoleRuntime;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use Kode\Runtime\RuntimeInterface;
use PHPUnit\Framework\TestCase;

/**
 * 验证 ConsoleRuntime 装饰器在最新 kode/console (^4.0) 下的集成行为
 */
final class ConsoleRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
        ConsoleRuntime::setOutput(new Output());
    }

    protected function tearDown(): void
    {
        Runtime::reset();
        ConsoleRuntime::setOutput(new Output());
    }

    public function testEnvironmentIsConsole(): void
    {
        $runtime = new ConsoleRuntime();

        self::assertSame(RuntimeEnvironment::Console, $runtime->environment());
        self::assertSame('CONSOLE', $runtime->getName());
    }

    public function testDecoratesConcurrentRuntime(): void
    {
        $runtime = new ConsoleRuntime();

        self::assertInstanceOf(RuntimeInterface::class, $runtime->innerRuntime());
        // 装饰器应暴露底层运行时的并发能力，而非自行降级
        self::assertSame(
            $runtime->innerRuntime()->supportsConcurrency(),
            $runtime->supportsConcurrency()
        );
    }

    public function testDelegatesAsyncAndChannelToInner(): void
    {
        $runtime = new ConsoleRuntime();
        $channel = $runtime->createChannel(1);

        self::assertInstanceOf(\Kode\Runtime\ChannelInterface::class, $channel);

        $captured = null;
        $runtime->async(static function () use (&$captured): void {
            $captured = 'ran';
        });
        $runtime->wait();

        self::assertSame('ran', $captured);
    }

    public function testOutputHelpersDoNotThrow(): void
    {
        $out = fopen('php://memory', 'r+');
        $err = fopen('php://memory', 'r+');
        ConsoleRuntime::setOutput(new Output($out, $err));

        ConsoleRuntime::info('info message');
        ConsoleRuntime::warn('warn message');
        ConsoleRuntime::error('error message');
        ConsoleRuntime::success('success message');
        ConsoleRuntime::line('plain line');

        rewind($out);
        $writtenOut = (string) stream_get_contents($out);
        rewind($err);
        $writtenErr = (string) stream_get_contents($err);

        // info/success/line 写入标准输出，warn/error 写入标准错误
        self::assertStringContainsString('info message', $writtenOut);
        self::assertStringContainsString('success message', $writtenOut);
        self::assertStringContainsString('plain line', $writtenOut);
        self::assertStringContainsString('warn message', $writtenErr);
        self::assertStringContainsString('error message', $writtenErr);
    }

    public function testFactoryCreatesConsoleRuntimeWhenRequested(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Console);

        self::assertInstanceOf(ConsoleRuntime::class, Runtime::adapter());
    }
}
