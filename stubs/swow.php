<?php

// Swow PECL 扩展符号桩（phpstan 静态分析用；运行期由 ext-swow 提供）

namespace Swow;

class Channel
{
    public function __construct(int $capacity) {}

    public function push(mixed $data, ?float $timeout = null): void {}

    public function pop(?float $timeout = null): mixed {}

    public function isAvailable(): bool {}

    public function getCapacity(): int {}

    public function getLength(): int {}

    public function isEmpty(): bool {}

    public function isFull(): bool {}

    public function close(): void {}
}

class Coroutine
{
    public static function run(callable $callback, mixed ...$args): static {}

    public static function yield(): void {}

    public static function getCurrent(): static {}

    public static function getMain(): static {}

    public static function sleep(float $seconds): void {}

    public function isAvailable(): bool {}

    public function addOnCloseCallback(callable $callback): void {}
}
