<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Once;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Once 单飞原语测试
 *
 * 由于自动探测的运行时可能是事件循环型（Swoole/Swow），涉及通道/协程的场景统一置于
 * Runtime::run() 作用域内执行，以保证跨运行时行为一致。
 */
final class OnceTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    public function testExecutesCallbackExactlyOnce(): void
    {
        $payload = Runtime::run(function (): array {
            $once = new Once();
            $calls = 0;
            $fn = static function () use (&$calls): string {
                $calls++;
                Runtime::sleep(0.01);
                return 'computed';
            };

            $a = $once->do($fn);
            $b = $once->do($fn);
            $c = $once->do($fn);

            return ['a' => $a, 'b' => $b, 'c' => $c, 'calls' => $calls, 'hasRun' => $once->hasRun()];
        });

        self::assertSame('computed', $payload['a']);
        self::assertSame('computed', $payload['b']);
        self::assertSame('computed', $payload['c']);
        self::assertSame(1, $payload['calls'], '回调应仅执行一次');
        self::assertTrue($payload['hasRun']);
    }

    public function testConcurrentCallersShareSingleExecution(): void
    {
        $payload = Runtime::run(function (): array {
            $once = new Once();
            $calls = 0;
            $wg = Runtime::waitGroup();

            for ($i = 0; $i < 10; $i++) {
                $wg->run(static function () use ($once, &$calls): void {
                    $once->do(static function () use (&$calls): string {
                        $calls++;
                        Runtime::sleep(0.02);
                        return 'shared';
                    });
                });
            }
            $wg->wait();

            return ['calls' => $calls, 'hasRun' => $once->hasRun()];
        });

        self::assertSame(1, $payload['calls'], '10 个并发调用者仍只应执行一次');
        self::assertTrue($payload['hasRun']);
    }

    public function testErrorIsCachedAndNotRetried(): void
    {
        $payload = Runtime::run(function (): array {
            $once = new Once();
            $calls = 0;
            $first = null;
            $second = null;

            $task = static function () use (&$calls): string {
                $calls++;
                throw new \RuntimeException('boom');
            };

            try {
                $once->do($task);
            } catch (\RuntimeException $e) {
                $first = $e->getMessage();
            }

            try {
                $once->do($task);
            } catch (\RuntimeException $e) {
                $second = $e->getMessage();
            }

            return ['calls' => $calls, 'first' => $first, 'second' => $second, 'hasRun' => $once->hasRun()];
        });

        self::assertSame('boom', $payload['first']);
        self::assertSame('boom', $payload['second']);
        self::assertSame(1, $payload['calls'], '失败后不应重试');
        self::assertTrue($payload['hasRun']);
    }

    public function testResetAllowsReExecution(): void
    {
        $payload = Runtime::run(function (): array {
            $once = new Once();
            $calls = 0;
            $fn = static function () use (&$calls): int {
                $calls++;
                return $calls;
            };

            $first = $once->do($fn);
            $once->reset();
            $second = $once->do($fn);

            return ['first' => $first, 'second' => $second, 'calls' => $calls, 'hasRun' => $once->hasRun()];
        });

        self::assertSame(1, $payload['first']);
        self::assertSame(2, $payload['second']);
        self::assertSame(2, $payload['calls']);
        self::assertTrue($payload['hasRun']);
    }

    public function testHasRunIsFalseBeforeExecution(): void
    {
        $payload = Runtime::run(static function (): array {
            $once = new Once();
            return ['hasRun' => $once->hasRun()];
        });

        self::assertFalse($payload['hasRun']);
    }

    public function testWorksAtTopLevelUnderCliRuntime(): void
    {
        // 强制 CLI 运行时，验证同步路径（CliChannel 信号量）在顶层可用
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $once = new Once();
        $calls = 0;
        $fn = static function () use (&$calls): string {
            $calls++;
            return 'cli-value';
        };

        self::assertSame('cli-value', $once->do($fn));
        self::assertSame('cli-value', $once->do($fn));
        self::assertSame(1, $calls);
        self::assertTrue($once->hasRun());
    }
}
