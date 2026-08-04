# `kode/runtime` —— 跨平台运行时抽象层

> **一个为现代 PHP 常驻内存应用设计的统一运行时抽象包**
> 支持 Swoole、Swow、PHP Fiber（协程）、多进程、多线程、Console 及传统 CLI 模式
> 面向 PHP 8.3+，基于 enum、#[\Override]、typed class constants、Fiber 协作式调度器等 PHP 8.3 新特性构建

---

## 📦 包信息

| 项目 | 内容 |
|------|------|
| **包名** | `kode/runtime` |
| **PHP 版本** | `^8.3` |
| **许可证** | Apache-2.0 |
| **维护状态** | Active |
| **GitHub** | `https://github.com/kodephp/runtime` |
| **Packagist** | `kode/runtime` |

### 依赖包

| 包名 | 版本 | 说明 |
|------|------|------|
| `kode/context` | `^2.1` | 协程/纤程上下文管理（必需） |
| `kode/console` | `^3.0` | 控制台输入输出（可选，suggest，用于 `ConsoleRuntime` 装饰器） |

---

## 🎯 设计目标

为构建 **常驻内存型 PHP 框架** 提供底层运行时抽象能力：

- 🔌 统一不同协程引擎（Swoole / Swow / Fiber）的 API 差异
- 🔄 提供一致的异步编程模型（`async` / `run` / `parallel` / `channel` / `defer`）
- 🏗️ 支持多进程（`pcntl_fork`）、多线程（`ext-parallel`）环境
- 🎨 Console 作为**输出增强层**（装饰器模式），不再抢占自动探测导致降级
- 🔒 协程安全的上下文管理
- ⚡ 内置协作式 `FiberScheduler`，使 Fiber 模式下的 `sleep` / `Channel` 阻塞**真正让出执行权**而非忙等

---

## ✅ 核心功能

| 功能 | 说明 |
|------|------|
| 🔍 运行环境枚举 | `RuntimeEnvironment` 类型安全枚举，自动检测 Swoole → Swow → Fiber → CLI |
| 🔄 统一协程启动 | `Runtime::async()` / 全局函数 `go()` |
| 🏁 run / parallel | `run(callable $main)` 入口函数驱动并等待；`parallel(iterable $tasks)` 并发收集结果 |
| ⏱️ 统一 sleep API | `Runtime::sleep()` / `delay()` 支持微秒级，协程环境真让出 |
| 📦 通道（Channel） | 跨平台通信，支持超时（`TIMEOUT_FOREVER` / `TIMEOUT_NONE`）、`isEmpty` / `isFull` / `isTimeout` |
| 🧩 defer 支持 | 按作用域隔离（协程互不串扰）、LIFO 清理、根作用域注册 shutdown |
| 🧠 上下文管理 | 基于 `kode/context` 的协程安全存储 |
| 🎮 Console 集成 | `ConsoleRuntime` 装饰器，委托给并发运行时并增强输出 |
| 🧵 Fiber 调度器 | `FiberScheduler` 就绪队列 + 定时器事件循环，协程假异步已修复 |
| 🛠️ 函数助手 | 全局函数 `go` / `parallel` / `run` / `channel` / `defer` / `delay` / `wait` |

---

## 🚀 快速开始

### 1. 安装

```bash
composer require kode/runtime
```

> 最低要求 PHP 8.3。可选能力通过扩展提供：`ext-swoole`、`ext-swow`、`ext-pcntl`、`ext-parallel`；控制台增强通过 `kode/console`。

### 2. 环境检测

```php
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;

echo "当前运行环境: " . Runtime::environment()->label();
// 输出: SWOOLE | SWOW | FIBER | PROCESS | THREAD | CONSOLE | CLI

var_dump(Runtime::supportsConcurrency()); // 是否具备真正的并发能力

// 枚举方式列举可用环境
foreach (RuntimeEnvironment::available() as $env) {
    echo $env->label() . PHP_EOL;
}
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

### 4. run + parallel 并发

```php
use Kode\Runtime\Runtime;

$results = Runtime::run(function () {
    return Runtime::parallel([
        'a' => fn () => usleep(100_000) ?: 'A',
        'b' => fn () => usleep(200_000) ?: 'B',
        'c' => fn () => usleep(50_000)  ?: 'C',
    ]);
});

print_r($results); // ['a' => 'A', 'b' => 'B', 'c' => 'C'] 并发执行
```

`parallel()` 在具备并发能力的运行时（Fiber / Swoole / Swow / Process / Thread）真正并行；在 CLI 下顺序退化为顺序执行但保持 API 一致。

### 5. Channel 通信（含超时）

```php
use Kode\Runtime\Runtime;
use Kode\Runtime\ChannelInterface;

$channel = Runtime::createChannel(1);

Runtime::async(function () use ($channel) {
    $channel->push("Hello from coroutine");
});

Runtime::async(function () use ($channel) {
    Runtime::sleep(0.5);
    $data = $channel->pop();
    echo "收到: $data\n";

    // 带超时的非阻塞尝试
    $miss = $channel->pop(ChannelInterface::TIMEOUT_NONE);
    var_dump($channel->isTimeout()); // true
});

Runtime::wait();
```

### 6. defer 清理资源

```php
use Kode\Runtime\Runtime;

Runtime::async(function () {
    $fp = fopen('/tmp/test.txt', 'w');

    Runtime::defer(function () use ($fp) {
        fclose($fp);
    });

    fwrite($fp, "Hello");
    Runtime::sleep(1);
});
```

`defer` 按**作用域**隔离：协程内注册的回调只在该协程结束时执行，主流程（根作用域）注册的回调在 `wait()` 或脚本结束时执行，且均为**后进先出（LIFO）**。

### 7. 多进程支持

```php
use Kode\Runtime\Runtime;
use Kode\Runtime\RuntimeEnvironment;

Runtime::setEnvironment(RuntimeEnvironment::Process);

$pid = Runtime::fork(function () {
    echo "子进程 PID: " . getmypid() . "\n";
    Runtime::sleep(1);
});

Runtime::wait();
```

子进程通过退出码返回状态，异常会被捕获并以退出码 `1` 传播，父进程据此判断成败。

### 8. Console 命令

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

> `ConsoleRuntime` 现在是**装饰器**：它内部委托给 `RuntimeAdapterFactory::createConcurrent()` 选择的并发运行时，只增强控制台输出，不再因为安装了 `kode/console` 就把运行时降级为同步。

---

## 🧱 架构设计

### 适配器 + 抽象基类 + 装饰器

```
┌─────────────────────────────────────────────────────────┐
│                      用户代码层                          │
│   Runtime::async() / run() / parallel() / go() ...      │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                  RuntimeInterface                       │
│          (统一接口：environment/supportsConcurrency/     │
│           async/run/parallel/sleep/createChannel/       │
│           defer/wait)                                   │
└─────────────────────────────────────────────────────────┘
                           │
        ┌──────────┬───────┴───────┬──────────┬──────────┐
        ▼          ▼               ▼          ▼          ▼
  ┌──────────┐ ┌────────┐   ┌──────────┐ ┌────────┐ ┌──────────┐
  │ Swoole   │ │ Swow   │   │ Fiber    │ │Process │ │ Thread   │
  │Runtime   │ │Runtime │   │Runtime   │ │Runtime │ │Runtime   │
  └──────────┘ └────────┘   └──────────┘ └────────┘ └──────────┘
        │          │             │            │          │
        └──────────┴──┬──────────┴────────────┴──────────┘
                      ▼
              ┌──────────────┐      ┌──────────────┐
              │ AbstractRuntime│◄─────│ ConsoleRuntime│ (装饰器)
              │ (defer 作用域) │      │ (委托并发+输出)│
              └──────────────┘      └──────────────┘
                      │
              ┌──────────────┐
              │ FiberScheduler │ (Fiber 模式专用事件循环)
              └──────────────┘
```

### 核心类

| 类名 | 说明 |
|------|------|
| `Runtime` | 静态门面，提供全局访问点与自动探测 |
| `RuntimeInterface` | 运行时接口契约（含 `run` / `parallel` 等新能力） |
| `RuntimeEnvironment` | 运行环境枚举（类型安全、可用性检测、自动降级） |
| `RuntimeAdapterFactory` | 适配器工厂，`createConcurrent()` 自动探测 |
| `AbstractRuntime` | 抽象基类，统一 defer 作用域语义与通用能力 |
| `FiberScheduler` | PHP Fiber 协作式调度器（就绪队列 + 定时器） |
| `FiberRuntime` | PHP Fiber 适配器（驱动 `FiberScheduler`） |
| `SwooleRuntime` | Swoole 协程适配器 |
| `SwowRuntime` | Swow 协程适配器 |
| `ProcessRuntime` | 多进程适配器（`pcntl_fork` + `socket_pair` 回收结果） |
| `ThreadRuntime` | 多线程适配器（`ext-parallel`） |
| `ConsoleRuntime` | Console 装饰器，委托并发运行时 + 输出增强 |
| `CliRuntime` | CLI 同步执行适配器（顺序退化） |
| `RuntimeCommand` | Console 命令基类 |
| `functions.php` | 全局函数助手（composer `files` autoload） |

---

## 🔄 API 参考

### Runtime 门面

```php
final class Runtime
{
    // 运行环境名称（大写标签）
    public static function getName(): string;

    // 运行环境枚举
    public static function environment(): RuntimeEnvironment;

    // 是否具备真正的并发能力
    public static function supportsConcurrency(): bool;

    // 异步执行（协程/进程/线程，取决于当前运行时）
    public static function async(callable $callback): mixed;

    // 以入口函数方式运行并等待全部异步任务结束
    public static function run(callable $main): mixed;

    // 并发执行多个任务并按原始键收集结果
    public static function parallel(iterable $tasks): array;

    // 休眠（协程环境让出执行权）
    public static function sleep(float $seconds): void;

    // 创建通道
    public static function createChannel(int $capacity = 0): ChannelInterface;

    // 注册当前作用域退出时执行的回调（LIFO）
    public static function defer(callable $callback): void;

    // 等待所有异步任务完成
    public static function wait(): void;

    // 创建子进程（仅 PCNTL 环境）
    public static function fork(callable $callback): int;

    // 设置特定运行环境（接受枚举或字符串）
    public static function setEnvironment(RuntimeEnvironment|string $environment): void;

    // 直接注入适配器（便于测试/DI），null 恢复自动探测
    public static function setAdapter(?RuntimeInterface $adapter): void;

    // 获取当前适配器
    public static function adapter(): RuntimeInterface;

    // 重置适配器（测试用）
    public static function reset(): void;
}
```

### RuntimeEnvironment 枚举

```php
enum RuntimeEnvironment: string
{
    case Swoole  = 'swoole';
    case Swow    = 'swow';
    case Fiber   = 'fiber';
    case Process = 'process';
    case Thread  = 'thread';
    case Console = 'console';
    case Cli     = 'cli';

    public function label(): string;            // 大写显示名
    public function isAvailable(): bool;        // 当前环境是否可用
    public function supportsConcurrency(): bool;// 是否真正并发
    public function unavailableMessage(): string;

    public static function resolve(string|self $environment): self;
    public static function detect(): self;      // Swoole→Swow→Fiber→Cli
    public static function available(): array;  // 所有可用环境
}
```

### RuntimeAdapterFactory

```php
final class RuntimeAdapterFactory
{
    public const string ENV_SWOOLE  = 'swoole';
    public const string ENV_SWOW    = 'swow';
    public const string ENV_FIBER   = 'fiber';
    public const string ENV_PROCESS = 'process';
    public const string ENV_THREAD  = 'thread';
    public const string ENV_CLI     = 'cli';
    public const string ENV_CONSOLE = 'console';

    public static function create(RuntimeEnvironment|string|null $environment = null): RuntimeInterface;
    public static function createConcurrent(): RuntimeInterface;     // 自动探测最强并发
    public static function createForEnvironment(RuntimeEnvironment|string $environment): RuntimeInterface;
    public static function availableEnvironments(): array;
    public static function isAvailable(RuntimeEnvironment|string $environment): bool;
    public static function isSwooleAvailable(): bool;
    public static function isSwowAvailable(): bool;
    public static function isFiberSupported(): bool;
    public static function isConsoleAvailable(): bool;
}
```

### FiberScheduler（Fiber 模式事件循环）

```php
final class FiberScheduler
{
    public static function instance(): self;       // 全局单例
    public static function resetInstance(): void;  // 重置（测试用）

    public function spawn(callable $callback, ?callable $onFinish = null): \Fiber;
    public function yield(): void;
    public function sleep(float $seconds): void;
    public function run(): void;                   // 驱动循环直到全部结束
    public function tick(): bool;
    public function hasPendingWork(): bool;
    public function hasOtherWork(): bool;          // Channel 死锁判定
    public function inFiber(): bool;
    public function count(): int;
}
```

### 全局函数助手（functions.php）

```php
go(callable $callback): mixed;                 // = Runtime::async()
parallel(iterable $tasks): array;              // = Runtime::parallel()
run(callable $main): mixed;                    // = Runtime::run()
channel(int $capacity = 0): ChannelInterface;  // = Runtime::createChannel()
defer(callable $callback): void;               // = Runtime::defer()
delay(float $seconds): void;                   // = Runtime::sleep()
wait(): void;                                  // = Runtime::wait()
```

### RuntimeCommand 基类

```php
abstract class RuntimeCommand extends \Kode\Console\Command
{
    protected function async(callable $callback): mixed;
    protected function run(callable $main): mixed;
    protected function parallel(iterable $tasks): array;
    protected function sleep(float $seconds): void;
    protected function defer(callable $callback): void;
    protected function wait(): void;
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
    public const float TIMEOUT_FOREVER = -1.0;  // 永久等待
    public const float TIMEOUT_NONE    =  0.0;  // 不等待（立即返回）

    public function push(mixed $data, float $timeout = self::TIMEOUT_FOREVER): bool;
    public function pop(float $timeout = self::TIMEOUT_FOREVER): mixed;
    public function getCapacity(): int;
    public function getLength(): int;
    public function isEmpty(): bool;
    public function isFull(): bool;
    public function isTimeout(): bool;
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
| PHP Fiber | ✅ | PHP 8.3+ 原生 Fiber + 内置 `FiberScheduler` |
| Console | ✅ | 装饰器模式，委托底层并发运行时（需 `kode/console`） |
| 多进程 | ✅ | 基于 PCNTL（`ext-pcntl`） |
| 多线程 | ⚠️ | 需 `ext-parallel`（ZTS 版本 PHP） |
| CLI | ✅ | 同步顺序执行，API 完全兼容 |

> 多线程已从已废弃的 `pthreads` 迁移到官方维护的 `ext-parallel`。

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
- ✅ RuntimeEnvironment 枚举测试
- ✅ RuntimeAdapterFactory 工厂测试
- ✅ FiberRuntime + FiberScheduler 调度测试
- ✅ ProcessRuntime 适配器测试（含子进程异常传播）
- ✅ ThreadRuntime 适配器测试（`ext-parallel` 缺省跳过）
- ✅ Channel 接口测试（含超时）
- ✅ CliRuntime 同步测试
- ✅ 全局函数助手测试
- ✅ RuntimeCommand 命令基类测试

---

## 🛡️ 特性

- **类型安全**：PHP 8.3+ 严格类型、enum、`#[\Override]`、typed class constants、`readonly`
- **真异步**：Fiber 模式内置事件循环，`sleep` / `Channel` 阻塞真正让出，无忙等
- **内存管理**：自动清理协程栈与作用域回调
- **异常处理**：协程/子进程异常统一捕获并向上传播
- **协程安全**：基于 `kode/context` 的上下文隔离
- **API 一致**：同步（CLI）与并发环境共享同一套抽象，可无缝切换

---

## 📁 目录结构

```
src/
├── Contract/                  # 接口定义
├── Exception/                 # 异常类
├── AbstractRuntime.php        # 运行时抽象基类（defer 作用域）
├── ChannelInterface.php       # 通道接口（超时支持）
├── CliChannel.php             # CLI/Fiber 通道
├── CliRuntime.php             # CLI 运行时（同步）
├── ConsoleRuntime.php         # Console 装饰器
├── FiberRuntime.php           # Fiber 运行时
├── FiberScheduler.php         # Fiber 协作式调度器
├── ProcessRuntime.php         # 进程运行时
├── Runtime.php                # 门面类
├── RuntimeAdapterFactory.php  # 适配器工厂
├── RuntimeCommand.php         # 命令基类
├── RuntimeEnvironment.php     # 运行环境枚举
├── RuntimeInterface.php       # 运行时接口
├── SwooleChannel.php          # Swoole 通道
├── SwooleRuntime.php          # Swoole 运行时
├── SwowChannel.php            # Swow 通道
├── SwowRuntime.php            # Swow 运行时
├── ThreadRuntime.php          # 线程运行时
└── functions.php              # 全局函数助手
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
