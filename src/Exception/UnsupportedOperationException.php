<?php

declare(strict_types=1);

namespace Kode\Runtime\Exception;

/**
 * 不支持操作异常
 *
 * 当操作在当前环境中不被支持时抛出
 */
final class UnsupportedOperationException extends RuntimeException
{
}
