<?php

declare(strict_types=1);

namespace Kode\Runtime\Tests;

use Kode\Runtime\Context;
use PHPUnit\Framework\TestCase;

/**
 * Context 上下文测试
 */
final class ContextTest extends TestCase
{
    protected function setUp(): void
    {
        Context::clear();
    }

    protected function tearDown(): void
    {
        Context::clear();
    }

    /**
     * 测试设置和获取上下文
     */
    public function testSetAndGet(): void
    {
        Context::set('key1', 'value1');
        $this->assertEquals('value1', Context::get('key1'));
    }

    /**
     * 测试默认值
     */
    public function testDefaultValue(): void
    {
        $this->assertNull(Context::get('non_existent'));
        $this->assertEquals('default', Context::get('non_existent', 'default'));
    }

    /**
     * 测试删除上下文
     */
    public function testDelete(): void
    {
        Context::set('key2', 'value2');
        Context::delete('key2');
        $this->assertFalse(Context::has('key2'));
    }

    /**
     * 测试检查键是否存在
     */
    public function testHas(): void
    {
        Context::set('key3', 'value3');

        $this->assertTrue(Context::has('key3'));
        $this->assertFalse(Context::has('non_existent'));
    }

    /**
     * 测试清空上下文
     */
    public function testClear(): void
    {
        Context::set('a', 1);
        Context::set('b', 2);
        Context::clear();

        $this->assertFalse(Context::has('a'));
        $this->assertFalse(Context::has('b'));
    }

    /**
     * 测试复制上下文
     */
    public function testCopy(): void
    {
        Context::set('key4', 'value4');
        $copy = Context::copy();

        $this->assertIsArray($copy);
        $this->assertArrayHasKey('key4', $copy);
        $this->assertEquals('value4', $copy['key4']);
    }

    /**
     * 测试获取所有键名
     */
    public function testKeys(): void
    {
        Context::set('a', 1);
        Context::set('b', 2);
        $keys = Context::keys();

        $this->assertContains('a', $keys);
        $this->assertContains('b', $keys);
    }

    /**
     * 测试覆盖值
     */
    public function testOverwrite(): void
    {
        Context::set('key5', 'first');
        Context::set('key5', 'second');

        $this->assertEquals('second', Context::get('key5'));
    }

    /**
     * 测试各种数据类型
     */
    public function testVariousDataTypes(): void
    {
        Context::set('int', 123);
        Context::set('bool', true);
        Context::set('array', ['a', 'b']);
        Context::set('object', new \stdClass());

        $this->assertEquals(123, Context::get('int'));
        $this->assertTrue(Context::get('bool'));
        $this->assertEquals(['a', 'b'], Context::get('array'));
        $this->assertInstanceOf(\stdClass::class, Context::get('object'));
    }

    /**
     * 测试 run 方法
     */
    public function testRun(): void
    {
        Context::set('outer', 'value_outer');

        $result = Context::run(function () {
            Context::set('inner', 'value_inner');
            return Context::get('inner');
        });

        $this->assertEquals('value_inner', $result);
        $this->assertFalse(Context::has('inner'));
        $this->assertEquals('value_outer', Context::get('outer'));
    }
}
