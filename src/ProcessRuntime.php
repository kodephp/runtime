<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Process runtime adapter implementation for multi-process environments.
 *
 * This adapter provides process-level parallelism using fork() system calls.
 */
class ProcessRuntime implements RuntimeInterface
{
    /**
     * @var array[int]
     */
    private static array $childProcesses = [];

    /**
     * Get the name of the runtime environment
     *
     * @return string Environment name
     */
    public function getName(): string
    {
        return 'PROCESS';
    }

    /**
     * Execute a function in a separate process
     *
     * @param callable $callback The function to execute
     * @return int Process ID
     */
    public function async(callable $callback)
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            // Fork failed
            throw new Exception\RuntimeException('Failed to fork process');
        } elseif ($pid === 0) {
            // Child process
            try {
                $callback();
            } finally {
                exit(0);
            }
        } else {
            // Parent process
            self::$childProcesses[] = $pid;
            return $pid;
        }
    }

    /**
     * Sleep for the specified number of seconds
     *
     * @param float $seconds Number of seconds to sleep
     * @return void
     */
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            sleep((int)$seconds);
        }
    }

    /**
     * Create a new channel with the specified capacity
     *
     * @param int $capacity Channel capacity
     * @return ChannelInterface
     */
    public function createChannel(int $capacity = 0): ChannelInterface
    {
        // In process mode, we use a simple queue-based channel
        return new CliChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current process exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void
    {
        // Register shutdown function for process cleanup
        register_shutdown_function($callback);
    }

    /**
     * Wait for all child processes to complete
     *
     * @return void
     */
    public function wait(): void
    {
        foreach (self::$childProcesses as $pid) {
            pcntl_waitpid($pid, $status);
        }
        self::$childProcesses = [];
    }
}
