<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * ParseClassName — splits a single Tailwind class token into its components.
 *
 * PHP port of tailwind-merge parse-class-name.ts.
 *
 * A Tailwind class is composed of up to four parts:
 *
 *   [variants:]  [!]  baseClass  [/postfix]
 *
 * Examples:
 *   hover:focus:!bg-red-500/50
 *   │────────│ │ │────────│ ││
 *   variants  !  base      /50  ← postfix (opacity modifier)
 *
 *   tw:hover:p-4        ← v4 variant-style prefix 'tw'
 *   hover:tw-bg-red-500 ← v3 dash-style prefix 'tw-'
 *
 * VARIANT PARSING RULE
 * ────────────────────
 * The class is scanned character-by-character.  A ':' outside of [...] or (...)
 * brackets is treated as a variant separator.  This ensures that the colon
 * inside arbitrary variants is not split:
 *   [&:hover]:p-4   → modifiers=['[&:hover]'], base='p-4'  ✓
 *   [&:nth-child(3)]:p-4  → modifiers=['[&:nth-child(3)]'], base='p-4'  ✓
 */
class ParseClassName
{
    /** The '!' character that marks an important utility, e.g. '!p-4'. */
    public const IMPORTANT_MODIFIER = '!';

    /** The ':' character used to separate variant modifiers from the base class. */
    private const MODIFIER_SEPARATOR = ':';

    /**
     * Parses a single Tailwind class name into its structural components.
     *
     * When a non-empty $prefix is supplied, it is detected and stripped from the
     * parsed result so that ClassGroupUtils always receives bare Tailwind utility
     * names (e.g. 'p-4') regardless of any configured prefix.
     *
     * PREFIX STRIPPING
     * ────────────────
     * v4 variant-style (prefix has NO trailing dash, e.g. 'tw'):
     *   The prefix token appears as one of the modifier segments.
     *   "tw:p-4"       → modifiers=[], base="p-4", hasPrefix=true
     *   "hover:tw:p-4" → modifiers=['hover'], base="p-4", hasPrefix=true
     *   "tw:hover:p-4" → modifiers=['hover'], base="p-4", hasPrefix=true
     *   "hover:p-4"    → modifiers=['hover'], base="p-4", hasPrefix=false
     *
     * v3 dash-style (prefix has a trailing dash, e.g. 'tw-'):
     *   The prefix is prepended to the base class name.
     *   "tw-p-4"        → modifiers=[], base="p-4", hasPrefix=true
     *   "hover:tw-p-4"  → modifiers=['hover'], base="p-4", hasPrefix=true
     *
     * If neither style matches, hasPrefix=false and the class is treated as
     * a non-Tailwind token (kept verbatim by MergeClassList).
     *
     * @param string $className  The full class token, e.g. 'hover:!bg-red-500/50'.
     * @param string $prefix     Optional configured prefix ('tw', 'tw-', or '').
     *
     * @return array{
     *   modifiers: string[],               Variant modifier tokens, e.g. ['hover','focus'].
     *   hasImportantModifier: bool,         True when the class begins with '!'.
     *   baseClassName: string,              The bare utility, e.g. 'bg-red-500/50'.
     *   maybePostfixModifierPosition: int|null,  Byte offset of '/' in baseClassName,
     *                                            or null if no postfix was found.
     *   hasPrefix: bool                     True when the configured prefix was found
     *                                       and stripped from this class.
     * }
     */
    public static function parseClassName(string $className, string $prefix = ''): array
    {
        $modifiers   = [];
        $bracketDepth = 0; // depth inside [...]
        $parenDepth   = 0; // depth inside (...)
        $modifierStart = 0;
        $postfixModifierPosition = null;

        $len = strlen($className);

        // Single-pass scan: track bracket depth so we never split on ':' or '/'
        // that are inside arbitrary-value or arbitrary-variable expressions.
        for ($index = 0; $index < $len; $index++) {
            $char = $className[$index];

            if ($bracketDepth === 0 && $parenDepth === 0) {
                if ($char === self::MODIFIER_SEPARATOR) {
                    // Found a top-level ':' — everything from $modifierStart to here
                    // is one modifier token (e.g. 'hover', 'md', '[&:nth-child(2)]').
                    $modifiers[]   = substr($className, $modifierStart, $index - $modifierStart);
                    $modifierStart = $index + 1;
                } elseif ($char === '/') {
                    // Record the position of the last '/' at the top level.
                    // This may be the opacity/postfix separator (e.g. bg-red-500/50).
                    // The last '/' wins if there are multiple (edge case for gradients).
                    $postfixModifierPosition = $index;
                }
            }

            // Track nesting depth to skip colons/slashes inside bracket expressions.
            if ($char === '[')      { $bracketDepth++; }
            elseif ($char === ']')  { $bracketDepth--; }
            elseif ($char === '(')  { $parenDepth++; }
            elseif ($char === ')')  { $parenDepth--; }
        }

        // Everything after the last ':' is the base class (plus optional '!' prefix).
        $baseClassNameWithImportantModifier = count($modifiers) === 0
            ? $className
            : substr($className, $modifierStart);

        // Strip the leading '!' if present and record the flag.
        $hasImportantModifier = str_starts_with($baseClassNameWithImportantModifier, self::IMPORTANT_MODIFIER);
        $baseClassName        = $hasImportantModifier
            ? substr($baseClassNameWithImportantModifier, 1)
            : $baseClassNameWithImportantModifier;

        // Convert the absolute postfix position to an offset relative to the
        // start of baseClassName (accounting for the '!' that may have been stripped).
        $maybePostfixModifierPosition = $postfixModifierPosition !== null
            ? $postfixModifierPosition - $modifierStart - ($hasImportantModifier ? 1 : 0)
            : null;

        // ── Prefix stripping ──────────────────────────────────────────────────
        $hasPrefix = false;

        if ($prefix !== '') {
            [$baseClassName, $modifiers, $hasPrefix] = self::stripPrefix(
                $baseClassName,
                $modifiers,
                $prefix
            );
        }

        return [
            'modifiers'                    => $modifiers,
            'hasImportantModifier'         => $hasImportantModifier,
            'baseClassName'                => $baseClassName,
            'maybePostfixModifierPosition' => $maybePostfixModifierPosition,
            'hasPrefix'                    => $hasPrefix,
        ];
    }

    /**
     * Strips a configured Tailwind prefix from either the modifiers array
     * (v4 variant-style) or the base class name (v3 dash-style).
     *
     * Returns [$newBaseClassName, $newModifiers, $hasPrefix].
     *
     * @param string   $baseClassName  The base class after '!' has been removed.
     * @param string[] $modifiers      The collected modifier tokens.
     * @param string   $prefix         The configured prefix (e.g. 'tw' or 'tw-').
     */
    private static function stripPrefix(
        string $baseClassName,
        array  $modifiers,
        string $prefix
    ): array {
        // ── v4 variant-style ──────────────────────────────────────────────────
        // The prefix appears as a modifier token, e.g.:
        //   class="tw:p-4"        → modifiers collected as ['tw'],  base='p-4'
        //   class="hover:tw:p-4"  → modifiers=['hover','tw'], base='p-4'
        //   class="tw:hover:p-4"  → modifiers=['tw','hover'], base='p-4'
        // Strip it wherever it sits in the list; the remaining modifiers (hover
        // etc.) are kept so the conflict key is built correctly.
        $variantPrefix = $prefix; // no trailing colon — modifier tokens don't include it
        $prefixIndex   = array_search($variantPrefix, $modifiers, true);

        if ($prefixIndex !== false) {
            array_splice($modifiers, $prefixIndex, 1);
            return [$baseClassName, $modifiers, true];
        }

        // ── v3 dash-style ─────────────────────────────────────────────────────
        // The prefix is prepended to the base class with a dash, e.g.:
        //   class="tw-p-4"       → base starts with 'tw-', strip → 'p-4'
        //   class="hover:tw-p-4" → modifiers=['hover'], base starts with 'tw-'
        //
        // Accept the prefix with or without a trailing dash:
        //   prefix='tw-' → dashPrefix='tw-'   (already has dash)
        //   prefix='tw'  → dashPrefix='tw-'   (auto-append dash for v3 detection)
        $dashPrefix = str_ends_with($prefix, '-') ? $prefix : $prefix . '-';

        if (str_starts_with($baseClassName, $dashPrefix)) {
            return [substr($baseClassName, strlen($dashPrefix)), $modifiers, true];
        }

        // Neither style matched — this class doesn't carry the configured prefix.
        return [$baseClassName, $modifiers, false];
    }

    /**
     * Sorts a list of modifier tokens into a canonical order so that
     * 'hover:focus:p-4' and 'focus:hover:p-4' produce the same conflict key.
     *
     * SORTING RULES
     * ─────────────
     * • Standard string modifiers (hover, focus, md, dark, …) are sorted
     *   alphabetically within their run.
     * • Arbitrary variants ([&:hover], [.parent_&], …) break a run and are
     *   kept in their original insertion order relative to other arbitrary
     *   variants, because their order can be semantically significant.
     *
     * Example:
     *   ['hover', 'focus', '[&:nth-child(2)]', 'lg']
     *   → sorted standard run ['hover','focus'] → ['focus','hover']
     *   → arbitrary variant breaks the run, kept as-is
     *   → sorted standard run ['lg'] → ['lg']
     *   Result: ['focus', 'hover', '[&:nth-child(2)]', 'lg']
     *
     * @param string[] $modifiers  The raw modifier tokens from parseClassName().
     * @return string[]            Canonically sorted modifier tokens.
     */
    public static function sortModifiers(array $modifiers): array
    {
        if (count($modifiers) <= 1) {
            return $modifiers; // nothing to sort
        }

        $sortedModifiers   = [];
        $unsortedModifiers = []; // accumulates standard modifiers between arbitrary ones

        foreach ($modifiers as $modifier) {
            if (str_contains($modifier, '[') || str_contains($modifier, '(')) {
                // Arbitrary variant encountered — flush any pending standard modifiers
                // sorted alphabetically, then append this arbitrary variant in-place.
                if (!empty($unsortedModifiers)) {
                    sort($unsortedModifiers);
                    $sortedModifiers   = array_merge($sortedModifiers, $unsortedModifiers);
                    $unsortedModifiers = [];
                }
                $sortedModifiers[] = $modifier;
            } else {
                // Standard modifier — accumulate for later sort.
                $unsortedModifiers[] = $modifier;
            }
        }

        // Flush any remaining standard modifiers.
        if (!empty($unsortedModifiers)) {
            sort($unsortedModifiers);
            $sortedModifiers = array_merge($sortedModifiers, $unsortedModifiers);
        }

        return $sortedModifiers;
    }
}
