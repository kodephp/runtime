<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Console\Input;
use Kode\Console\Output;
use Kode\Runtime\ChannelInterface;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use Kode\Runtime\RuntimeCommand;
use PHPUnit\Framework\TestCase;

/**
 * 验证 RuntimeCommand 在最新 kode/console (^4.0) 下的集成行为
 *
 * RuntimeCommand 的辅助方法（info/async/getRuntimeName/...）均为 protected，
 * 仅供命令子类在 fire() 内部使用，因此本测试通过子类在 fire() 中调用它们，
 * 再由测试读取子类上记录的观测结果来进行断言。
 */
final class RuntimeCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Runtime::reset();
    }

    public function testCommandExtendsConsoleCommandAndFires(): void
    {
        $out = new Output(fopen('php://memory', 'r+'));

        $cmd = new class ('demo', '演示命令') extends RuntimeCommand {
            public bool $fired = false;

            public function fire(Input $in, Output $out): int
            {
                $this->fired = true;
                $this->info('fired-ok');
                return 0;
            }
        };
        $cmd->setOutput($out);

        $code = $cmd->fire(new Input(['demo']), $out);

        self::assertTrue($cmd->fired);
        self::assertSame(0, $code);
    }

    public function testOutputHelpersRenderToStream(): void
    {
        $stream = fopen('php://memory', 'r+');
        $err = fopen('php://memory', 'r+');
        $out = new Output($stream, $err);

        $cmd = new class ('demo') extends RuntimeCommand {
            public function fire(Input $in, Output $out): int
            {
                $this->info('i');
                $this->warn('w');
                $this->error('e');
                $this->success('s');
                $this->line('l');
                $this->table(['A', 'B'], [['1', '2']]);
                $this->progress(1, 2);
                return 0;
            }
        };
        $cmd->setOutput($out);

        $cmd->fire(new Input(['demo']), $out);

        rewind($stream);
        $written = (string) stream_get_contents($stream);
        rewind($err);
        $writtenErr = (string) stream_get_contents($err);

        // info/success/line/table/progress 写入标准输出，warn/error 写入标准错误
        self::assertStringContainsString('i', $written);
        self::assertStringContainsString('s', $written);
        self::assertStringContainsString('l', $written);
        self::assertStringContainsString('A', $written);
        self::assertStringContainsString('1', $written);
        self::assertStringContainsString('w', $writtenErr);
        self::assertStringContainsString('e', $writtenErr);
    }

    public function testAsyncInsideCommandUsesRuntime(): void
    {
        Runtime::reset();

        $cmd = new class ('demo') extends RuntimeCommand {
            public string $order = '';

            public function fire(Input $in, Output $out): int
            {
                $this->async(static function (): void {
                    usleep(10_000);
                });
                $this->async(static function (): void {
                    usleep(10_000);
                });
                $this->wait();
                $this->order = 'done';
                return 0;
            }
        };
        $cmd->setOutput(new Output(fopen('php://memory', 'r+')));

        $code = $cmd->fire(new Input(['demo']), $cmd->getOutput());

        self::assertSame(0, $code);
        self::assertSame('done', $cmd->order);
    }

    public function testRuntimeIntrospection(): void
    {
        $cmd = new class ('demo') extends RuntimeCommand {
            public string $observedName = '';
            public ?RuntimeEnvironment $observedEnv = null;
            public ?bool $observedConcurrent = null;
            public ?bool $observedIsConsole = null;

            public function fire(Input $in, Output $out): int
            {
                $this->observedName = $this->getRuntimeName();
                $this->observedEnv = $this->getRuntimeEnvironment();
                $this->observedConcurrent = $this->supportsConcurrency();
                $this->observedIsConsole = $this->isConsoleRuntime();
                return 0;
            }
        };
        $cmd->setOutput(new Output(fopen('php://memory', 'r+')));

        $cmd->fire(new Input(['demo']), $cmd->getOutput());

        self::assertNotEmpty($cmd->observedName);
        self::assertInstanceOf(RuntimeEnvironment::class, $cmd->observedEnv);
        self::assertIsBool($cmd->observedConcurrent);
        self::assertIsBool($cmd->observedIsConsole);
    }

    public function testCreateChannel(): void
    {
        $cmd = new class ('demo') extends RuntimeCommand {
            public ?ChannelInterface $chan = null;

            public function fire(Input $in, Output $out): int
            {
                $this->chan = $this->createChannel(2);
                return 0;
            }
        };
        $cmd->setOutput(new Output(fopen('php://memory', 'r+')));

        $cmd->fire(new Input(['demo']), $cmd->getOutput());

        self::assertInstanceOf(ChannelInterface::class, $cmd->chan);
    }
}
