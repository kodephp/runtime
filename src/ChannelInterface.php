<?php

declare(strict_types=1);

namespace Kode\Runtime;

/**
 * 通道接口
 *
 * 提供协程间通信的统一抽象接口
 */
interface ChannelInterface
{
    /**
     * 向通道推送数据
     *
     * @param mixed $data 要推送的数据
     * @return bool 推送成功返回 true，失败返回 false
     */
    public function push(mixed $data): bool;

    /**
     * 从通道弹出数据
     *
     * @return mixed 通道中的数据，如果通道为空或已关闭则返回 null
     */
    public function pop(): mixed;

    /**
     * 获取通道容量
     *
     * @return int 通道容量
     */
    public function getCapacity(): int;

    /**
     * 获取通道当前长度（通道中的元素数量）
     *
     * @return int 当前长度
     */
    public function getLength(): int;

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
