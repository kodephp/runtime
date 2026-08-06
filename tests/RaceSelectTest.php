<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ChannelInterface;
use Kode\Runtime\Runtime;
use PHPUnit\Framework\TestCase;

use function Kode\Runtime\race;
use function Kode\Runtime\select;

/**
 * race() 与 select() 通道竞争原语测试
 *
 * 统一置于 Runtime::run() 作用域内执行，以兼容事件循环型运行时（Swoole/Swow）。
 */
final class RaceSelectTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    public function testRaceReturnsFastestTaskResult(): void
    {
        $winner = Runtime::run(static function (): string {
            return Runtime::race(
                static function (): string {
                    Runtime::sleep(0.10);
                    return 'slow';
                },
                static function (): string {
                    Runtime::sleep(0.02);
                    return 'fast';
                },
                static function (): string {
                    Runtime::sleep(0.05);
                    return 'mid';
                },
            );
        });

        self::assertSame('fast', $winner);
    }

    public function testRacePropagatesWinnerException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('lose-fast');

        Runtime::run(static function (): void {
            Runtime::race(
                static function (): string {
                    Runtime::sleep(0.10);
                    return 'ok';
                },
                static function (): string {
                    Runtime::sleep(0.01);
                    throw new \RuntimeException('lose-fast');
                },
            );
        });
    }

    public function testRaceEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Runtime::race();
    }

    public function testSelectReturnsFirstReadyChannel(): void
    {
        $payload = Runtime::run(function (): array {
            $ca = Runtime::createChannel();
            $cb = Runtime::createChannel();

            Runtime::async(static function () use ($cb): void {
                Runtime::sleep(0.02);
                $cb->push('from-b');
            });
            Runtime::async(static function () use ($ca): void {
                Runtime::sleep(0.10);
                $ca->push('from-a');
            });

            $r = Runtime::select($ca, $cb);

            return [
                'channel' => $r['channel'] === $ca ? 'A' : 'B',
                'value' => $r['value'],
            ];
        });

        self::assertSame('B', $payload['channel']);
        self::assertSame('from-b', $payload['value']);
    }

    public function testSelectResultShapesChannelAndValue(): void
    {
        $payload = Runtime::run(function (): array {
            $ch = Runtime::createChannel();
            Runtime::async(static function () use ($ch): void {
                $ch->push(42);
            });

            $r = Runtime::select($ch);

            return [
                'isChannel' => $r['channel'] instanceof ChannelInterface,
                'value' => $r['value'],
            ];
        });

        self::assertTrue($payload['isChannel']);
        self::assertSame(42, $payload['value']);
    }

    public function testSelectEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Runtime::select();
    }

    public function testRaceGlobalFunction(): void
    {
        $winner = Runtime::run(static function (): string {
            return race(
                static function (): string {
                    Runtime::sleep(0.05);
                    return 'slow';
                },
                static function (): string {
                    Runtime::sleep(0.01);
                    return 'fast';
                },
            );
        });

        self::assertSame('fast', $winner);
    }

    public function testSelectGlobalFunction(): void
    {
        $payload = Runtime::run(function (): array {
            $ch = Runtime::createChannel();
            Runtime::async(static function () use ($ch): void {
                $ch->push('via-global');
            });

            $r = select($ch);

            return ['value' => $r['value']];
        });

        self::assertSame('via-global', $payload['value']);
    }
}
