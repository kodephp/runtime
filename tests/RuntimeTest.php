<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Kode\Runtime\Runtime;

class RuntimeTest extends TestCase
{
    public function testGetEnvironment()
    {
        // This will depend on the current environment
        $environment = Runtime::getEnvironment();
        $this->assertIsString($environment);
    }

    public function testCreateChannel()
    {
        $channel = Runtime::createChannel(10);
        $this->assertNotNull($channel);
        $this->assertInstanceOf(\Kode\Runtime\ChannelInterface::class, $channel);
    }
}
