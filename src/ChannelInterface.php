<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 通道接口
 *
 * 提供协程间通信的统一抽象接口，支持超时控制
 */
interface ChannelInterface
{
    /**
     * 永久等待（不超时）
     */
    public const float TIMEOUT_FOREVER = -1.0;

    /**
     * 不等待（立即返回）
     */
    public const float TIMEOUT_NONE = 0.0;

    /**
     * 向通道推送数据
     *
     * @param mixed $data 要推送的数据
     * @param float $timeout 通道满时的等待秒数，-1 表示一直等待，0 表示不等待
     * @return bool 推送成功返回 true，失败或超时返回 false
     */
    public function push(mixed $data, float $timeout = self::TIMEOUT_FOREVER): bool;

    /**
     * 从通道弹出数据
     *
     * @param float $timeout 通道空时的等待秒数，-1 表示一直等待，0 表示不等待
     * @return mixed 通道中的数据，通道已关闭、超时或为空时返回 null
     */
    public function pop(float $timeout = self::TIMEOUT_FOREVER): mixed;

    /**
     * 获取通道容量
     *
     * @return int 通道容量，0 表示无限制
     */
    public function getCapacity(): int;

    /**
     * 获取通道当前长度（通道中的元素数量）
     *
     * @return int 当前长度
     */
    public function getLength(): int;

    /**
     * 通道是否为空
     *
     * @return bool 为空返回 true
     */
    public function isEmpty(): bool;

    /**
     * 通道是否已满
     *
     * @return bool 已满返回 true，无容量限制时恒为 false
     */
    public function isFull(): bool;

    /**
     * 最近一次操作是否因超时而失败
     *
     * @return bool 超时返回 true
     */
    public function isTimeout(): bool;

    /**
     * 关闭通道
     */
    public function close(): void;

    /**
     * 检查通道是否已关闭
     *
     * @return bool 已关闭返回 true，否则返回 false
     */
    public function isClosed(): bool;
}
