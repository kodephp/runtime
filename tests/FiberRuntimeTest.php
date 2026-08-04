<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\FiberRuntime;
use Kode\Runtime\FiberScheduler;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * FiberRuntime 与协作式调度器测试
 */
final class FiberRuntimeTest extends TestCase
{
    private FiberRuntime $runtime;

    protected function setUp(): void
    {
        // 每个用例使用独立调度器，避免协程状态跨用例污染
        $this->runtime = new FiberRuntime(new FiberScheduler());
    }

    /**
     * 测试环境信息
     */
    public function testEnvironment(): void
    {
        $this->assertEquals('FIBER', $this->runtime->getName());
        $this->assertSame(RuntimeEnvironment::Fiber, $this->runtime->environment());
        $this->assertTrue($this->runtime->supportsConcurrency());
    }

    /**
     * 测试 async 返回 Fiber 且立即开始执行
     */
    public function testAsyncStartsImmediately(): void
    {
        $started = false;

        $fiber = $this->runtime->async(function () use (&$started): void {
            $started = true;
        });

        $this->assertInstanceOf(\Fiber::class, $fiber);
        $this->assertTrue($started);
        $this->assertTrue($fiber->isTerminated());
    }

    /**
     * 测试协程真正交替执行（sleep 会让出执行权）
     */
    public function testCoroutinesInterleave(): void
    {
        $order = [];

        $this->runtime->async(function () use (&$order): void {
            $order[] = 'a1';
            $this->runtime->sleep(0.03);
            $order[] = 'a2';
        });

        $this->runtime->async(function () use (&$order): void {
            $order[] = 'b1';
            $this->runtime->sleep(0.01);
            $order[] = 'b2';
        });

        $order[] = 'main';
        $this->runtime->wait();

        $this->assertSame(['a1', 'b1', 'main', 'b2', 'a2'], $order);
    }

    /**
     * 测试并发休眠总耗时接近最长任务而非累加
     */
    public function testConcurrentSleepDoesNotAccumulate(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 4; $i++) {
            $this->runtime->async(function (): void {
                $this->runtime->sleep(0.05);
            });
        }

        $this->runtime->wait();
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.05, $elapsed);
        $this->assertLessThan(0.2, $elapsed, '4 个协程串行执行会超过 0.2 秒');
    }

    /**
     * 测试 parallel 并发执行并保留键
     */
    public function testParallelPreservesKeys(): void
    {
        $start = microtime(true);

        $results = $this->runtime->parallel([
            'first' => function (): string {
                $this->runtime->sleep(0.03);
                return 'A';
            },
            'second' => function (): string {
                $this->runtime->sleep(0.03);
                return 'B';
            },
        ]);

        $this->assertSame(['first' => 'A', 'second' => 'B'], $results);
        $this->assertLessThan(0.1, microtime(true) - $start);
    }

    /**
     * 测试协程间通过通道通信
     */
    public function testChannelCommunicationBetweenFibers(): void
    {
        $channel = $this->runtime->createChannel(1);
        $received = [];

        $this->runtime->async(function () use ($channel): void {
            foreach (['m1', 'm2', 'm3'] as $message) {
                $channel->push($message);
                $this->runtime->sleep(0.005);
            }
            $channel->close();
        });

        $this->runtime->async(function () use ($channel, &$received): void {
            while (!$channel->isClosed() || !$channel->isEmpty()) {
                $value = $channel->pop(0.5);
                if ($value === null) {
                    break;
                }
                $received[] = $value;
            }
        });

        $this->runtime->wait();

        $this->assertSame(['m1', 'm2', 'm3'], $received);
    }

    /**
     * 测试 defer 跟随协程作用域且互不串扰
     */
    public function testDeferIsScopedPerFiber(): void
    {
        $log = [];

        $this->runtime->async(function () use (&$log): void {
            $this->runtime->defer(function () use (&$log): void {
                $log[] = 'a-defer';
            });
            $this->runtime->sleep(0.02);
            $log[] = 'a-body';
        });

        $this->runtime->async(function () use (&$log): void {
            $this->runtime->defer(function () use (&$log): void {
                $log[] = 'b-defer';
            });
            $log[] = 'b-body';
        });

        $this->runtime->wait();

        $this->assertSame(['b-body', 'b-defer', 'a-body', 'a-defer'], $log);
    }

    /**
     * 测试协程内未捕获异常在 wait() 时抛出
     */
    public function testUncaughtExceptionSurfacesOnWait(): void
    {
        $this->runtime->async(static function (): void {
            throw new \RuntimeException('协程异常');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('协程异常');

        $this->runtime->wait();
    }

    /**
     * 测试主流程 sleep 时会顺带驱动协程，避免协程被饿死
     */
    public function testMainSleepDrivesScheduler(): void
    {
        $done = false;

        $this->runtime->async(function () use (&$done): void {
            $this->runtime->sleep(0.01);
            $done = true;
        });

        $this->assertFalse($done);
        $this->runtime->sleep(0.05);

        $this->assertTrue($done);
    }

    /**
     * 测试调度器统计信息
     */
    public function testSchedulerCounters(): void
    {
        $scheduler = $this->runtime->scheduler();

        $this->assertSame(0, $scheduler->count());
        $this->assertFalse($scheduler->hasPendingWork());

        $this->runtime->async(function (): void {
            $this->runtime->sleep(0.01);
        });

        $this->assertSame(1, $scheduler->count());
        $this->assertTrue($scheduler->hasPendingWork());

        $this->runtime->wait();

        $this->assertSame(0, $scheduler->count());
        $this->assertFalse($scheduler->hasPendingWork());
    }
}
