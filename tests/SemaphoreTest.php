<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\CliRuntime;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use Kode\Runtime\Semaphore;
use PHPUnit\Framework\TestCase;

/**
 * Semaphore 信号量测试
 *
 * 事件循环运行时（Swoole / Swow）下，Semaphore 需在 Runtime::run() 作用域内使用；
 * CLI 运行时可在顶层直接使用。
 */
final class SemaphoreTest extends TestCase
{
    protected function setUp(): void
    {
        Runtime::reset();
    }

    protected function tearDown(): void
    {
        Runtime::reset();
    }

    /**
     * 信号量应真正限制并发度不超过许可数
     */
    public function testLimitsConcurrencyToPermits(): void
    {
        $payload = Runtime::run(function (): array {
            $sem = Runtime::semaphore(2);
            $active = 0;
            $peak = 0;

            $results = Runtime::parallel(array_map(
                static fn (int $i) => static function () use ($sem, &$active, &$peak): int {
                    return $sem->run(static function () use (&$active, &$peak): int {
                        $active++;
                        $peak = max($peak, $active);
                        Runtime::sleep(0.02);
                        $active--;

                        return 1;
                    });
                },
                range(0, 9)
            ));

            return [
                'peak' => $peak,
                'count' => count($results),
                'active' => $active,
                'available' => $sem->available(),
            ];
        });

        self::assertSame(10, $payload['count']);
        self::assertLessThanOrEqual(2, $payload['peak']);
        self::assertSame(0, $payload['active']);
        self::assertSame(2, $payload['available']);
    }

    /**
     * run() 返回回调的返回值
     */
    public function testRunReturnsCallbackValue(): void
    {
        $value = Runtime::run(static fn (): int => Runtime::semaphore(1)->run(static fn (): int => 99));

        self::assertSame(99, $value);
    }

    /**
     * run() 的异常应向上传播，且不泄漏许可
     */
    public function testExceptionPropagatesAndReleasesPermit(): void
    {
        $payload = Runtime::run(function (): array {
            $sem = Runtime::semaphore(1);
            $thrown = null;

            try {
                $sem->run(static function (): void {
                    throw new \RuntimeException('boom');
                });
            } catch (\RuntimeException $e) {
                $thrown = $e->getMessage();
            }

            return [
                'thrown' => $thrown,
                'available' => $sem->available(),
                'capacity' => $sem->capacity(),
            ];
        });

        self::assertSame('boom', $payload['thrown']);
        self::assertSame(1, $payload['available']);
        self::assertSame(1, $payload['capacity']);
    }

    /**
     * 手动 acquire / release 应正确反映可用许可数
     */
    public function testManualAcquireRelease(): void
    {
        $payload = Runtime::run(function (): array {
            $sem = Runtime::semaphore(2);

            self::assertSame(2, $sem->available());

            $sem->acquire();
            $sem->acquire();
            self::assertSame(0, $sem->available());

            $sem->release();
            self::assertSame(1, $sem->available());

            return [];
        });

        self::assertSame([], $payload);
    }

    /**
     * 许可数量小于 1 时抛出异常
     */
    public function testRejectsInvalidPermits(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Semaphore(0);
    }

    /**
     * 全局函数助手 semaphore() 可创建信号量
     */
    public function testGlobalSemaphoreHelper(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $sem = \Kode\Runtime\semaphore(3);

        self::assertInstanceOf(Semaphore::class, $sem);
        self::assertSame(3, $sem->capacity());
        self::assertSame(3, $sem->available());
    }

    /**
     * CLI 运行时下信号量在顶层确定性可用
     */
    public function testCliRuntimeDeterministicAtTopLevel(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $sem = new Semaphore(1, new CliRuntime());
        $value = $sem->run(static fn (): string => 'done');

        self::assertSame('done', $value);
        self::assertSame(1, $sem->available());
    }
}
