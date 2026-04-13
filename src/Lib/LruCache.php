<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * Simple LRU cache (Least Recently Used).
 *
 * Uses a sentinel object to distinguish a cache miss from a cached empty string,
 * which is a valid merge result (e.g. merging only empty strings).
 */
class LruCache
{
    private array $cache = [];
    private int $size;
    private int $maxSize;

    /** Sentinel used to distinguish "not in cache" from "cached empty string" */
    private static mixed $MISS = null;

    public function __construct(int $maxSize)
    {
        $this->maxSize = $maxSize;
        $this->size    = 0;
    }

    /**
     * Returns the cached value, or the MISS sentinel (use has() to distinguish).
     * Prefer the has()+get() or the combined getOrMiss() pattern.
     */
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            return self::miss();
        }

        // Move to end (most recently used)
        $value = $this->cache[$key];
        unset($this->cache[$key]);
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Returns true if the key exists in the cache (regardless of value).
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    public function set(string $key, mixed $value): void
    {
        if (array_key_exists($key, $this->cache)) {
            unset($this->cache[$key]);
            $this->cache[$key] = $value;
            return;
        }

        if ($this->size >= $this->maxSize) {
            // Remove least recently used (first element)
            reset($this->cache);
            $lruKey = key($this->cache);
            unset($this->cache[$lruKey]);
            $this->size--;
        }

        $this->cache[$key] = $value;
        $this->size++;
    }

    /**
     * Returns a singleton sentinel that represents a cache miss.
     * Callers can compare with === to detect misses.
     */
    public static function miss(): object
    {
        if (self::$MISS === null) {
            self::$MISS = new \stdClass();
        }
        return self::$MISS;
    }
}
