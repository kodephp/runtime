<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\ProcessRuntime;
use Kode\Runtime\RuntimeAdapterFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for ProcessRuntime adapter
 */
class ProcessRuntimeTest extends TestCase
{
    /**
     * Test that ProcessRuntime can be created via factory
     */
    public function testProcessRuntimeCreation(): void
    {
        // Skip test if PCNTL is not available
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL extension is not available');
        }

        $runtime = RuntimeAdapterFactory::createForEnvironment(RuntimeAdapterFactory::ENV_PROCESS);
        $this->assertInstanceOf(ProcessRuntime::class, $runtime);
        $this->assertEquals('PROCESS', $runtime->getName());
    }

    /**
     * Test async execution in process mode
     */
    public function testAsyncExecution(): void
    {
        // Skip test if PCNTL is not available
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('PCNTL extension is not available');
        }

        $runtime = new ProcessRuntime();
        $result = null;

        $pid = $runtime->async(function () use (&$result) {
            $result = 'executed';
        });

        $this->assertIsInt($pid);
        $this->assertGreaterThan(0, $pid);

        // Wait for the process to complete
        pcntl_waitpid($pid, $status);
        $this->assertTrue(true); // If we get here without error, the test passed
    }

    /**
     * Test channel creation in process mode
     */
    public function testChannelCreation(): void
    {
        $runtime = new ProcessRuntime();
        $channel = $runtime->createChannel(1);

        $this->assertNotNull($channel);
        $this->assertTrue(method_exists($channel, 'push'));
        $this->assertTrue(method_exists($channel, 'pop'));
    }

    /**
     * Test sleep functionality in process mode
     */
    public function testSleep(): void
    {
        $runtime = new ProcessRuntime();

        // This should not throw an exception
        $runtime->sleep(0.001);
        $this->assertTrue(true);
    }
}
