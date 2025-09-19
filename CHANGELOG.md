# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2025-09-20

### Added
- 新增 ProcessRuntime 适配器（基于 pcntl_fork 实现）
- 新增 ThreadRuntime 适配器（基于 pthreads extension 实现）
- 新增 RuntimeAdapterFactory 类用于创建适配器实例
- 新增 setEnvironment 方法用于设置运行环境
- 新增 ProcessRuntimeTest 和 ThreadRuntimeTest 测试类
- 新增多进程和多线程使用示例
- 新增综合示例文件 examples/comprehensive_example.php

### Changed
- 重构 Runtime 类为适配器工厂模式
- 更新 README.md 文档说明

### Fixed
- Fixed minor code style issues
- Fixed file ending newlines

## [1.1.0] - 2025-09-19

### Added
- Integrated with `kode/context` package for context management
- Added comprehensive test suite with PHPUnit
- Added code quality checks with PHP_CodeSniffer

### Changed
- Refactored `Context` class to use `kode/context` package
- Updated README.md with information about `kode/context` integration
- Improved code documentation and comments

### Fixed
- Fixed minor code style issues
- Fixed file ending newlines

## [1.0.0] - 2025-09-18

### Added
- Initial release
- Support for Swoole, Swow, PHP Fiber and CLI environments
- Unified runtime abstraction layer
- Channel implementations for all supported environments
- Context management
- Comprehensive documentation