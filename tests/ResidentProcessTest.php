<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\FiberScheduler;
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * 常驻进程语义回归：无人接收的协程异常是否留下痕迹、fork 出的子进程会不会重放
 * 父进程的收尾、进程级状态能否被整体归零，以及 Swoole 下 wait() 是否真的等到收尾。
 */
final class ResidentProcessTest extends TestCase
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
     * 一次 run() 只能抛出一个异常，其余的必须经上报通道出去，不能静默消失
     */
    public function testAllUncaughtCoroutineErrorsSurface(): void
    {
        $scheduler = new FiberScheduler();
        $reported = [];

        $scheduler->setErrorHandler(static function (Throwable $e) use (&$reported): void {
            $reported[] = $e->getMessage();
        });

        foreach ([1, 2, 3] as $index) {
            $scheduler->spawn(static function () use ($index): void {
                throw new RuntimeException("协程 {$index} 失败");
            });
        }

        $thrown = null;

        try {
            $scheduler->run();
        } catch (Throwable $e) {
            $thrown = $e->getMessage();
        }

        self::assertSame('协程 1 失败', $thrown, '第一个异常仍按既有契约由 run() 抛出');
        self::assertSame(['协程 2 失败', '协程 3 失败'], $reported, '其余异常必须有上报痕迹');

        $scheduler->run();
        self::assertCount(2, $reported, '上报过的异常不得重复上报');
    }

    /**
     * 上报通道自身失败（日志挂了）不能吃掉 run() 本应抛出的异常
     */
    public function testFailingErrorHandlerDoesNotBreakTheLoop(): void
    {
        $scheduler = new FiberScheduler();
        $scheduler->setErrorHandler(static function (): void {
            throw new RuntimeException('日志通道不可用');
        });

        $scheduler->spawn(static function (): void {
            throw new RuntimeException('第一条路径');
        });
        $scheduler->spawn(static function (): void {
            throw new RuntimeException('第二条路径');
        });

        $seen = null;

        try {
            $scheduler->run();
        } catch (Throwable $e) {
            $seen = $e->getMessage();
        }

        self::assertSame('第一条路径', $seen, '处理器抛异常时仍要交出协程自己的异常');
    }

    /**
     * 进程级状态要能整体归零：只清适配器会留下调度器那一半（跨请求串味的来源）
     */
    public function testResetClearsTheSchedulerSingletonToo(): void
    {
        $before = FiberScheduler::instance();
        $before->spawn(static function () use ($before): void {
            // 挂起在定时器上，模拟一个请求结束时没跑完的协程
            $before->sleep(30);
        });

        self::assertGreaterThan(0, $before->count(), '前置条件：全局调度器里有存活协程');

        Runtime::reset();

        $after = FiberScheduler::instance();

        self::assertNotSame($before, $after, 'reset() 应连全局调度器一起换掉');
        self::assertSame(0, $after->count(), '新调度器不该继承上一个请求的协程');
    }

    /**
     * 子进程是父进程的内存镜像：父进程待执行的 ROOT defer 不能被子进程重放
     */
    public function testForkedChildDoesNotReplayParentRootDefers(): void
    {
        if (!RuntimeEnvironment::Process->isAvailable()) {
            self::markTestSkipped('PCNTL 扩展不可用');
        }

        // 固定到 Fiber：让断言只覆盖「继承状态清理」这一件事，与事件循环实现无关
        Runtime::setEnvironment(RuntimeEnvironment::Fiber);

        $probe = @pcntl_fork();

        if ($probe === false || $probe === null) {
            self::markTestSkipped('当前运行时不支持 fork（pcntl_fork 被禁用）');
        }

        if ($probe === 0) {
            exit(0);
        }

        pcntl_waitpid($probe, $probeStatus);

        $trace = tempnam(sys_get_temp_dir(), 'kode-runtime-fork-');
        self::assertIsString($trace);

        Runtime::defer(static function () use ($trace): void {
            file_put_contents($trace, 'P', FILE_APPEND);
        });

        $pid = Runtime::fork(static function () use ($trace): void {
            file_put_contents($trace, 'C', FILE_APPEND);
        });

        $status = 0;
        pcntl_waitpid($pid, $status);

        self::assertSame(0, pcntl_wexitstatus($status), '子进程应正常退出');
        self::assertSame(
            'C',
            (string) file_get_contents($trace),
            '子进程只执行自己的任务，不得重放父进程的 ROOT defer'
        );

        Runtime::wait();
        self::assertSame('CP', (string) file_get_contents($trace), '父进程的 defer 只在父进程执行一次');

        @unlink($trace);
    }

    public function testForkedChildReportsFailureWithNonZeroExit(): void
    {
        if (!RuntimeEnvironment::Process->isAvailable()) {
            self::markTestSkipped('PCNTL 扩展不可用');
        }

        $pid = Runtime::fork(static function (): void {
            throw new RuntimeException('子进程内部失败');
        });

        $status = 0;
        pcntl_waitpid($pid, $status);

        self::assertSame(1, pcntl_wexitstatus($status), '子进程异常必须以非零退出码反馈给父进程');
    }

    /**
     * Swoole 下 async() 的协程挂起过之后，wait() 必须真的等到它结束，
     * 而不是把尾巴留给 Swoole 的 rshutdown
     */
    public function testSwooleWaitDrainsPendingCoroutines(): void
    {
        if (!extension_loaded('swoole')) {
            self::markTestSkipped('Swoole 扩展不可用');
        }

        Runtime::setEnvironment(RuntimeEnvironment::Swoole);

        $finished = false;

        Runtime::async(static function () use (&$finished): void {
            \Swoole\Coroutine::sleep(0.02);
            $finished = true;
        });

        Runtime::wait();

        self::assertTrue($finished, 'wait() 返回时协程收尾应已完成');
    }
}
