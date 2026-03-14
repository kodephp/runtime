<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Runtime;
use PHPUnit\Framework\TestCase;

/**
 * Runtime 门面测试
 */
final class RuntimeTest extends TestCase
{
    /**
     * 测试环境检测
     */
    public function testGetEnvironment(): void
    {
        $environment = Runtime::getName();
        $this->assertIsString($environment);
        $this->assertContains($environment, ['SWOOLE', 'SWOW', 'FIBER', 'PROCESS', 'THREAD', 'CLI']);
    }

    /**
     * 测试通道创建
     */
    public function testCreateChannel(): void
    {
        $channel = Runtime::createChannel(1);
        $this->assertNotNull($channel);
        $this->assertInstanceOf(\Kode\Runtime\ChannelInterface::class, $channel);
    }

    /**
     * 测试设置环境
     */
    public function testSetEnvironment(): void
    {
        Runtime::setEnvironment('cli');
        $this->assertEquals('CLI', Runtime::getName());

        Runtime::reset();
    }
}
