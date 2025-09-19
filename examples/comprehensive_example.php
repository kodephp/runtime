<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kode\Runtime\Runtime;
use Kode\Runtime\Context;

// 演示不同运行环境的使用

echo "=== kode/runtime 综合示例 ===\n\n";

// 1. 检测当前环境
echo "1. 当前运行环境: " . Runtime::getName() . "\n\n";

// 2. 使用通道进行通信
echo "2. 通道通信示例:\n";
$channel = Runtime::createChannel(2);

Runtime::async(function () use ($channel) {
    echo "  生产者: 准备发送数据\n";
    $channel->push("Hello from coroutine 1");
    $channel->push("Hello from coroutine 2");
    echo "  生产者: 数据已发送\n";
});

Runtime::async(function () use ($channel) {
    Runtime::sleep(0.5);
    $data1 = $channel->pop();
    $data2 = $channel->pop();
    echo "  消费者: 接收到: $data1\n";
    echo "  消费者: 接收到: $data2\n";
});

Runtime::wait();
echo "\n";

// 3. 使用上下文管理
echo "3. 上下文管理示例:\n";
Context::set('request_id', 'req_12345');
Context::set('user_id', 1001);

Runtime::async(function () {
    echo "  协程中获取上下文:\n";
    echo "    Request ID: " . Context::get('request_id') . "\n";
    echo "    User ID: " . Context::get('user_id') . "\n";
});

Runtime::wait();
echo "\n";

// 4. defer 清理资源
echo "4. defer 清理资源示例:\n";
Runtime::async(function () {
    echo "  协程开始执行\n";
    
    Runtime::defer(function () {
        echo "  协程结束，执行清理操作\n";
    });
    
    Runtime::sleep(0.1);
    echo "  协程执行中...\n";
});

Runtime::wait();
echo "\n";

// 5. 多进程示例 (仅在支持时演示)
echo "5. 多进程示例:\n";
if (function_exists('pcntl_fork')) {
    Runtime::setEnvironment('process');
    echo "  切换到进程环境: " . Runtime::getName() . "\n";
    
    $pid = Runtime::fork(function () {
        echo "  子进程 PID: " . getmypid() . " 执行中...\n";
        Runtime::sleep(0.1);
        echo "  子进程执行完成\n";
    });
    
    echo "  父进程 PID: " . getmypid() . "\n";
    echo "  创建的子进程 PID: $pid\n";
    
    Runtime::wait();
    echo "  所有进程执行完成\n\n";
    
    // 重置回原环境
    Runtime::reset();
} else {
    echo "  当前环境不支持多进程 (需要 pcntl 扩展)\n\n";
}

// 6. 多线程示例 (仅在支持时演示)
echo "6. 多线程示例:\n";
if (extension_loaded('pthreads')) {
    Runtime::setEnvironment('thread');
    echo "  切换到线程环境: " . Runtime::getName() . "\n";
    
    $thread = Runtime::async(function () {
        echo "  线程执行中...\n";
        Runtime::sleep(0.1);
        echo "  线程执行完成\n";
    });
    
    echo "  主线程继续执行\n";
    
    Runtime::wait();
    echo "  所有线程执行完成\n\n";
    
    // 重置回原环境
    Runtime::reset();
} else {
    echo "  当前环境不支持多线程 (需要 pthreads 扩展)\n\n";
}

echo "=== 示例执行完成 ===\n";