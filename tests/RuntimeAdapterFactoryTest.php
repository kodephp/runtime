<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\RuntimeAdapterFactory;
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
        $this->assertEquals('CLI', $adapter->getName());
    }

    /**
     * 测试强制创建 Fiber 适配器
     */
    public function testCreateFiberAdapter(): void
    {
        $adapter = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_FIBER);
        $this->assertEquals('FIBER', $adapter->getName());
    }

    /**
     * 测试检测 Swoole 可用性
     */
    public function testIsSwooleAvailable(): void
    {
        $result = RuntimeAdapterFactory::isSwooleAvailable();
        $this->assertIsBool($result);
    }

    /**
     * 测试检测 Swow 可用性
     */
    public function testIsSwowAvailable(): void
    {
        $result = RuntimeAdapterFactory::isSwowAvailable();
        $this->assertIsBool($result);
    }

    /**
     * 测试检测 Fiber 支持
     */
    public function testIsFiberSupported(): void
    {
        $result = RuntimeAdapterFactory::isFiberSupported();
        $this->assertTrue($result);
    }

    /**
     * 测试环境常量
     */
    public function testEnvironmentConstants(): void
    {
        $this->assertEquals('swoole', RuntimeAdapterFactory::ENV_SWOOLE);
        $this->assertEquals('swow', RuntimeAdapterFactory::ENV_SWOW);
        $this->assertEquals('fiber', RuntimeAdapterFactory::ENV_FIBER);
        $this->assertEquals('process', RuntimeAdapterFactory::ENV_PROCESS);
        $this->assertEquals('thread', RuntimeAdapterFactory::ENV_THREAD);
        $this->assertEquals('cli', RuntimeAdapterFactory::ENV_CLI);
        $this->assertEquals('console', RuntimeAdapterFactory::ENV_CONSOLE);
    }

    /**
     * 测试不支持的环境
     */
    public function testUnsupportedEnvironment(): void
    {
        $this->expectException(\Kode\Runtime\Exception\UnsupportedOperationException::class);
        RuntimeAdapterFactory::createForEnvironment('unsupported');
    }
}
