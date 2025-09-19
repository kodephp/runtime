<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * Thread runtime adapter implementation for multi-threaded environments.
 *
 * This adapter provides thread-level parallelism using the pthreads extension.
 * Note: This requires PHP to be compiled with ZTS (Zend Thread Safety) support.
 */
class ThreadRuntime implements RuntimeInterface
{
    /**
     * @var array[\Thread]
     */
    private static array $threads = [];

    /**
     * Get the name of the runtime environment
     *
     * @return string Environment name
     */
    public function getName(): string
    {
        return 'THREAD';
    }

    /**
     * Execute a function in a separate thread
     *
     * @param callable $callback The function to execute
     * @return \Thread Thread instance
     */
    public function async(callable $callback)
    {
        // Check if pthreads is available
        if (!extension_loaded('pthreads')) {
            throw new Exception\UnsupportedOperationException('pthreads extension is not available');
        }

        $thread = new class ($callback) extends \Thread {
            private $callback;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
            }

            public function run()
            {
                call_user_func($this->callback);
            }
        };

        $thread->start();
        self::$threads[] = $thread;
        return $thread;
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
            usleep((int)($seconds * 1000000));
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
        // In thread mode, we use a thread-safe queue-based channel
        return new CliChannel($capacity);
    }

    /**
     * Register a callback to be executed when the current thread exits
     *
     * @param callable $callback Cleanup function to execute
     * @return void
     */
    public function defer(callable $callback): void
    {
        // In thread mode, we can't use register_shutdown_function
        // The callback should be handled within the thread's run method
    }

    /**
     * Wait for all threads to complete
     *
     * @return void
     */
    public function wait(): void
    {
        foreach (self::$threads as $thread) {
            $thread->join();
        }
        self::$threads = [];
    }
}
