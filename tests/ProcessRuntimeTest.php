<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ProcessRuntime;
use Kode\Runtime\RuntimeAdapterFactory;
use PHPUnit\Framework\TestCase;

/**
 * ProcessRuntime 适配器测试
 */
final class ProcessRuntimeTest extends TestCase
{
    /**
     * 测试进程运行时创建
     */
    public function testProcessRuntimeCreation(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL 扩展不可用');
        }

        $runtime = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_PROCESS);
        $this->assertInstanceOf(ProcessRuntime::class, $runtime);
        $this->assertEquals('PROCESS', $runtime->getName());
    }

    /**
     * 测试异步执行
     */
    public function testAsyncExecution(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL 扩展不可用');
        }

        $runtime = new ProcessRuntime();

        $pid = $runtime->async(function () {
        });

        $this->assertIsInt($pid);
        $this->assertGreaterThan(0, $pid);

        pcntl_waitpid($pid, $status);
    }

    /**
     * 测试通道创建
     */
    public function testChannelCreation(): void
    {
        $runtime = new ProcessRuntime();
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
        $runtime = new ProcessRuntime();

        $runtime->sleep(0.001);
        $this->assertTrue(true);
    }
}
