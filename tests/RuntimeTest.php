<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ChannelInterface;
use Kode\Runtime\CliRuntime;
use Kode\Runtime\FiberScheduler;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Runtime 门面测试
 */
final class RuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
        FiberScheduler::resetInstance();
    }

    protected function tearDown(): void
    {
        Runtime::reset();
        FiberScheduler::resetInstance();
    }

    /**
     * 测试环境检测
     */
    public function testGetEnvironment(): void
    {
        $environment = Runtime::getName();

        $this->assertIsString($environment);
        $this->assertContains($environment, ['SWOOLE', 'SWOW', 'FIBER', 'PROCESS', 'THREAD', 'CONSOLE', 'CLI']);
        $this->assertInstanceOf(RuntimeEnvironment::class, Runtime::environment());
    }

    /**
     * 测试自动探测不会因安装了 kode/console 而降级为同步
     */
    public function testAutoDetectPrefersConcurrentRuntime(): void
    {
        $this->assertNotSame(RuntimeEnvironment::Console, Runtime::environment());

        if (RuntimeEnvironment::Fiber->isAvailable()) {
            $this->assertTrue(Runtime::supportsConcurrency());
        }
    }

    /**
     * 测试通道创建
     */
    public function testCreateChannel(): void
    {
        $channel = Runtime::createChannel(1);

        $this->assertInstanceOf(ChannelInterface::class, $channel);
        $this->assertEquals(1, $channel->getCapacity());
    }

    /**
     * 测试设置环境
     */
    public function testSetEnvironment(): void
    {
        Runtime::setEnvironment('cli');
        $this->assertEquals('CLI', Runtime::getName());

        Runtime::setEnvironment(RuntimeEnvironment::Fiber);
        $this->assertEquals('FIBER', Runtime::getName());
    }

    /**
     * 测试注入自定义适配器
     */
    public function testSetAdapter(): void
    {
        $adapter = new CliRuntime();
        Runtime::setAdapter($adapter);

        $this->assertSame($adapter, Runtime::adapter());

        Runtime::setAdapter(null);
        $this->assertNotSame($adapter, Runtime::adapter());
    }

    /**
     * 测试非法环境名
     */
    public function testInvalidEnvironment(): void
    {
        $this->expectException(\Kode\Runtime\Exception\UnsupportedOperationException::class);

        Runtime::setEnvironment('quantum');
    }

    /**
     * 测试 run 与 parallel 门面方法
     */
    public function testRunAndParallel(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Fiber);

        $result = Runtime::run(static fn (): string => 'main-done');
        $this->assertEquals('main-done', $result);

        $values = Runtime::parallel([
            'one' => static fn (): int => 1,
            'two' => static fn (): int => 2,
        ]);
        $this->assertSame(['one' => 1, 'two' => 2], $values);
    }

    /**
     * 测试 fork 子进程
     */
    public function testFork(): void
    {
        if (!RuntimeEnvironment::Process->isAvailable()) {
            $this->markTestSkipped('PCNTL 扩展不可用');
        }

        $pid = Runtime::fork(static function (): void {
            usleep(1000);
        });

        $this->assertIsInt($pid);
        $this->assertGreaterThan(0, $pid);

        $status = 0;
        pcntl_waitpid($pid, $status);
        $this->assertSame(0, pcntl_wexitstatus($status));
    }
}
