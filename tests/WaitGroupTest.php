<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\CliRuntime;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use Kode\Runtime\RuntimeInterface;
use Kode\Runtime\WaitGroup;
use PHPUnit\Framework\TestCase;

/**
 * WaitGroup 等待组测试
 *
 * 事件循环运行时（Swoole / Swow）下，WaitGroup 需在 Runtime::run() 作用域内使用，
 * 与 channel / parallel 的使用模型一致；CLI 运行时可在顶层直接使用。
 */
final class WaitGroupTest extends TestCase
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
     * 在「任意」自动探测到的运行时内，等待组应能收集全部结果并保持派发顺序
     */
    public function testCollectsResultsInDispatchOrder(): void
    {
        $payload = Runtime::run(function (): array {
            $wg = Runtime::waitGroup();
            $wg->run(static fn (): string => 'A');
            $wg->run(static fn (): string => 'B');
            $wg->run(static fn (): string => 'C');
            $wg->wait();

            return $wg->results();
        });

        self::assertSame(['A', 'B', 'C'], $payload);
        self::assertSame(0, Runtime::waitGroup()->count());
    }

    /**
     * 单个任务抛异常不应中断其余任务，且异常被独立收集
     */
    public function testCapturesErrorsWithoutAbortingOthers(): void
    {
        $payload = Runtime::run(function (): array {
            $wg = Runtime::waitGroup();
            $wg->run(static fn (): string => 'ok-1');
            $wg->run(static function (): string {
                throw new \RuntimeException('boom');
            });
            $wg->run(static fn (): string => 'ok-2');
            $wg->wait();

            return [
                'results' => $wg->results(),
                'errors' => array_map(static fn (\Throwable $e): string => $e->getMessage(), $wg->errors()),
                'hasErrors' => $wg->hasErrors(),
                'count' => $wg->count(),
            ];
        });

        self::assertSame(['ok-1', 'ok-2'], array_values($payload['results']));
        self::assertSame(['boom'], array_values($payload['errors']));
        // 派发序号保留，可与 errors() 的键对应（任务 1 抛错，故结果键为 0、2）
        self::assertSame([0, 2], array_keys($payload['results']));
        self::assertTrue($payload['hasErrors']);
        // wait() 之后计数归零
        self::assertSame(0, $payload['count']);
    }

    /**
     * 等待组应真正并发执行（耗时低于串行之和）
     */
    public function testTasksRunConcurrently(): void
    {
        $elapsed = Runtime::run(function (): float {
            $start = microtime(true);
            $wg = Runtime::waitGroup();
            $wg->run(static function (): void {
                Runtime::sleep(0.1);
            });
            $wg->run(static function (): void {
                Runtime::sleep(0.1);
            });
            $wg->wait();

            return microtime(true) - $start;
        });

        // 两个 0.1s 任务并发执行，总耗时应明显小于 0.2s（给予调度开销余量）
        self::assertLessThan(0.18, $elapsed);
    }

    /**
     * CLI 运行时下等待组在顶层确定性可用
     */
    public function testCliRuntimeDeterministicAtTopLevel(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $runtime = new CliRuntime();
        $wg = new WaitGroup($runtime);

        $wg->run(static fn (): int => 10);
        $wg->run(static fn (): int => 20);
        $wg->run(static fn (): int => 30);
        $wg->wait();

        self::assertSame([10, 20, 30], $wg->results());
        self::assertFalse($wg->hasErrors());
    }

    /**
     * add() 不接受负数增量
     */
    public function testAddRejectsNegativeDelta(): void
    {
        $wg = new WaitGroup(new CliRuntime());

        $this->expectException(\InvalidArgumentException::class);
        $wg->add(-1);
    }

    /**
     * 全局函数助手 waitGroup() 可创建等待组
     */
    public function testGlobalWaitGroupHelper(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $wg = \Kode\Runtime\waitGroup();
        self::assertInstanceOf(WaitGroup::class, $wg);

        $wg->run(static fn (): int => 42);
        $wg->wait();

        self::assertSame([42], $wg->results());
    }

    /**
     * 通过 Countable / 计数器可观察待完成任务数量
     */
    public function testPendingCountReflectsDispatchedTasks(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $runtime = new CliRuntime();
        $wg = new WaitGroup($runtime);

        self::assertSame(0, $wg->count());

        $wg->add(2);
        self::assertSame(2, $wg->count());

        $wg->run(static fn (): int => 1); // run() 内部再 +1
        self::assertSame(3, $wg->count());
    }

    /**
     * 仅 add() 登记的外部任务，可经 done() 归还计数，避免 wait() 死锁
     */
    public function testAddPairedWithDoneAvoidsDeadlock(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $runtime = new CliRuntime();
        $wg = new WaitGroup($runtime);

        // 登记 3 个外部派发的任务，并立即通过 done() 归还计数
        $wg->add(3);
        $wg->done();
        $wg->done();
        $wg->done();

        // 不应永久阻塞
        $wg->wait();

        self::assertSame(0, $wg->count());
        self::assertFalse($wg->hasErrors());
    }

    /**
     * run() 内部等价于在任务结束时调用一次 done()
     */
    public function testRunInvokesDoneOnCompletion(): void
    {
        Runtime::setEnvironment(RuntimeEnvironment::Cli);

        $runtime = new CliRuntime();
        $wg = new WaitGroup($runtime);

        $wg->run(static fn (): int => 7);
        $wg->wait();

        self::assertSame([7], $wg->results());
        self::assertSame(0, $wg->count());
    }
}
