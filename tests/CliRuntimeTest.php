<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\CliRuntime;
use PHPUnit\Framework\TestCase;

/**
 * CliRuntime 适配器测试
 */
final class CliRuntimeTest extends TestCase
{
    private CliRuntime $runtime;

    protected function setUp(): void
    {
        $this->runtime = new CliRuntime();
    }

    /**
     * 测试获取运行时名称
     */
    public function testGetName(): void
    {
        $this->assertEquals('CLI', $this->runtime->getName());
    }

    /**
     * 测试异步执行
     */
    public function testAsync(): void
    {
        $result = $this->runtime->async(function () {
            return 'async_result';
        });

        $this->assertEquals('async_result', $result);
    }

    /**
     * 测试休眠
     */
    public function testSleep(): void
    {
        $start = microtime(true);
        $this->runtime->sleep(0.01);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.01, $elapsed);
        $this->assertLessThan(0.05, $elapsed);
    }

    /**
     * 测试创建通道
     */
    public function testCreateChannel(): void
    {
        $channel = $this->runtime->createChannel(5);

        $this->assertNotNull($channel);
        $this->assertEquals(5, $channel->getCapacity());
    }

    /**
     * 测试 defer 回调
     */
    public function testDefer(): void
    {
        $called = false;

        $this->runtime->defer(function () use (&$called) {
            $called = true;
        });

        $this->runtime->async(function () {
        });

        $this->assertTrue($called);
    }

    /**
     * 测试 wait（CLI 模式下为空操作）
     */
    public function testWait(): void
    {
        $this->runtime->wait();
        $this->assertTrue(true);
    }

    /**
     * 测试微秒级休眠精度
     */
    public function testMicrosecondSleep(): void
    {
        $start = microtime(true);
        $this->runtime->sleep(0.001);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.001, $elapsed);
    }
}
