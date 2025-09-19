<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kode\Runtime\Runtime;

// 设置运行环境为多线程模式
Runtime::setEnvironment('thread');

echo "当前运行环境: " . Runtime::getName() . "\n";

// 创建一个线程
$thread = Runtime::async(function () {
    echo "线程 ID: " . Thread::getCurrentThreadId() . "\n";
    Runtime::sleep(1);
    echo "线程执行完成\n";
});

echo "主线程继续执行\n";

// 等待线程完成
Runtime::wait();

echo "所有线程执行完成\n";