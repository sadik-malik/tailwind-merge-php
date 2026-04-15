<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * LruCache — a fixed-size Least Recently Used cache.
 *
 * Used by TailwindMerge to memoize merge results keyed by the raw input
 * class string.  Because component libraries repeatedly call merge() with the
 * same arguments, even a modest cache size (500 entries) eliminates nearly all
 * redundant trie walks in practice.
 *
 * IMPLEMENTATION
 * ──────────────
 * PHP's associative arrays preserve insertion order.  We exploit this to build
 * an LRU eviction policy with no additional data structures:
 *
 *   • get()  — if the key exists, unset it and re-insert it at the END of the
 *              array (making it the most-recently used entry).
 *   • set()  — append the key to the end.  If the cache is full, remove the
 *              FIRST entry (least-recently used) before appending.
 *
 * Both operations are O(1) amortised.
 *
 * SENTINEL PATTERN
 * ─────────────────
 * PHP's null cannot serve as a "cache miss" signal when '' (empty string) is a
 * legitimate cached value (merging only empty strings produces '').  A dedicated
 * singleton stdClass object is used as the sentinel so that:
 *
 *   has($key)        → bool    most efficient miss check (uses array_key_exists)
 *   get($key)        → mixed   returns the cached value, or LruCache::miss()
 *   miss()           → object  the singleton sentinel (compare with ===)
 */
class LruCache
{
    /**
     * The underlying storage.  Keys are the cache keys; values are cached results.
     * PHP preserves insertion order, enabling O(1) LRU eviction via reset()/key().
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

    /** Current number of entries in the cache. */
    private int $size;

    /** Maximum number of entries before eviction occurs. */
    private int $maxSize;

    /**
     * Singleton sentinel object returned by get() on a cache miss.
     * Initialised lazily by miss().
     */
    private static mixed $MISS = null;

    /**
     * @param int $maxSize  Maximum entries to hold before evicting the LRU entry.
     *                      The default configured by TailwindMerge is 500.
     */
    public function __construct(int $maxSize)
    {
        $this->maxSize = $maxSize;
        $this->size    = 0;
    }

    /**
     * Retrieves a cached value.
     *
     * If $key exists the entry is moved to the end of the array (marking it as
     * most-recently used) and the value is returned.
     *
     * If $key does not exist the MISS sentinel is returned.  Callers should
     * use has() for a clean boolean check, or compare the return value with
     * === LruCache::miss() to detect a miss without paying for two lookups.
     *
     * @param string $key  The cache key.
     * @return mixed       The cached value, or LruCache::miss() if not present.
     */
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            return self::miss(); // cache miss
        }

        // Promote to most-recently used by removing and re-appending.
        $value = $this->cache[$key];
        unset($this->cache[$key]);
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Returns true if $key is present in the cache, regardless of its value.
     *
     * Use this instead of checking get() === null, because null (or '') can
     * be a legitimately cached value.
     *
     * @param string $key  The cache key to check.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Stores a value in the cache under $key.
     *
     * If $key already exists the entry is updated in-place (moved to most-recent).
     * If the cache is at capacity, the least-recently used entry (first in the
     * array) is evicted before the new entry is added.
     *
     * @param string $key    The cache key.
     * @param mixed  $value  The value to cache (any type, including '' and null).
     */
    public function set(string $key, mixed $value): void
    {
        if (array_key_exists($key, $this->cache)) {
            // Update existing entry: remove and re-append at the end.
            unset($this->cache[$key]);
            $this->cache[$key] = $value;
            return; // size unchanged
        }

        if ($this->size >= $this->maxSize) {
            // Evict the least-recently used entry — the first element in the array.
            reset($this->cache);
            $lruKey = key($this->cache);
            unset($this->cache[$lruKey]);
            $this->size--;
        }

        $this->cache[$key] = $value;
        $this->size++;
    }

    /**
     * Returns the singleton "cache miss" sentinel object.
     *
     * Callers compare return values from get() with === miss() to detect misses:
     *
     *   $value = $cache->get($key);
     *   if ($value === LruCache::miss()) { /* key not in cache *\/ }
     *
     * Using a dedicated object instead of null means that null and ''
     * (which are valid cached values) can be stored and retrieved correctly.
     */
    public static function miss(): object
    {
        if (self::$MISS === null) {
            self::$MISS = new \stdClass();
        }
        return self::$MISS;
    }
}
