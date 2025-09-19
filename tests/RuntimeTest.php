<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for Runtime facade
 */
class RuntimeTest extends TestCase
{
    /**
     * Test that we can detect the current environment
     */
    public function testGetEnvironment(): void
    {
        $environment = Runtime::getName();
        $this->assertIsString($environment);
        $this->assertContains($environment, ['SWOOLE', 'SWOW', 'FIBER', 'PROCESS', 'THREAD', 'CLI']);
    }

    /**
     * Test that we can create a channel
     */
    public function testCreateChannel(): void
    {
        $channel = Runtime::createChannel(1);
        $this->assertNotNull($channel);
        $this->assertInstanceOf(\Kode\Runtime\ChannelInterface::class, $channel);
    }

    /**
     * Test that we can set a specific environment
     */
    public function testSetEnvironment(): void
    {
        // Save the original environment
        $originalEnvironment = Runtime::getName();

        // Try to set to CLI environment (should always work)
        Runtime::setEnvironment('cli');
        $this->assertEquals('CLI', Runtime::getName());

        // Reset to original environment
        Runtime::reset();
    }
}
