<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ChannelInterface;
use Kode\Runtime\CliChannel;
use PHPUnit\Framework\TestCase;

/**
 * Channel 通道测试
 */
final class ChannelTest extends TestCase
{
    /**
     * 测试创建通道
     */
    public function testCreateChannel(): void
    {
        $channel = new CliChannel(1);
        $this->assertInstanceOf(ChannelInterface::class, $channel);
    }

    /**
     * 测试无容量限制通道
     */
    public function testUnlimitedChannel(): void
    {
        $channel = new CliChannel(0);

        $this->assertEquals(0, $channel->getCapacity());
        $this->assertFalse($channel->isFull());
    }

    /**
     * 测试负容量归一化为无限制
     */
    public function testNegativeCapacityIsNormalized(): void
    {
        $channel = new CliChannel(-5);

        $this->assertEquals(0, $channel->getCapacity());
        $this->assertFalse($channel->isFull());
    }

    /**
     * 测试通道 push 和 pop
     */
    public function testPushAndPop(): void
    {
        $channel = new CliChannel(1);

        $this->assertTrue($channel->push('test'));
        $this->assertEquals('test', $channel->pop());
    }

    /**
     * 测试通道容量限制
     */
    public function testCapacityLimit(): void
    {
        $channel = new CliChannel(1);

        $this->assertTrue($channel->push('first'));
        $this->assertFalse($channel->push('second'));
        $this->assertTrue($channel->isFull());
    }

    /**
     * 测试通道长度与空判断
     */
    public function testGetLengthAndIsEmpty(): void
    {
        $channel = new CliChannel(2);

        $this->assertEquals(0, $channel->getLength());
        $this->assertTrue($channel->isEmpty());

        $channel->push('a');
        $this->assertEquals(1, $channel->getLength());
        $this->assertFalse($channel->isEmpty());

        $channel->push('b');
        $this->assertEquals(2, $channel->getLength());
        $this->assertTrue($channel->isFull());
    }

    /**
     * 测试通道关闭
     */
    public function testClose(): void
    {
        $channel = new CliChannel(1);

        $channel->push('test');
        $channel->close();

        $this->assertTrue($channel->isClosed());
        $this->assertFalse($channel->push('after close'));
        $this->assertNull($channel->pop());
    }

    /**
     * 测试空通道 pop
     */
    public function testPopEmptyChannel(): void
    {
        $channel = new CliChannel(1);

        $this->assertNull($channel->pop());
    }

    /**
     * 测试零超时立即返回
     */
    public function testZeroTimeoutReturnsImmediately(): void
    {
        $channel = new CliChannel(1);

        $start = microtime(true);
        $this->assertNull($channel->pop(ChannelInterface::TIMEOUT_NONE));
        $this->assertLessThan(0.05, microtime(true) - $start);
        $this->assertTrue($channel->isTimeout());
    }

    /**
     * 测试超时标记在成功操作后被重置
     */
    public function testTimeoutFlagIsResetOnSuccess(): void
    {
        $channel = new CliChannel(1);

        $channel->pop(ChannelInterface::TIMEOUT_NONE);
        $this->assertTrue($channel->isTimeout());

        $channel->push('value');
        $this->assertFalse($channel->isTimeout());
        $this->assertEquals('value', $channel->pop());
        $this->assertFalse($channel->isTimeout());
    }

    /**
     * 测试同步环境下满通道的超时等待
     */
    public function testPushTimeoutOnFullChannel(): void
    {
        $channel = new CliChannel(1);
        $channel->push('first');

        $this->assertFalse($channel->push('second', 0.05));
        $this->assertTrue($channel->isTimeout());
    }

    /**
     * 测试多数据类型
     */
    public function testVariousDataTypes(): void
    {
        $channel = new CliChannel(10);

        $channel->push('string');
        $channel->push(123);
        $channel->push(['array']);
        $channel->push(['key' => 'value']);
        $channel->push(new \stdClass());

        $this->assertEquals('string', $channel->pop());
        $this->assertEquals(123, $channel->pop());
        $this->assertEquals(['array'], $channel->pop());
        $this->assertEquals(['key' => 'value'], $channel->pop());
        $this->assertInstanceOf(\stdClass::class, $channel->pop());
    }
}
