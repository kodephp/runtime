<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Exception\UnsupportedOperationException;
use Kode\Runtime\RuntimeAdapterFactory;
use Kode\Runtime\RuntimeEnvironment;
use Kode\Runtime\ThreadRuntime;
use PHPUnit\Framework\TestCase;

/**
 * ThreadRuntime 适配器测试（基于 ext-parallel）
 */
final class ThreadRuntimeTest extends TestCase
{
    /**
     * 测试线程运行时创建
     */
    public function testThreadRuntimeCreation(): void
    {
        if (!RuntimeEnvironment::Thread->isAvailable()) {
            $this->markTestSkipped('parallel 扩展不可用');
        }

        $runtime = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_THREAD);

        $this->assertInstanceOf(ThreadRuntime::class, $runtime);
        $this->assertEquals('THREAD', $runtime->getName());
    }

    /**
     * 测试异步执行
     */
    public function testAsyncExecution(): void
    {
        if (!RuntimeEnvironment::Thread->isAvailable()) {
            $this->markTestSkipped('parallel 扩展不可用');
        }

        $runtime = new ThreadRuntime();
        $future = $runtime->async(static fn (): string => 'done');

        $this->assertIsObject($future);
        $runtime->wait();
    }

    /**
     * 测试并发执行并收集结果
     */
    public function testParallelCollectsResults(): void
    {
        if (!RuntimeEnvironment::Thread->isAvailable()) {
            $this->markTestSkipped('parallel 扩展不可用');
        }

        $results = (new ThreadRuntime())->parallel([
            'a' => static fn (): int => 1,
            'b' => static fn (): int => 2,
        ]);

        $this->assertSame(['a' => 1, 'b' => 2], $results);
    }

    /**
     * 测试扩展缺失时抛出明确异常
     */
    public function testThrowsWhenExtensionMissing(): void
    {
        if (RuntimeEnvironment::Thread->isAvailable()) {
            $this->markTestSkipped('parallel 扩展已安装');
        }

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('parallel 扩展不可用');

        (new ThreadRuntime())->async(static fn (): int => 1);
    }

    /**
     * 测试通道创建
     */
    public function testChannelCreation(): void
    {
        $channel = (new ThreadRuntime())->createChannel(1);

        $this->assertEquals(1, $channel->getCapacity());
    }

    /**
     * 测试休眠功能
     */
    public function testSleep(): void
    {
        $runtime = new ThreadRuntime();
        $start = microtime(true);
        $runtime->sleep(0.001);

        $this->assertGreaterThanOrEqual(0.001, microtime(true) - $start);
    }
}
