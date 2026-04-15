<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\Lib\LruCache;

/**
 * LruCacheTest — unit tests for the LRU cache implementation.
 *
 * The LruCache is used by TailwindMerge to memoise merge results.  Key
 * correctness requirements:
 *
 *   1. get() on a missing key returns the miss sentinel, NOT null (because null
 *      and '' are both valid cached values when merging empty strings).
 *   2. has() is the clean boolean check for key presence.
 *   3. When the cache is full, the least-recently-used entry is evicted —
 *      get() promotes an entry to "most recently used".
 *   4. set() on an existing key updates in place (does not grow the cache).
 */
class LruCacheTest extends TestCase
{
    // =========================================================================
    // Basic get / set / has
    // =========================================================================

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
        // A cache miss must return the sentinel object — NOT null — so that
        // null and '' (legitimate cached values) can be distinguished from a miss.
        $cache  = new LruCache(3);
        $result = $cache->get('missing');
        $this->assertSame(LruCache::miss(), $result);
    }

    public function testCanCacheEmptyString(): void
    {
        // '' is a valid merge result (merging two empty strings → '').
        // It must survive a round-trip through the cache without being mistaken
        // for a miss, which would happen if we used null as the miss sentinel.
        $cache = new LruCache(3);
        $cache->set('key', '');
        $this->assertTrue($cache->has('key'));
        $this->assertSame('', $cache->get('key'));
    }

    public function testUpdateExistingKey(): void
    {
        // set() on an existing key replaces the value without growing the cache.
        $cache = new LruCache(3);
        $cache->set('key', 'old');
        $cache->set('key', 'new');
        $this->assertSame('new', $cache->get('key'));
    }

    // =========================================================================
    // LRU eviction
    // =========================================================================

    public function testEvictsLeastRecentlyUsed(): void
    {
        // Fill a size-3 cache: a, b, c (a is oldest, c is newest).
        $cache = new LruCache(3);
        $cache->set('a', '1');
        $cache->set('b', '2');
        $cache->set('c', '3');

        // Access 'a' — this promotes it to most-recently used.
        // LRU order is now: b (oldest), c, a (newest).
        $cache->get('a');

        // Adding 'd' must evict 'b' (the least-recently used).
        $cache->set('d', '4');

        $this->assertSame('1', $cache->get('a'));   // still present (was promoted)
        $this->assertFalse($cache->has('b'));        // evicted
        $this->assertSame('3', $cache->get('c'));
        $this->assertSame('4', $cache->get('d'));
    }

    public function testSizeOneCache(): void
    {
        // A cache of size 1 evicts the existing entry every time a new one is added.
        $cache = new LruCache(1);
        $cache->set('a', '1');
        $cache->set('b', '2');   // evicts 'a'
        $this->assertFalse($cache->has('a'));
        $this->assertTrue($cache->has('b'));
    }

    // =========================================================================
    // Sentinel
    // =========================================================================

    public function testMissSentinelIsSingleton(): void
    {
        // miss() must return the same object on every call so that callers can
        // reliably use === comparison to detect a cache miss.
        $this->assertSame(LruCache::miss(), LruCache::miss());
    }
}
