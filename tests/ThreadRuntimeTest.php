<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ThreadRuntime;
use Kode\Runtime\RuntimeAdapterFactory;
use PHPUnit\Framework\TestCase;

/**
 * ThreadRuntime 适配器测试
 */
final class ThreadRuntimeTest extends TestCase
{
    /**
     * 测试线程运行时创建
     */
    public function testThreadRuntimeCreation(): void
    {
        if (!extension_loaded('pthreads')) {
            $this->markTestSkipped('pthreads 扩展不可用');
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
        if (!extension_loaded('pthreads')) {
            $this->markTestSkipped('pthreads 扩展不可用');
        }

        $runtime = new ThreadRuntime();

        $thread = $runtime->async(function () {
        });

        $this->assertNotNull($thread);
        $this->assertInstanceOf(\Thread::class, $thread);

        $thread->join();
    }

    /**
     * 测试通道创建
     */
    public function testChannelCreation(): void
    {
        $runtime = new ThreadRuntime();
        $channel = $runtime->createChannel(1);

        $this->assertNotNull($channel);
        $this->assertTrue(method_exists($channel, 'push'));
        $this->assertTrue(method_exists($channel, 'pop'));
    }

    /**
     * 测试休眠功能
     */
    public function testSleep(): void
    {
        $runtime = new ThreadRuntime();

        $runtime->sleep(0.001);
        $this->assertTrue(true);
    }
}
