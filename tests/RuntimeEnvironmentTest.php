<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Exception\UnsupportedOperationException;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * RuntimeEnvironment 枚举测试
 */
final class RuntimeEnvironmentTest extends TestCase
{
    /**
     * 测试显示名称
     */
    public function testLabel(): void
    {
        $this->assertEquals('SWOOLE', RuntimeEnvironment::Swoole->label());
        $this->assertEquals('CLI', RuntimeEnvironment::Cli->label());
    }

    /**
     * 测试并发能力标记
     */
    public function testSupportsConcurrency(): void
    {
        $this->assertTrue(RuntimeEnvironment::Fiber->supportsConcurrency());
        $this->assertTrue(RuntimeEnvironment::Swoole->supportsConcurrency());
        $this->assertTrue(RuntimeEnvironment::Process->supportsConcurrency());
        $this->assertFalse(RuntimeEnvironment::Cli->supportsConcurrency());
        $this->assertFalse(RuntimeEnvironment::Console->supportsConcurrency());
    }

    /**
     * 测试可用性探测
     */
    public function testIsAvailable(): void
    {
        $this->assertTrue(RuntimeEnvironment::Cli->isAvailable());
        $this->assertTrue(RuntimeEnvironment::Fiber->isAvailable());
        $this->assertEquals(extension_loaded('swoole'), RuntimeEnvironment::Swoole->isAvailable());
        $this->assertEquals(function_exists('pcntl_fork'), RuntimeEnvironment::Process->isAvailable());
    }

    /**
     * 测试环境解析
     */
    public function testResolve(): void
    {
        $this->assertSame(RuntimeEnvironment::Fiber, RuntimeEnvironment::resolve('FIBER'));
        $this->assertSame(RuntimeEnvironment::Fiber, RuntimeEnvironment::resolve(' fiber '));
        $this->assertSame(RuntimeEnvironment::Cli, RuntimeEnvironment::resolve(RuntimeEnvironment::Cli));
    }

    /**
     * 测试解析非法名称
     */
    public function testResolveInvalidName(): void
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('不支持的运行时环境: warp');

        RuntimeEnvironment::resolve('warp');
    }

    /**
     * 测试自动探测结果始终可用且具备并发能力（除非降级到 CLI）
     */
    public function testDetect(): void
    {
        $detected = RuntimeEnvironment::detect();

        $this->assertTrue($detected->isAvailable());
        $this->assertContains($detected, [
            RuntimeEnvironment::Swoole,
            RuntimeEnvironment::Swow,
            RuntimeEnvironment::Fiber,
            RuntimeEnvironment::Cli,
        ]);
    }

    /**
     * 测试可用环境列表
     */
    public function testAvailable(): void
    {
        $available = RuntimeEnvironment::available();

        $this->assertContains(RuntimeEnvironment::Cli, $available);
        $this->assertSame(
            extension_loaded('parallel'),
            in_array(RuntimeEnvironment::Thread, $available, true)
        );
    }

    /**
     * 测试不可用提示信息
     */
    public function testUnavailableMessage(): void
    {
        $this->assertStringContainsString('Swoole', RuntimeEnvironment::Swoole->unavailableMessage());
        $this->assertStringContainsString('parallel', RuntimeEnvironment::Thread->unavailableMessage());
    }
}
