# `kode/runtime` —— 跨平台运行时抽象层

> **一个为现代 PHP 常驻内存应用设计的统一运行时抽象包**
> 支持 Swoole、Swow、PHP Fiber（协程）、多进程、多线程、Console 及传统 CLI 模式
> 面向 PHP 8.1+，基于协变/逆变、readonly、match 等 PHP 8+ 新特性构建

---

## 📦 包信息

| 项目 | 内容 |
|------|------|
| **包名** | `kode/runtime` |
| **PHP 版本** | `^8.1` |
| **许可证** | Apache-2.0 |
| **维护状态** | Active |
| **GitHub** | `https://github.com/kodephp/runtime` |
| **Packagist** | `kode/runtime` |

### 依赖包

| 包名 | 版本 | 说明 |
|------|------|------|
| `kode/context` | `^2.0` | 协程/纤程上下文管理 |
| `kode/console` | `^3.0` | 控制台输入输出（可选） |

---

## 🎯 设计目标

为构建 **常驻内存型 PHP 框架** 提供底层运行时抽象能力：

- 🔌 统一不同协程引擎（Swoole / Swow / Fiber）的 API 差异
- 🔄 提供一致的异步编程模型
- 🏗️ 支持多进程、多线程环境
- 🎨 集成 Console 控制台支持
- 🔒 协程安全的上下文管理

---

## ✅ 核心功能

| 功能 | 说明 |
|------|------|
| 🔍 运行环境检测 | 自动识别 Swoole、Swow、Fiber、Console、Process、Thread、CLI 模式 |
| 🔄 统一协程启动 | `Runtime::async()` 跨平台启动协程 |
| ⏱️ 统一 sleep API | `Runtime::sleep()` 支持微秒级休眠 |
| 📦 通道（Channel） | 跨平台协程间通信机制 |
| 🧩 defer 支持 | 函数退出时自动执行清理逻辑 |
| 🧠 上下文管理 | 基于 `kode/context` 的协程安全存储 |
| 🎮 Console 集成 | `RuntimeCommand` 命令基类，集成控制台输出 |
| 🛠️ 适配器模式 | 易于扩展新运行时 |

---

## 🚀 快速开始

### 1. 安装

```bash
composer require kode/runtime
```

### 2. 环境检测

```php
use Kode\Runtime\Runtime;

echo "当前运行环境: " . Runtime::getName();
// 输出: CONSOLE | SWOOLE | SWOW | FIBER | PROCESS | THREAD | CLI
```

### 3. 启动协程

```php
use Kode\Runtime\Runtime;

Runtime::async(function () {
    echo "协程开始\n";
    Runtime::sleep(1.5);
    echo "协程结束\n";
});

echo "主流程继续执行\n";
Runtime::wait();
```

### 4. Channel 通信

```php
$channel = Runtime::createChannel(1);

Runtime::async(function () use ($channel) {
    $channel->push("Hello from coroutine");
});

Runtime::async(function () use ($channel) {
    Runtime::sleep(0.5);
    $data = $channel->pop();
    echo "收到: $data\n";
});

Runtime::wait();
```

### 5. defer 清理资源

```php
Runtime::async(function () {
    $fp = fopen('/tmp/test.txt', 'w');

    Runtime::defer(function () use ($fp) {
        fclose($fp);
    });

    fwrite($fp, "Hello");
    Runtime::sleep(1);
});
```

### 6. 多进程支持

```php
Runtime::setEnvironment('process');

$pid = Runtime::fork(function () {
    echo "子进程 PID: " . getmypid() . "\n";
    Runtime::sleep(1);
});

Runtime::wait();
```

### 7. Console 命令

```php
use Kode\Runtime\RuntimeCommand;
use Kode\Console\Input;
use Kode\Console\Output;

class AsyncTaskCommand extends RuntimeCommand
{
    public function __construct()
    {
        parent::__construct('async:task', '异步任务示例');
    }

    public function fire(Input $in, Output $out): int
    {
        $this->setOutput($out);

        $this->async(function () {
            $this->info('任务开始');
            $this->sleep(1);
            $this->success('任务完成');
        });

        $this->wait();
        return 0;
    }
}
```

---

## 🧱 架构设计

### 适配器模式

```
┌─────────────────────────────────────────────────────────┐
│                      用户代码层                          │
│                   Runtime::async()                      │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  RuntimeInterface                       │
│                 (抽象运行时接口)                        │
└─────────────────────────────────────────────────────────┘
                           │
        ┌──────────┬───────┴───────┬──────────┐
        ▼          ▼               ▼          ▼
  ┌──────────┐ ┌────────┐   ┌──────────┐ ┌────────┐
  │ Swoole   │ │ Swow   │   │ Fiber    │ │Console │
  │Runtime   │ │Runtime │   │Runtime   │ │Runtime │
  └──────────┘ └────────┘   └──────────┘ └────────┘
        │          │             │          │
  ┌──────────┐ ┌────────┐   ┌──────────┐ ┌────────┐
  │ Process  │ │Thread  │   │ Cli      │ │  Cli   │
  │Runtime   │ │Runtime │   │Runtime   │ │Channel │
  └──────────┘ └────────┘   └──────────┘ └────────┘
```

### 核心类

| 类名 | 说明 |
|------|------|
| `Runtime` | 静态门面，提供全局访问点 |
| `RuntimeInterface` | 运行时接口契约 |
| `RuntimeAdapterFactory` | 适配器工厂类 |
| `ConsoleRuntime` | Console 环境适配器 |
| `SwooleRuntime` | Swoole 协程适配器 |
| `SwowRuntime` | Swow 协程适配器 |
| `FiberRuntime` | PHP Fiber 适配器 |
| `ProcessRuntime` | 多进程适配器 |
| `ThreadRuntime` | 多线程适配器 |
| `CliRuntime` | CLI 同步执行适配器 |
| `RuntimeCommand` | Console 命令基类 |

---

## 🔄 API 参考

### Runtime 门面

```php
final class Runtime
{
    // 获取运行时名称
    public static function getName(): string;

    // 异步执行
    public static function async(callable $callback): mixed;

    // 休眠
    public static function sleep(float $seconds): void;

    // 创建通道
    public static function createChannel(int $capacity = 0): ChannelInterface;

    // 注册清理回调
    public static function defer(callable $callback): void;

    // 等待完成
    public static function wait(): void;

    // Fork 进程
    public static function fork(callable $callback): int;

    // 设置环境
    public static function setEnvironment(string $environment): void;

    // 重置
    public static function reset(): void;
}
```

### RuntimeAdapterFactory

```php
final class RuntimeAdapterFactory
{
    // 环境常量
    public const ENV_SWOOLE = 'swoole';
    public const ENV_SWOW = 'swow';
    public const ENV_FIBER = 'fiber';
    public const ENV_PROCESS = 'process';
    public const ENV_THREAD = 'thread';
    public const ENV_CLI = 'cli';
    public const ENV_CONSOLE = 'console';

    // 创建适配器
    public static function create(?string $environment = null): RuntimeInterface;

    // 强制创建指定环境适配器
    public static function createForEnvironment(string $environment): RuntimeInterface;

    // 检测方法
    public static function isSwooleAvailable(): bool;
    public static function isSwowAvailable(): bool;
    public static function isFiberSupported(): bool;
    public static function isConsoleAvailable(): bool;
}
```

### RuntimeCommand 基类

```php
abstract class RuntimeCommand extends \Kode\Console\Command
{
    // 异步执行
    protected function async(callable $callback): mixed;

    // 休眠
    protected function sleep(float $seconds): void;

    // 注册清理
    protected function defer(callable $callback): void;

    // 等待完成
    protected function wait(): void;

    // 创建通道
    protected function createChannel(int $capacity = 0): ChannelInterface;

    // 输出方法
    protected function info(string $message): void;
    protected function warn(string $message): void;
    protected function error(string $message): void;
    protected function success(string $message): void;
    protected function line(string $text, string $color = ''): void;
    protected function table(array $headers, array $rows): void;
    protected function progress(int $current, int $total, int $width = 50): void;
}
```

---

## 🧪 Channel 接口

```php
interface ChannelInterface
{
    public function push(mixed $data): bool;
    public function pop(): mixed;
    public function getCapacity(): int;
    public function getLength(): int;
    public function close(): void;
    public function isClosed(): bool;
}
```

---

## 🧪 兼容性

| 运行环境 | 支持 | 说明 |
|---------|------|------|
| Swoole | ✅ | v4.8+，需启用协程 |
| Swow | ✅ | v1.5+ |
| PHP Fiber | ✅ | PHP 8.1+ |
| Console | ✅ | 集成 kode/console |
| 多进程 | ✅ | 基于 PCNTL |
| 多线程 | ⚠️ | 需 pthreads 扩展 |
| CLI | ✅ | 同步执行 |

---

## 🧪 测试

```bash
# 运行测试
composer test

# 代码风格检查
composer cs-check

# 修复代码风格
composer cs-fix
```

### 测试覆盖

- ✅ Runtime 门面测试
- ✅ ProcessRuntime 适配器测试
- ✅ ThreadRuntime 适配器测试
- ✅ Channel 接口测试
- ✅ 上下文管理测试

---

## 🛡️ 特性

- **类型安全**：PHP 8.1+ 严格类型、readonly、final
- **内存管理**：自动清理协程栈
- **异常处理**：统一捕获协程异常
- **协程安全**：基于 `kode/context` 的上下文隔离

---

## 📁 目录结构

```
src/
├── Contract/                  # 接口定义
├── Exception/                 # 异常类
├── ChannelInterface.php       # 通道接口
├── CliChannel.php            # CLI 通道
├── CliRuntime.php            # CLI 运行时
├── ConsoleChannel.php        # Console 通道
├── ConsoleRuntime.php        # Console 运行时
├── Context.php               # 上下文管理
├── FiberRuntime.php          # Fiber 运行时
├── ProcessRuntime.php        # 进程运行时
├── Runtime.php               # 门面类
├── RuntimeAdapterFactory.php # 适配器工厂
├── RuntimeCommand.php        # 命令基类
├── RuntimeInterface.php      # 运行时接口
├── SwooleChannel.php         # Swoole 通道
├── SwooleRuntime.php         # Swoole 运行时
├── SwowChannel.php           # Swow 通道
├── SwowRuntime.php           # Swow 运行时
└── ThreadRuntime.php         # 线程运行时
```

---

## 🙌 贡献

欢迎提交 PR 和 Issue！

请遵循 PSR-12 编码规范，编写单元测试。

---

## 📄 许可证

Apache License 2.0

---

> `kode/runtime` —— 让 PHP 在任何运行时都如丝般顺滑 🚀
