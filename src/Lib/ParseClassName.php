<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * PHP port of tailwind-merge parse-class-name.ts
 *
 * Supports two prefix styles:
 *   - v4 variant-style:  tw:flex   tw:hover:bg-red-500   hover:tw:bg-red-500
 *   - v3 dash-style:     tw-flex   hover:tw-bg-red-500
 */
class ParseClassName
{
    public const IMPORTANT_MODIFIER = '!';
    private const MODIFIER_SEPARATOR = ':';

    /**
     * Parses a single Tailwind class name into its components.
     *
     * When a $prefix is supplied the method strips it from the class so that
     * group lookup always works against bare Tailwind utility names.
     *
     * v4 variant-style prefix (e.g. 'tw'):
     *   "tw:p-4"            → modifiers=[], base="p-4",    hasPrefix=true
     *   "hover:tw:p-4"      → modifiers=['hover'], base="p-4", hasPrefix=true
     *   "tw:hover:p-4"      → modifiers=['hover'], base="p-4", hasPrefix=true
     *   "hover:p-4"         → modifiers=['hover'], base="p-4", hasPrefix=false (not a prefixed class)
     *
     * v3 dash-style prefix (e.g. 'tw-'):
     *   "tw-p-4"            → modifiers=[], base="p-4",    hasPrefix=true
     *   "hover:tw-p-4"      → modifiers=['hover'], base="p-4", hasPrefix=true
     *
     * @return array{
     *   modifiers: string[],
     *   hasImportantModifier: bool,
     *   baseClassName: string,
     *   maybePostfixModifierPosition: int|null,
     *   hasPrefix: bool
     * }
     */
    public static function parseClassName(string $className, string $prefix = ''): array
    {
        $modifiers = [];
        $bracketDepth = 0;
        $parenDepth   = 0;
        $modifierStart = 0;
        $postfixModifierPosition = null;

        $len = strlen($className);

        for ($index = 0; $index < $len; $index++) {
            $char = $className[$index];

            if ($bracketDepth === 0 && $parenDepth === 0) {
                if ($char === self::MODIFIER_SEPARATOR) {
                    $modifiers[] = substr($className, $modifierStart, $index - $modifierStart);
                    $modifierStart = $index + 1;
                } elseif ($char === '/') {
                    $postfixModifierPosition = $index;
                }
            }

            if ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth--;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth--;
            }
        }

        $baseClassNameWithImportantModifier = count($modifiers) === 0
            ? $className
            : substr($className, $modifierStart);

        $hasImportantModifier = str_starts_with($baseClassNameWithImportantModifier, self::IMPORTANT_MODIFIER);
        $baseClassName = $hasImportantModifier
            ? substr($baseClassNameWithImportantModifier, 1)
            : $baseClassNameWithImportantModifier;

        // Postfix modifier (like /50 in bg-red-500/50) position relative to base
        $maybePostfixModifierPosition = $postfixModifierPosition !== null
            ? $postfixModifierPosition - $modifierStart - ($hasImportantModifier ? 1 : 0)
            : null;

        // ── Prefix handling ─────────────────────────────────────────────────
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
     * Strip the prefix from either the base class name (v3 dash-style) or
     * from the modifiers list (v4 variant-style).
     *
     * Returns [$newBase, $newModifiers, $hasPrefix].
     */
    private static function stripPrefix(
        string $baseClassName,
        array  $modifiers,
        string $prefix
    ): array {
        // ── v4 variant-style: prefix appears as a modifier segment ──────────
        // e.g. prefix="tw", class="tw:p-4"   → modifiers=["tw"], base="p-4"
        //                    class="hover:tw:p-4" → modifiers=["hover","tw"], base="p-4"
        //                    class="tw:hover:p-4" → modifiers=["tw","hover"], base="p-4"
        $variantPrefix = $prefix; // no trailing colon in the modifier token
        $prefixIndex = array_search($variantPrefix, $modifiers, true);
        if ($prefixIndex !== false) {
            // Remove the prefix token from modifiers; the base stays the same
            array_splice($modifiers, $prefixIndex, 1);
            return [$baseClassName, $modifiers, true];
        }

        // ── v3 dash-style: prefix prepended to base class name ───────────────
        // e.g. prefix="tw-", class="tw-p-4" → base="p-4"
        // The user supplies the prefix WITH the dash: prefix="tw-"
        // But we also handle when they supply it WITHOUT: prefix="tw" → try "tw-"
        $dashPrefix = str_ends_with($prefix, '-') ? $prefix : $prefix . '-';
        if (str_starts_with($baseClassName, $dashPrefix)) {
            return [substr($baseClassName, strlen($dashPrefix)), $modifiers, true];
        }

        // Class has no prefix — treat as unknown (not a Tailwind class for this config)
        return [$baseClassName, $modifiers, false];
    }

    /**
     * Sorts modifiers for consistent conflict key generation.
     * Regular modifiers (hover, focus, md…) are sorted alphabetically.
     * Arbitrary variants ([&:hover]) keep their relative insertion order.
     */
    public static function sortModifiers(array $modifiers): array
    {
        if (count($modifiers) <= 1) {
            return $modifiers;
        }

        $sortedModifiers   = [];
        $unsortedModifiers = [];

        foreach ($modifiers as $modifier) {
            if (str_contains($modifier, '[') || str_contains($modifier, '(')) {
                if (!empty($unsortedModifiers)) {
                    sort($unsortedModifiers);
                    $sortedModifiers = array_merge($sortedModifiers, $unsortedModifiers);
                    $unsortedModifiers = [];
                }
                $sortedModifiers[] = $modifier;
            } else {
                $unsortedModifiers[] = $modifier;
            }
        }

        if (!empty($unsortedModifiers)) {
            sort($unsortedModifiers);
            $sortedModifiers = array_merge($sortedModifiers, $unsortedModifiers);
        }

        return $sortedModifiers;
    }
}
