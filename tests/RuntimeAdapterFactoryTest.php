<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\CliRuntime;
use Kode\Runtime\FiberRuntime;
use Kode\Runtime\RuntimeAdapterFactory;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * RuntimeAdapterFactory 工厂测试
 */
final class RuntimeAdapterFactoryTest extends TestCase
{
    /**
     * 测试创建 CLI 适配器
     */
    public function testCreateCliAdapter(): void
    {
        $adapter = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_CLI);

        $this->assertInstanceOf(CliRuntime::class, $adapter);
        $this->assertEquals('CLI', $adapter->getName());
    }

    /**
     * 测试强制创建 Fiber 适配器
     */
    public function testCreateFiberAdapter(): void
    {
        $adapter = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_FIBER);

        $this->assertInstanceOf(FiberRuntime::class, $adapter);
        $this->assertEquals('FIBER', $adapter->getName());
    }

    /**
     * 测试使用枚举创建适配器
     */
    public function testCreateWithEnum(): void
    {
        $adapter = RuntimeAdapterFactory::createForEnvironment(RuntimeEnvironment::Cli);

        $this->assertSame(RuntimeEnvironment::Cli, $adapter->environment());
    }

    /**
     * 测试自动探测优先返回并发运行时
     */
    public function testCreateConcurrentPrefersCoroutine(): void
    {
        $adapter = RuntimeAdapterFactory::createConcurrent();

        $this->assertNotSame(RuntimeEnvironment::Console, $adapter->environment());
        $this->assertTrue($adapter->environment()->isAvailable());
    }

    /**
     * 测试环境名大小写不敏感
     */
    public function testEnvironmentNameIsCaseInsensitive(): void
    {
        $adapter = RuntimeAdapterFactory::createForEnvironment(' CLI ');

        $this->assertEquals('CLI', $adapter->getName());
    }

    /**
     * 测试可用性检测
     */
    public function testAvailabilityChecks(): void
    {
        $this->assertIsBool(RuntimeAdapterFactory::isSwooleAvailable());
        $this->assertIsBool(RuntimeAdapterFactory::isSwowAvailable());
        $this->assertIsBool(RuntimeAdapterFactory::isConsoleAvailable());
        $this->assertTrue(RuntimeAdapterFactory::isFiberSupported());
        $this->assertTrue(RuntimeAdapterFactory::isAvailable('cli'));
    }

    /**
     * 测试可用环境列表
     */
    public function testAvailableEnvironments(): void
    {
        $environments = RuntimeAdapterFactory::availableEnvironments();

        $this->assertNotEmpty($environments);
        $this->assertContains(RuntimeEnvironment::Cli, $environments);
        $this->assertContains(RuntimeEnvironment::Fiber, $environments);
    }

    /**
     * 测试环境常量与枚举保持一致
     */
    public function testEnvironmentConstants(): void
    {
        $this->assertEquals(RuntimeEnvironment::Swoole->value, RuntimeAdapterFactory::ENV_SWOOLE);
        $this->assertEquals(RuntimeEnvironment::Swow->value, RuntimeAdapterFactory::ENV_SWOW);
        $this->assertEquals(RuntimeEnvironment::Fiber->value, RuntimeAdapterFactory::ENV_FIBER);
        $this->assertEquals(RuntimeEnvironment::Process->value, RuntimeAdapterFactory::ENV_PROCESS);
        $this->assertEquals(RuntimeEnvironment::Thread->value, RuntimeAdapterFactory::ENV_THREAD);
        $this->assertEquals(RuntimeEnvironment::Cli->value, RuntimeAdapterFactory::ENV_CLI);
        $this->assertEquals(RuntimeEnvironment::Console->value, RuntimeAdapterFactory::ENV_CONSOLE);
    }

    /**
     * 测试不支持的环境
     */
    public function testUnsupportedEnvironment(): void
    {
        $this->expectException(\Kode\Runtime\Exception\UnsupportedOperationException::class);

        RuntimeAdapterFactory::createForEnvironment('unsupported');
    }

    /**
     * 测试环境合法但扩展缺失时抛出明确异常
     */
    public function testUnavailableEnvironmentThrows(): void
    {
        if (RuntimeEnvironment::Swoole->isAvailable()) {
            $this->markTestSkipped('Swoole 扩展已安装');
        }

        $this->expectException(\Kode\Runtime\Exception\UnsupportedOperationException::class);
        $this->expectExceptionMessage('Swoole 扩展不可用');

        RuntimeAdapterFactory::createForEnvironment(RuntimeEnvironment::Swoole);
    }
}
