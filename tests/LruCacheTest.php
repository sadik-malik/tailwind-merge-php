<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\Lib\LruCache;

class LruCacheTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $cache = new LruCache(3);
        $cache->set('key1', 'value1');
        $this->assertSame('value1', $cache->get('key1'));
    }

    public function testHasReturnsFalseForMissing(): void
    {
        $cache = new LruCache(3);
        $this->assertFalse($cache->has('missing'));
    }

    public function testHasReturnsTrueAfterSet(): void
    {
        $cache = new LruCache(3);
        $cache->set('key', 'val');
        $this->assertTrue($cache->has('key'));
    }

    public function testGetMissingReturnsSentinel(): void
    {
        $cache = new LruCache(3);
        $result = $cache->get('missing');
        $this->assertSame(LruCache::miss(), $result);
    }

    public function testCanCacheEmptyString(): void
    {
        // Empty string is a valid cache value (e.g. merging two empty strings)
        $cache = new LruCache(3);
        $cache->set('key', '');
        $this->assertTrue($cache->has('key'));
        $this->assertSame('', $cache->get('key'));
    }

    public function testEvictsLeastRecentlyUsed(): void
    {
        $cache = new LruCache(3);
        $cache->set('a', '1');
        $cache->set('b', '2');
        $cache->set('c', '3');

        // Access 'a' so it becomes most recently used
        $cache->get('a');

        // Adding 'd' should evict 'b' (LRU)
        $cache->set('d', '4');

        $this->assertSame('1', $cache->get('a'));
        $this->assertFalse($cache->has('b'));
        $this->assertSame('3', $cache->get('c'));
        $this->assertSame('4', $cache->get('d'));
    }

    public function testUpdateExistingKey(): void
    {
        $cache = new LruCache(3);
        $cache->set('key', 'old');
        $cache->set('key', 'new');
        $this->assertSame('new', $cache->get('key'));
    }

    public function testMissSentinelIsSingleton(): void
    {
        // Two calls to miss() return the same object
        $this->assertSame(LruCache::miss(), LruCache::miss());
    }

    public function testSizeOneCache(): void
    {
        $cache = new LruCache(1);
        $cache->set('a', '1');
        $cache->set('b', '2');
        $this->assertFalse($cache->has('a'));
        $this->assertTrue($cache->has('b'));
    }
}
