<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Exception\RuntimeException;
use Kode\Runtime\ProcessRuntime;
use Kode\Runtime\RuntimeAdapterFactory;
use Kode\Runtime\RuntimeEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * ProcessRuntime 适配器测试
 */
final class ProcessRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!RuntimeEnvironment::Process->isAvailable()) {
            $this->markTestSkipped('PCNTL 扩展不可用');
        }

        // 即使加载了 pcntl，受限环境（沙箱、Swoole 事件循环内）也会禁止 fork
        $probe = @pcntl_fork();

        if ($probe === null || $probe === false) {
            $this->markTestSkipped('当前运行时不支持 fork（pcntl_fork 被禁用）');
        }

        if ($probe === 0) {
            // 子进程：直接退出，避免测试体在子进程中执行
            exit(0);
        }

        // 父进程：回收探针子进程后继续
        pcntl_waitpid($probe, $status);
    }

    /**
     * 测试进程运行时创建
     */
    public function testProcessRuntimeCreation(): void
    {
        $runtime = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_PROCESS);

        $this->assertInstanceOf(ProcessRuntime::class, $runtime);
        $this->assertEquals('PROCESS', $runtime->getName());
        $this->assertTrue($runtime->supportsConcurrency());
    }

    /**
     * 测试异步执行
     */
    public function testAsyncExecution(): void
    {
        $runtime = new ProcessRuntime();
        $pid = $runtime->async(static function (): void {
            usleep(1000);
        });

        $this->assertIsInt($pid);
        $this->assertGreaterThan(0, $pid);

        $runtime->wait();
        $this->assertSame(0, $runtime->exitCodes()[$pid]);
    }

    /**
     * 测试并发执行并回收子进程返回值
     */
    public function testParallelCollectsResults(): void
    {
        $runtime = new ProcessRuntime();
        $start = microtime(true);

        $results = $runtime->parallel([
            'a' => static function (): array {
                usleep(150_000);
                return ['pid' => getmypid(), 'value' => 'A'];
            },
            'b' => static function (): array {
                usleep(150_000);
                return ['pid' => getmypid(), 'value' => 'B'];
            },
        ]);

        $elapsed = microtime(true) - $start;

        $this->assertSame('A', $results['a']['value']);
        $this->assertSame('B', $results['b']['value']);
        $this->assertNotSame($results['a']['pid'], $results['b']['pid'], '任务应在不同子进程中执行');
        $this->assertNotSame(getmypid(), $results['a']['pid']);
        $this->assertLessThan(0.28, $elapsed, '两个子进程应并行执行');
    }

    /**
     * 测试子进程异常会向父进程传播
     */
    public function testChildFailurePropagates(): void
    {
        $runtime = new ProcessRuntime();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/子进程 \d+ 执行失败: 子任务异常/');

        $runtime->parallel([
            static function (): void {
                throw new \LogicException('子任务异常');
            },
        ]);
    }

    /**
     * 测试通道创建
     */
    public function testChannelCreation(): void
    {
        $channel = (new ProcessRuntime())->createChannel(1);

        $this->assertEquals(1, $channel->getCapacity());
        $this->assertTrue($channel->isEmpty());
    }

    /**
     * 测试休眠功能
     */
    public function testSleep(): void
    {
        $runtime = new ProcessRuntime();
        $start = microtime(true);
        $runtime->sleep(0.01);

        $this->assertGreaterThanOrEqual(0.01, microtime(true) - $start);
    }
}
