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
| `php` | `^8.3` | 运行环境（必需） |
| `kode/context` | `^3.0` | 协程/纤程上下文管理（必需） |
| `kode/console` | `^4.0` | 控制台输入输出（必需，用于 `ConsoleRuntime` 装饰器与 `RuntimeCommand` 命令基类） |

> 可选并发能力通过 PHP 扩展提供：`ext-swoole`、`ext-swow`、`ext-pcntl`、`ext-parallel`。

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
| 🔗 等待组（WaitGroup） | `Runtime::waitGroup()` / 全局 `waitGroup()` 动态派生任意数量异步任务，统一等待并分别收集结果与异常 |
| 🔁 单飞（Once） | `Runtime::once()` / 全局 `once()` 确保回调仅执行一次，并发调用者共享同一结果（含异常） |
| 🏁 竞速（race） | `Runtime::race()` / 全局 `race()` 并发执行多个任务，返回**首个完成**（成功或失败）的结果 |
| 📡 选择（select） | `Runtime::select()` / 全局 `select()` 等待多个通道中**首个就绪者**，返回其通道与数据 |
| 🛠️ 函数助手 | 全局函数 `go` / `parallel` / `run` / `channel` / `defer` / `delay` / `wait` / `waitGroup` / `once` / `race` / `select` |

---

## 🚀 快速开始

### 1. 安装

```bash
composer require kode/runtime
```

> 最低要求 PHP 8.3。`kode/context` 与 `kode/console` 为必需依赖（已随 `composer require` 自动安装）。可选并发能力通过扩展提供：`ext-swoole`、`ext-swow`、`ext-pcntl`、`ext-parallel`。

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

### 7. 等待组（WaitGroup）

当任务数量在编写时不确定、或在多处动态派发时，用 `WaitGroup` 比一次性 `parallel()` 更灵活。它在内部用完成通道阻塞等待，**单个任务失败不会中断其余任务**，并分别收集结果与异常。

```php
use Kode\Runtime\Runtime;

Runtime::run(function () {
    $wg = Runtime::waitGroup();

    foreach (range(1, 5) as $i) {
        $wg->run(static function () use ($i): int {
            Runtime::sleep(0.1);
            return $i * $i;
        });
    }

    $wg->wait();

    print_r($wg->results());   // [1, 4, 9, 16, 25]（按派发顺序）
    echo $wg->hasErrors() ? '有任务失败' : '全部成功';
});
```

> Fiber / CLI 运行时可在顶层直接使用；Swoole / Swow 等事件循环运行时请在 `Runtime::run()` 作用域内使用（与 `channel` / `parallel` 一致）。

### 8. 单飞（Once）与通道竞争（race / select）

`Once` 保证回调**仅执行一次**，多个并发调用者共享同一次执行的结果（或异常），适合缓存一次性初始化、防止重复副作用。

`race()` 并发执行多个任务，返回**第一个完成**（无论成功或失败）的结果；其余任务继续在后台运行但结果被丢弃。若胜出任务抛异常，则向上传播。

`select()` 等待多个通道中**第一个就绪**者，返回其通道与数据，格式为 `['channel' => ChannelInterface, 'value' => mixed]`。

```php
use Kode\Runtime\Runtime;

// 单飞：昂贵的初始化只算一次
$once = Runtime::once();
$value = Runtime::run(fn () => $once->do(fn () => expensiveLookup()));

// 竞速：最快者胜出
$fastest = Runtime::run(fn () => Runtime::race(
    fn () => fetchFromCache(),   // 可能更快
    fn () => fetchFromApi(),     // 可能更慢
));

// 选择：谁先有数据用谁
[$channel, $value] = Runtime::run(function () {
    $a = Runtime::createChannel();
    $b = Runtime::createChannel();
    Runtime::async(fn () => $a->push('from-a'));
    $r = Runtime::select($a, $b);
    return [$r['channel'], $r['value']];
});
```

> 与 `WaitGroup` / `channel` 一致：Fiber / CLI 可在顶层直接使用；Swoole / Swow 等事件循环运行时请在 `Runtime::run()` 作用域内使用。

### 9. 多进程支持

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

### 10. Console 命令

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
| `WaitGroup` | 等待组（动态派生异步任务、统一等待并收集结果与异常） |
| `Once` | 单飞原语（回调仅执行一次，并发调用者共享结果） |
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

    // 创建等待组（WaitGroup）
    public static function waitGroup(?RuntimeInterface $runtime = null): WaitGroup;

    // 创建单飞原语（Once）
    public static function once(?RuntimeInterface $runtime = null): Once;

    // 竞速：并发执行多个任务，返回第一个完成（成功/失败）的结果
    public static function race(callable ...$tasks): mixed;

    // 选择：等待多个通道中第一个就绪者，返回 ['channel' => ChannelInterface, 'value' => mixed]
    public static function select(ChannelInterface ...$channels): array;

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
waitGroup(?RuntimeInterface $runtime = null): WaitGroup; // = Runtime::waitGroup()
once(?RuntimeInterface $runtime = null): Once;            // = Runtime::once()
race(callable ...$tasks): mixed;                          // = Runtime::race()
select(ChannelInterface ...$channels): array;             // = Runtime::select()
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

### WaitGroup 等待组

```php
final class WaitGroup
{
    // $runtime 为 null 时使用当前门面运行时
    public function __construct(?RuntimeInterface $runtime = null);

    // 增加待完成任务计数（delta 必须 ≥ 0，否则抛 InvalidArgumentException）
    public function add(int $delta = 1): static;

    // 派生一个异步任务，完成后收集返回值；异常收集到 errors()，不会中断其余任务
    public function run(callable $task): static;

    // 阻塞直到所有已派发任务完成（协程/事件循环运行时真正让出执行权）
    public function wait(): void;

    // 返回值，按派发序号排序（与 errors() 的键对应）
    public function results(): array;

    // 失败任务的异常，按派发序号排序
    public function errors(): array;

    public function hasErrors(): bool;
    public function count(): int;   // 尚未完成的任务数量
}
```

> 与 `parallel()` 的区别：`parallel()` 需一次性传入任务集合，且不区分「正常结果」与「任务异常」；`WaitGroup` 可在任意位置动态派生任务，并独立收集结果与异常。

### Once 单飞

```php
final class Once
{
    // $runtime 为 null 时使用当前门面运行时
    public function __construct(?RuntimeInterface $runtime = null);

    // 执行被包裹的回调（仅一次），返回其结果；并发调用者共享同一结果。
    // 若首次执行抛异常，则后续调用同样抛该异常且不会重新执行（Once 语义）。
    public function do(callable $fn): mixed;

    // 回调是否已经执行过（无论成功或失败）
    public function hasRun(): bool;

    // 重置状态，允许下次调用重新执行回调（适用于长生命周期进程周期性重新初始化）
    public function reset(): void;
}
```

> `Once` 内部使用容量为 1 的通道作为二元信号量，串行化「是否已完成」的判定与执行，保证在 Fiber / Swoole / Swow 等协作式或抢占式并发下都只执行一次。

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
| Console | ✅ | 装饰器模式，委托底层并发运行时（依赖 `kode/console ^4.0`） |
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
- ✅ WaitGroup 等待组测试（结果收集、异常隔离、并发、CLI 确定性）
- ✅ Once 单飞测试（仅执行一次、并发共享、异常缓存、reset、CLI 顶层）
- ✅ race / select 竞争原语测试（最快胜出、异常传播、首个就绪通道、全局函数助手）

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
├── Once.php                   # 单飞原语
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
