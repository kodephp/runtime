<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ThreadRuntime;
use Kode\Runtime\RuntimeAdapterFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for ThreadRuntime adapter
 */
class ThreadRuntimeTest extends TestCase
{
    /**
     * Test that ThreadRuntime can be created via factory
     */
    public function testThreadRuntimeCreation(): void
    {
        // Skip test if pthreads is not available
        if (!extension_loaded('pthreads')) {
            $this->markTestSkipped('pthreads extension is not available');
        }

        $runtime = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_THREAD);
        $this->assertInstanceOf(ThreadRuntime::class, $runtime);
        $this->assertEquals('THREAD', $runtime->getName());
    }

    /**
     * Test async execution in thread mode
     */
    public function testAsyncExecution(): void
    {
        // Skip test if pthreads is not available
        if (!extension_loaded('pthreads')) {
            $this->markTestSkipped('pthreads extension is not available');
        }

        $runtime = new ThreadRuntime();
        $result = null;

        $thread = $runtime->async(function () use (&$result) {
            $result = 'executed';
        });

        $this->assertNotNull($thread);
        $this->assertInstanceOf(\Thread::class, $thread);

        // Wait for the thread to complete
        $thread->join();
        $this->assertTrue(true); // If we get here without error, the test passed
    }

    /**
     * Test channel creation in thread mode
     */
    public function testChannelCreation(): void
    {
        $runtime = new ThreadRuntime();
        $channel = $runtime->createChannel(1);

        $this->assertNotNull($channel);
        $this->assertTrue(method_exists($channel, 'push'));
        $this->assertTrue(method_exists($channel, 'pop'));
    }

    /**
     * Test sleep functionality in thread mode
     */
    public function testSleep(): void
    {
        $runtime = new ThreadRuntime();

        // This should not throw an exception
        $runtime->sleep(0.001);
        $this->assertTrue(true);
    }
}
