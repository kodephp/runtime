# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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