<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\CliRuntime;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * CliRuntime 适配器测试
 */
final class CliRuntimeTest extends TestCase
{
    private CliRuntime $runtime;

    protected function setUp(): void
    {
        $this->runtime = new CliRuntime();
    }

    /**
     * 测试获取运行时名称与环境
     */
    public function testGetName(): void
    {
        $this->assertEquals('CLI', $this->runtime->getName());
        $this->assertSame(RuntimeEnvironment::Cli, $this->runtime->environment());
        $this->assertFalse($this->runtime->supportsConcurrency());
    }

    /**
     * 测试异步执行（CLI 下为同步）
     */
    public function testAsync(): void
    {
        $result = $this->runtime->async(static fn (): string => 'async_result');

        $this->assertEquals('async_result', $result);
    }

    /**
     * 测试休眠
     */
    public function testSleep(): void
    {
        $start = microtime(true);
        $this->runtime->sleep(0.01);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.01, $elapsed);
        $this->assertLessThan(0.1, $elapsed);
    }

    /**
     * 测试创建通道
     */
    public function testCreateChannel(): void
    {
        $channel = $this->runtime->createChannel(5);

        $this->assertNotNull($channel);
        $this->assertEquals(5, $channel->getCapacity());
    }

    /**
     * 测试 defer 在所属作用域结束时执行
     */
    public function testDeferRunsWhenScopeEnds(): void
    {
        $called = false;

        $this->runtime->async(function () use (&$called): void {
            $this->runtime->defer(function () use (&$called): void {
                $called = true;
            });

            $this->assertFalse($called, 'defer 不应在作用域结束前执行');
        });

        $this->assertTrue($called);
    }

    /**
     * 测试 defer 按后进先出顺序执行
     */
    public function testDeferRunsInLifoOrder(): void
    {
        $order = [];

        $this->runtime->async(function () use (&$order): void {
            $this->runtime->defer(function () use (&$order): void {
                $order[] = 'first';
            });
            $this->runtime->defer(function () use (&$order): void {
                $order[] = 'second';
            });
            $order[] = 'body';
        });

        $this->assertSame(['body', 'second', 'first'], $order);
    }

    /**
     * 测试根作用域的 defer 在 wait() 时执行
     */
    public function testRootDeferRunsOnWait(): void
    {
        $called = false;

        $this->runtime->defer(function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
        $this->runtime->wait();
        $this->assertTrue($called);
    }

    /**
     * 测试 defer 作用域互不串扰
     */
    public function testDeferScopesAreIsolated(): void
    {
        $order = [];

        $this->runtime->async(function () use (&$order): void {
            $this->runtime->defer(function () use (&$order): void {
                $order[] = 'outer';
            });

            $this->runtime->async(function () use (&$order): void {
                $this->runtime->defer(function () use (&$order): void {
                    $order[] = 'inner';
                });
            });

            $order[] = 'after-inner';
        });

        $this->assertSame(['inner', 'after-inner', 'outer'], $order);
    }

    /**
     * 测试 defer 内部异常不影响主流程
     */
    public function testDeferExceptionIsSwallowed(): void
    {
        $result = $this->runtime->async(function (): string {
            $this->runtime->defer(static function (): void {
                throw new \RuntimeException('清理失败');
            });

            return 'ok';
        });

        $this->assertEquals('ok', $result);
    }

    /**
     * 测试 parallel 在 CLI 下顺序执行并保留键
     */
    public function testParallelRunsSequentially(): void
    {
        $results = $this->runtime->parallel([
            'a' => static fn (): int => 1,
            'b' => static fn (): int => 2,
        ]);

        $this->assertSame(['a' => 1, 'b' => 2], $results);
    }

    /**
     * 测试 run 入口函数
     */
    public function testRun(): void
    {
        $this->assertEquals('done', $this->runtime->run(static fn (): string => 'done'));
    }

    /**
     * 测试微秒级休眠精度
     */
    public function testMicrosecondSleep(): void
    {
        $start = microtime(true);
        $this->runtime->sleep(0.001);

        $this->assertGreaterThanOrEqual(0.001, microtime(true) - $start);
    }
}
