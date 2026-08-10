<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Runtime;
use Kode\Runtime\SwooleChannel;
use PHPUnit\Framework\TestCase;

/**
 * SwooleChannel 关闭状态测试
 *
 * 验证 {@see SwooleChannel::isClosed()} 在关闭后无需后续操作即可正确反映状态。
 */
final class SwooleChannelTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    protected function tearDown(): void
    {
        Runtime::reset();
    }

    public function testIsClosedReflectsStateAfterClose(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('Swoole 扩展不可用');
        }

        Runtime::run(function (): void {
            $channel = new SwooleChannel(1);
            $channel->push('x');
            $channel->close();

            // 关键断言：close() 之后尚未发起任何读写操作，isClosed() 必须立即为 true
            self::assertTrue($channel->isClosed());

            // 关闭后写入应失败
            self::assertFalse($channel->push('y'));

            // 仍可取出关闭前缓冲的数据
            self::assertSame('x', $channel->pop());
            self::assertTrue($channel->isClosed());
        });
    }
}
