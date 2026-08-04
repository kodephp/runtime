<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ChannelInterface;
use Kode\Runtime\FiberScheduler;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

use function Kode\Runtime\channel;
use function Kode\Runtime\defer;
use function Kode\Runtime\delay;
use function Kode\Runtime\go;
use function Kode\Runtime\parallel;
use function Kode\Runtime\run;
use function Kode\Runtime\wait;

/**
 * 命名空间函数助手测试
 */
final class FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        FiberScheduler::resetInstance();
        Runtime::setEnvironment(RuntimeEnvironment::Fiber);
    }

    protected function tearDown(): void
    {
        Runtime::reset();
        FiberScheduler::resetInstance();
    }

    /**
     * 测试 go() 启动协程并由 wait() 收敛
     */
    public function testGoAndWait(): void
    {
        $done = false;

        go(function () use (&$done): void {
            delay(0.01);
            $done = true;
        });

        $this->assertFalse($done);
        wait();
        $this->assertTrue($done);
    }

    /**
     * 测试 parallel() 并发执行
     */
    public function testParallel(): void
    {
        $results = parallel([
            'a' => static fn (): string => 'A',
            'b' => static fn (): string => 'B',
        ]);

        $this->assertSame(['a' => 'A', 'b' => 'B'], $results);
    }

    /**
     * 测试 run() 入口函数
     */
    public function testRun(): void
    {
        $this->assertEquals(7, run(static fn (): int => 7));
    }

    /**
     * 测试 channel() 与 defer()
     */
    public function testChannelAndDefer(): void
    {
        $channel = channel(2);
        $this->assertInstanceOf(ChannelInterface::class, $channel);
        $this->assertEquals(2, $channel->getCapacity());

        $cleaned = false;
        go(function () use (&$cleaned, $channel): void {
            defer(function () use (&$cleaned): void {
                $cleaned = true;
            });
            $channel->push('value');
        });

        wait();

        $this->assertTrue($cleaned);
        $this->assertEquals('value', $channel->pop());
    }
}
