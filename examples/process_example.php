<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kode\Runtime\Runtime;

// 设置运行环境为多进程模式
Runtime::setEnvironment('process');

echo "当前运行环境: " . Runtime::getName() . "\n";

// Fork一个子进程
$pid = Runtime::fork(function () {
    echo "子进程 PID: " . getmypid() . "\n";
    Runtime::sleep(1);
    echo "子进程执行完成\n";
});

echo "父进程 PID: " . getmypid() . "\n";
echo "创建的子进程 PID: $pid\n";

// 等待所有进程完成
Runtime::wait();

echo "所有进程执行完成\n";