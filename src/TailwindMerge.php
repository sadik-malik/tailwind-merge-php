<?php

declare(strict_types=1);

namespace TailwindMerge;

use TailwindMerge\Lib\ClassGroupUtils;
use TailwindMerge\Lib\DefaultConfig;
use TailwindMerge\Lib\LruCache;
use TailwindMerge\Lib\MergeClassList;

/**
 * TailwindMerge — PHP port of the tailwind-merge JavaScript library.
 *
 * Merges Tailwind CSS class strings, resolving conflicts so that later classes
 * always win over earlier ones — exactly matching how the browser applies styles.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  HOW IT WORKS (high level)                                              │
 * │                                                                         │
 * │  1. The class string is split into individual tokens.                   │
 * │  2. Each token is parsed into: variant modifiers (hover:, md:, …),     │
 * │     an optional important modifier (!), and a base class name.          │
 * │  3. The base class is looked up in a trie built from DefaultConfig to   │
 * │     find its "class group" (e.g. 'p', 'bg-color', 'font-size').        │
 * │  4. Classes are iterated right-to-left; the first class in each         │
 * │     {modifiers + group} slot wins; duplicates are dropped.              │
 * │  5. The surviving classes are reassembled in their original order.      │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * BASIC USAGE (no prefix)
 * ──────────────────────
 *   $tw = new TailwindMerge();
 *   $tw->merge('px-2 py-1 bg-red-500', 'p-3 bg-blue-600');
 *   // → 'p-3 bg-blue-600'
 *   // Explanation: p-3 beats px-2/py-1 (same group), bg-blue beats bg-red.
 *
 * TAILWIND v4 VARIANT-STYLE PREFIX
 * ─────────────────────────────────
 *   Configured in CSS with:  @import "tailwindcss" prefix(tw);
 *   Classes look like:       tw:flex  tw:hover:bg-red-500
 *
 *   $tw = TailwindMerge::withConfig(['prefix' => 'tw']);
 *   $tw->merge('tw:px-2 tw:py-1', 'tw:p-3');
 *   // → 'tw:p-3'
 *
 * TAILWIND v3 DASH-STYLE PREFIX
 * ──────────────────────────────
 *   Configured in JS with:   prefix: 'tw' in tailwind.config.js
 *   Classes look like:       tw-flex  hover:tw-bg-red-500
 *
 *   $tw = TailwindMerge::withConfig(['prefix' => 'tw-']);
 *   $tw->merge('tw-px-2 tw-py-1', 'tw-p-3');
 *   // → 'tw-p-3'
 *
 * STATIC HELPERS
 * ──────────────
 *   TailwindMerge::tw('p-2 p-4');            // merge, shared singleton, no prefix
 *   TailwindMerge::join('p-2', null, 'p-4'); // concatenate only, no conflict resolution
 */
class TailwindMerge
{
    /**
     * Pre-built trie + conflict maps for fast group lookups.
     * One instance is shared across all merge() calls on this object.
     */
    private ClassGroupUtils $classGroupUtils;

    /**
     * LRU cache keyed by the raw input class string.
     * Avoids re-computing the merge for repeated identical inputs,
     * which is common in component-heavy UIs.
     */
    private LruCache $cache;

    /**
     * The configured Tailwind prefix, e.g. '' (none), 'tw' (v4), or 'tw-' (v3).
     * Stored so it can be forwarded to MergeClassList on every merge() call.
     */
    private string $prefix;

    /**
     * Singleton instance used by the static tw() shorthand.
     * Lazily created on first call; reset by resetInstance() in tests.
     */
    private static ?self $instance = null;

    // =========================================================================
    // Construction
    // =========================================================================

    /**
     * Creates a new TailwindMerge instance.
     *
     * Pass no arguments to use the default Tailwind v3 configuration.
     * Pass a $config array to override or extend the defaults — see withConfig()
     * for the full list of recognised keys.
     *
     * @param array|null $config  Override / extension on top of DefaultConfig.
     */
    public function __construct(?array $config = null)
    {
        // Start from the full default config, then layer any overrides on top.
        $baseConfig = DefaultConfig::get();

        if ($config !== null) {
            $baseConfig = self::mergeConfigs($baseConfig, $config);
        }

        // Pull the optional prefix out before handing config to ClassGroupUtils,
        // because the prefix is a merge-time concern, not a trie-build concern.
        $this->prefix          = $baseConfig['prefix'] ?? '';
        $this->classGroupUtils = new ClassGroupUtils($baseConfig);
        $this->cache           = new LruCache($baseConfig['cacheSize'] ?? 500);
    }

    // =========================================================================
    // Instance API
    // =========================================================================

    /**
     * Merge one or more Tailwind class strings, resolving conflicts.
     *
     * Multiple string arguments are joined with a space before processing,
     * so these two calls are equivalent:
     *   $tw->merge('p-2', 'p-4')
     *   $tw->merge('p-2 p-4')
     *
     * When a prefix is configured (via withConfig), only classes that carry
     * that prefix participate in conflict resolution; other tokens are kept
     * verbatim so they don't interfere with each other.
     *
     * Results are memoised in an LRU cache keyed by the joined input string,
     * so repeated identical calls are effectively free.
     *
     * @param string ...$args  One or more space-separated class strings.
     * @return string          Merged class string with conflicts resolved.
     */
    public function merge(string ...$args): string
    {
        // Join all arguments into one string; the merge algorithm then splits on whitespace.
        $classList = implode(' ', $args);

        // Skip any further work for blank inputs.
        if (trim($classList) === '') {
            return '';
        }

        // Return the cached result if this exact input has been seen before.
        if ($this->cache->has($classList)) {
            return $this->cache->get($classList);
        }

        // Delegate to the stateless merge algorithm, then cache the result.
        $result = MergeClassList::merge($classList, $this->classGroupUtils, $this->prefix);
        $this->cache->set($classList, $result);

        return $result;
    }

    // =========================================================================
    // Static API  (mirrors the JS named exports: twMerge, twJoin, …)
    // =========================================================================

    /**
     * Static shorthand for merge() using a shared no-prefix singleton instance.
     *
     * Equivalent to the JS `twMerge` export.  Convenient when you don't need a
     * custom config — just call TailwindMerge::tw(...) anywhere.
     *
     * The singleton is created lazily on first use.  Call resetInstance() to
     * clear it between tests if you need a clean state.
     *
     * @param string ...$args  One or more space-separated class strings.
     */
    public static function tw(string ...$args): string
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->merge(...$args);
    }

    /**
     * Concatenates class strings WITHOUT any conflict resolution.
     *
     * Equivalent to the JS `twJoin` export.  Useful when you just want to
     * conditionally include classes without triggering the merge logic:
     *
     *   TailwindMerge::join(
     *       'px-4 py-2',
     *       $isLarge  ? 'text-lg'     : '',
     *       $isActive ? 'bg-blue-500' : null,
     *   );
     *
     * Falsy values (null, false, '') are silently dropped.
     *
     * @param mixed ...$args  Strings, nulls, or booleans — anything falsy is ignored.
     */
    public static function join(mixed ...$args): string
    {
        $parts = [];
        foreach ($args as $arg) {
            // Drop null, false, and empty string — keep anything else as a string.
            if ($arg !== null && $arg !== false && $arg !== '') {
                $parts[] = (string) $arg;
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Creates a new TailwindMerge instance with a custom or extended config.
     *
     * Recognised top-level keys in $extension:
     *
     *   'prefix'                         Tailwind prefix, e.g. 'tw' (v4) or 'tw-' (v3).
     *   'cacheSize'                       LRU cache capacity (default: 500).
     *   'classGroups'                     Replaces the entire class-group map.
     *   'conflictingClassGroups'          Replaces the entire conflict map.
     *   'conflictingClassGroupModifiers'  Replaces the postfix-modifier conflict map.
     *   'extend' => [                     Adds to (rather than replaces) the defaults.
     *       'classGroups'            => [...],
     *       'conflictingClassGroups' => [...],
     *   ]
     *
     * EXAMPLES
     * ─────────
     * Add a custom class group so my-size-sm / my-size-lg conflict:
     *
     *   $tw = TailwindMerge::withConfig([
     *       'extend' => [
     *           'classGroups' => [
     *               'my-size' => [['my-size' => ['sm', 'md', 'lg']]],
     *           ],
     *       ],
     *   ]);
     *
     * Use the Tailwind v4 prefix:
     *
     *   $tw = TailwindMerge::withConfig(['prefix' => 'tw']);
     *
     * @param array $extension  Config to layer on top of the defaults.
     */
    public static function withConfig(array $extension): self
    {
        return new self($extension);
    }

    /**
     * Returns the raw default configuration array.
     *
     * Useful for inspecting what groups exist, building plugins, or feeding
     * a modified copy back into new TailwindMerge($modifiedConfig).
     */
    public static function getDefaultConfig(): array
    {
        return DefaultConfig::get();
    }

    /**
     * Deep-merges two config arrays, with $extension taking precedence.
     *
     * Rules:
     *   • Top-level scalar values in $extension overwrite those in $base.
     *   • Top-level array values are shallow-merged (array_merge).
     *   • The special key 'extend' triggers a recursive per-sub-key merge,
     *     so you can add new class groups without losing existing ones.
     *
     * This method is public so plugins and custom code can compose configs
     * without needing to instantiate a full TailwindMerge object.
     *
     * @param array $base       Starting config (typically from getDefaultConfig()).
     * @param array $extension  Overrides / additions to apply on top.
     */
    public static function mergeConfigs(array $base, array $extension): array
    {
        $result = $base;

        foreach ($extension as $key => $value) {
            if ($key === 'extend' && is_array($value)) {
                // 'extend' sub-key: merge each nested section rather than replace it.
                foreach ($value as $extKey => $extValue) {
                    if (isset($result[$extKey]) && is_array($result[$extKey])) {
                        if (is_array($extValue) && !array_is_list($extValue)) {
                            // Associative sub-array (e.g. classGroups) — merge per entry.
                            foreach ($extValue as $k => $v) {
                                if (isset($result[$extKey][$k]) && is_array($result[$extKey][$k]) && is_array($v)) {
                                    $result[$extKey][$k] = array_merge($result[$extKey][$k], $v);
                                } else {
                                    $result[$extKey][$k] = $v;
                                }
                            }
                        } else {
                            // List or scalar — append to the existing array.
                            $result[$extKey] = array_merge($result[$extKey], (array) $extValue);
                        }
                    } else {
                        // Key doesn't exist in base yet — just set it.
                        $result[$extKey] = $extValue;
                    }
                }
            } elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                // Both sides are arrays — shallow merge.
                $result[$key] = array_merge($result[$key], $value);
            } else {
                // Scalar overwrite.
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Resets the shared singleton used by tw().
     *
     * Only needed in tests that call tw() and want a completely fresh instance
     * between test cases (e.g. after changing global state).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
