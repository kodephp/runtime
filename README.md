# `kode/runtime` —— 跨平台运行时抽象层

> **一个为现代 PHP 常驻内存应用设计的统一运行时抽象包**  
> 支持 Swoole、Swow、PHP Fiber（协程）及传统 CLI 模式，实现多进程、多线程、协程兼容的运行时环境  
> 面向 PHP 8.1+，基于协变/逆变、反射与现代语言特性构建，安全、高效、简洁

---

## 📦 包信息

- **包名**: `kode/runtime`
- **PHP 版本要求**: `^8.1`
- **许可证**: Apache-2.0
- **维护状态**: Active
- **GitHub**: `https://github.com/kode-php/runtime`
- **Packagist**: `kode/runtime`

---

## 🎯 设计目标

为构建 **常驻内存型 PHP 框架** 提供底层运行时抽象能力，解决以下痛点：

- 不同协程引擎（Swoole / Swow / Fiber）API 差异大
- CLI 模式下无法使用协程特性
- 多进程/多线程环境下上下文混乱
- 缺乏统一的异步编程模型

`kode/runtime` 提供一个**统一接口层**，屏蔽底层差异，让开发者专注业务逻辑。

---

## ✅ 核心功能

| 功能 | 说明 |
|------|------|
| 🔍 运行环境检测 | 自动识别当前运行在 Swoole、Swow、Fiber 还是 CLI 模式 |
| 🔄 统一协程启动 | `Runtime::async()` 跨平台启动协程 |
| ⏱️ 统一 sleep API | `Runtime::sleep()` 支持微秒级休眠，协程安全 |
| 📦 通道（Channel）抽象 | 跨平台的协程间通信机制 |
| 🧩 defer 支持 | 函数退出时自动执行清理逻辑 |
| 🧠 上下文管理 | 支持协程/线程安全的上下文存储（Context） |
| 🛠️ 适配器模式 | 易于扩展新运行时（如未来 PHP 原生协程） |

---

## 🚀 快速开始

### 1. 安装

```bash
composer require kode/runtime
```

### 2. 环境检测

```php
use Kode\Runtime\Runtime;

echo "当前运行环境: " . Runtime::getEnvironment(); 
// 输出: SWOOLE | SWOW | FIBER | CLI
```

### 3. 启动协程（统一接口）

```php
Runtime::async(function () {
    echo "协程开始\n";
    Runtime::sleep(1.5); // 休眠1.5秒
    echo "协程结束\n";
});

echo "主流程继续执行\n";

// 等待所有协程完成（仅在 CLI/Fiber 模式需要）
Runtime::wait();
```

### 4. 使用 Channel 进行通信

```php
$channel = Runtime::createChannel(1); // 容量为1的通道

Runtime::async(function () use ($channel) {
    echo "生产者: 准备发送数据\n";
    $channel->push("Hello from coroutine");
    echo "生产者: 数据已发送\n";
});

Runtime::async(function () use ($channel) {
    Runtime::sleep(0.5);
    $data = $channel->pop();
    echo "消费者: 接收到: $data\n";
});

Runtime::wait();
```

### 5. defer 清理资源

```php
Runtime::async(function () {
    $fp = fopen('/tmp/test.txt', 'w');
    
    Runtime::defer(function () use ($fp) {
        fclose($fp);
        echo "文件已关闭\n";
    });

    fwrite($fp, "Hello");
    Runtime::sleep(1);
    // 即使发生异常，defer 也会执行
});
```

---

## 🧱 架构设计

### 适配器模式 + 抽象层

```
+------------------+
|   用户代码层     |
| Runtime::async() |
+------------------+
         ↓
+------------------+
|  抽象运行时接口  |
| (RuntimeInterface)|
+------------------+
         ↓
+------------------+     +------------------+     +------------------+
| SwooleAdapter    |     | SwowAdapter      |     | FiberAdapter     |
+------------------+     +------------------+     +------------------+
         ↓                       ↓                       ↓
     Swoole\Coroutine       Swow\Coroutine         Fiber (原生/Revolt)
```

### 核心类概览

| 类名 | 说明 |
|------|------|
| `Runtime` | 静态门面，提供全局访问点 |
| `RuntimeInterface` | 运行时接口契约 |
| `SwooleRuntime` | Swoole 适配器 |
| `SwowRuntime` | Swow 适配器 |
| `FiberRuntime` | PHP Fiber 适配器（基于 Revolt 或自研调度） |
| `CliRuntime` | CLI 模式降级处理（同步执行） |
| `ChannelInterface` | 通道接口 |
| `Context` | 协程/线程安全的上下文管理器 |

---

## 🔄 API 参考

### `Runtime` 类

```php
final class Runtime
{
    /**
     * 检测当前运行环境
     * @return string SWOOLE | SWOW | FIBER | CLI
     */
    public static function getEnvironment(): string;

    /**
     * 异步执行一个协程
     * @param callable $callback
     * @return mixed 协程句柄或ID
     */
    public static function async(callable $callback);

    /**
     * 休眠指定秒数（支持小数）
     * @param float $seconds
     */
    public static function sleep(float $seconds);

    /**
     * 创建一个通道
     * @param int $capacity
     * @return ChannelInterface
     */
    public static function createChannel(int $capacity = 0): ChannelInterface;

    /**
     * 注册退出时执行的回调（协程结束时）
     * @param callable $callback
     */
    public static function defer(callable $callback);

    /**
     * 等待所有协程完成（CLI/Fiber 模式需要）
     */
    public static function wait(): void;
}
```

---

## 🧪 兼容性与测试

| 运行环境 | 支持 | 说明 |
|---------|------|------|
| Swoole | ✅ | v4.8+，需启用协程 |
| Swow | ✅ | v1.5+ |
| PHP Fiber | ✅ | PHP 8.1+，基于生成器或 Revolt |
| CLI (传统) | ✅ | 降级为同步执行，无协程 |
| 多进程 | ✅ | 进程隔离，上下文不共享 |
| 多线程 | ⚠️ | 实验性支持，需 ZTS PHP |

> **注意**：PHP 原生 Fiber 不支持抢占式调度，建议配合事件循环（如 Revolt）使用。

---

## 🛡️ 安全与性能

- **类型安全**：全面使用 PHP 8.1+ 的 `never`、`readonly`、协变/逆变
- **反射优化**：缓存反射结果，避免重复解析
- **内存管理**：自动清理协程栈，防止内存泄漏
- **异常处理**：统一捕获协程内异常，避免崩溃

```php
// 示例：协变返回类型
interface ChannelInterface {
    public function pop(): mixed;
}

class SwooleChannel implements ChannelInterface {
    public function pop(): mixed { /* ... */ }
}
```

---

## 🧩 扩展建议

- 已集成 `kode/context` 包，实现请求级上下文追踪
- 支持 `OpenTelemetry` 分布式追踪上下文传递
- 提供 `Runtime::runInCoroutine()` 自动协程化同步代码（实验）

---

## 🙌 贡献

欢迎提交 PR 和 Issue！  
请遵循 PSR-12 编码规范，编写单元测试。

```bash
composer test
composer cs-check
```

---

## 📄 许可证

Apache License 2.0

---

> `kode/runtime` —— 让 PHP 在任何运行时都如丝般顺滑 🚀