<?php

declare(strict_types=1);

namespace TailwindMerge;

use TailwindMerge\Lib\ClassGroupUtils;
use TailwindMerge\Lib\DefaultConfig;
use TailwindMerge\Lib\LruCache;
use TailwindMerge\Lib\MergeClassList;

/**
 * Main entry point for tailwind-merge PHP port.
 *
 * Basic usage (no prefix):
 *   $tw = new TailwindMerge();
 *   $tw->merge('px-2 py-1 bg-red-500', 'p-3 bg-blue-600'); // → 'p-3 bg-blue-600'
 *
 * Tailwind v4 variant-style prefix:
 *   $tw = TailwindMerge::withConfig(['prefix' => 'tw']);
 *   $tw->merge('tw:px-2 tw:py-1', 'tw:p-3');              // → 'tw:p-3'
 *   $tw->merge('hover:tw:bg-red tw:hover:bg-red', 'tw:hover:bg-blue'); // → 'tw:hover:bg-blue'
 *
 * Tailwind v3 dash-style prefix:
 *   $tw = TailwindMerge::withConfig(['prefix' => 'tw-']);
 *   $tw->merge('tw-px-2 tw-py-1', 'tw-p-3');              // → 'tw-p-3'
 *   $tw->merge('hover:tw-bg-red-500', 'hover:tw-bg-blue-600'); // → 'hover:tw-bg-blue-600'
 *
 * Static helper (no prefix, shared singleton):
 *   TailwindMerge::tw('p-2 p-4'); // → 'p-4'
 *
 * Join without conflict resolution:
 *   TailwindMerge::join('p-2', null, false, 'p-4'); // → 'p-2 p-4'
 */
class TailwindMerge
{
    private ClassGroupUtils $classGroupUtils;
    private LruCache $cache;
    private string $prefix;

    /** @var self|null Singleton for the static tw() helper */
    private static ?self $instance = null;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct(?array $config = null)
    {
        $baseConfig = DefaultConfig::get();

        if ($config !== null) {
            $baseConfig = self::mergeConfigs($baseConfig, $config);
        }

        $this->prefix          = $baseConfig['prefix'] ?? '';
        $this->classGroupUtils = new ClassGroupUtils($baseConfig);
        $this->cache           = new LruCache($baseConfig['cacheSize'] ?? 500);
    }

    // -------------------------------------------------------------------------
    // Instance API
    // -------------------------------------------------------------------------

    /**
     * Merge one or more class strings, resolving Tailwind conflicts.
     *
     * When a prefix is configured only prefixed classes participate in conflict
     * resolution; all other tokens are kept verbatim.
     */
    public function merge(string ...$args): string
    {
        $classList = implode(' ', $args);

        if (trim($classList) === '') {
            return '';
        }

        if ($this->cache->has($classList)) {
            return $this->cache->get($classList);
        }

        $result = MergeClassList::merge($classList, $this->classGroupUtils, $this->prefix);
        $this->cache->set($classList, $result);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Static API — mirrors the JS named exports
    // -------------------------------------------------------------------------

    /**
     * Static shorthand — uses a shared no-prefix singleton instance.
     */
    public static function tw(string ...$args): string
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->merge(...$args);
    }

    /**
     * twJoin() — concatenates classes WITHOUT conflict resolution.
     *
     * Accepts mixed values; null, false, and '' are silently dropped.
     *
     * @param mixed ...$args
     */
    public static function join(mixed ...$args): string
    {
        $parts = [];
        foreach ($args as $arg) {
            if ($arg !== null && $arg !== false && $arg !== '') {
                $parts[] = (string) $arg;
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Create a new TailwindMerge instance with custom/extended config.
     *
     * Recognised top-level keys:
     *   prefix                      — Tailwind prefix string, e.g. 'tw' (v4) or 'tw-' (v3)
     *   cacheSize                   — LRU cache size (default 500)
     *   classGroups                 — override all class groups
     *   conflictingClassGroups      — override all conflict maps
     *   conflictingClassGroupModifiers
     *   extend.classGroups          — add to existing class groups
     *   extend.conflictingClassGroups
     *
     * @param array $extension Config to layer on top of defaults
     */
    public static function withConfig(array $extension): self
    {
        return new self($extension);
    }

    /**
     * Returns the raw default config array.
     */
    public static function getDefaultConfig(): array
    {
        return DefaultConfig::get();
    }

    /**
     * Deep-merge two config arrays, with $extension taking precedence.
     *
     * Use the 'extend' sub-key to add to (rather than replace) existing groups:
     *
     *   TailwindMerge::mergeConfigs($base, [
     *       'extend' => [
     *           'classGroups' => ['my-group' => [...]],
     *       ],
     *   ]);
     */
    public static function mergeConfigs(array $base, array $extension): array
    {
        $result = $base;

        foreach ($extension as $key => $value) {
            if ($key === 'extend' && is_array($value)) {
                foreach ($value as $extKey => $extValue) {
                    if (isset($result[$extKey]) && is_array($result[$extKey])) {
                        if (is_array($extValue) && !array_is_list($extValue)) {
                            foreach ($extValue as $k => $v) {
                                if (isset($result[$extKey][$k]) && is_array($result[$extKey][$k]) && is_array($v)) {
                                    $result[$extKey][$k] = array_merge($result[$extKey][$k], $v);
                                } else {
                                    $result[$extKey][$k] = $v;
                                }
                            }
                        } else {
                            $result[$extKey] = array_merge($result[$extKey], (array) $extValue);
                        }
                    } else {
                        $result[$extKey] = $extValue;
                    }
                }
            } elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                $result[$key] = array_merge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Reset the shared singleton (useful in tests).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
