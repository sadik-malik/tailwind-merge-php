<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * Validators — a library of predicate functions used by DefaultConfig.
 *
 * PHP port of tailwind-merge validators.ts.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  HOW VALIDATORS ARE USED                                                │
 * │                                                                         │
 * │  When a class like 'p-4' or 'p-[20px]' is looked up in the trie, the  │
 * │  walker eventually hits a node that has no literal child key for '4'   │
 * │  or '[20px]'.  The __validators__ list on that node is then tried in   │
 * │  order; the first callable that returns true provides the group ID.    │
 * │                                                                         │
 * │  Each validator receives the RECONSTRUCTED SUFFIX — the part of the    │
 * │  class name that comes AFTER the prefix in the trie.  For 'p-4' this  │
 * │  is '4'; for 'grid-cols-[1fr_2fr]' it is '[1fr_2fr]'.                 │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * TWO FAMILIES OF ARBITRARY VALIDATORS
 * ─────────────────────────────────────
 * Tailwind v3 uses square-bracket syntax for arbitrary values:
 *   p-[20px]           isArbitraryLength('[20px]') → true
 *   bg-[#B91C1C]       isAny('[#B91C1C]') → true  (colour, caught by isAny in bg-color)
 *   text-[1.5rem]      isArbitraryLength('[1.5rem]') → true
 *
 * Tailwind v4 also supports parenthesis syntax for CSS variable references:
 *   p-(--my-spacing)   isArbitraryVariable('(--my-spacing)') → true
 *   bg-(--brand)       isAny('(--brand)') → true  (caught by isAny in bg-color)
 *
 * LABEL DISAMBIGUATION
 * ─────────────────────
 * Some arbitrary-value validators require a 'label:' prefix inside the
 * brackets so they can distinguish between e.g. an arbitrary length vs an
 * arbitrary image when both are passed to a 'bg-*' utility:
 *   bg-[length:var(--w)]   → bg-size     (isArbitraryLength matches 'length:…')
 *   bg-[image:url(x.png)]  → bg-image    (isArbitraryImage matches 'image:…')
 *   bg-(--brand)           → bg-color    (no label → falls through to isAny)
 *
 * The getIsArbitraryVariable helper enforces a STRICT label check: when a
 * label is provided it ONLY matches the 'label:value' form.  Bare '--var'
 * references never match a labelled validator, ensuring they fall through
 * to the catch-all isAny in the colour group.
 */
class Validators
{
    // =========================================================================
    // Public validators
    // =========================================================================

    /**
     * Matches any class part — a catch-all used as the last resort.
     *
     * Used in $colors = [$isAny] so that any unknown colour token (including
     * bare CSS variables like '(--brand)' and arbitrary values like '[#abc]')
     * is claimed by the colour group rather than falling through to "unknown".
     *
     * CAUTION: Because it matches everything, it must be registered AFTER all
     * more specific validators in DefaultConfig to avoid shadowing them.
     */
    public static function isAny(string $classPart): bool
    {
        return true;
    }

    /**
     * Matches any class part that is NOT an arbitrary value ([…]) or an
     * arbitrary variable ((…)).
     *
     * Used in contexts like max-w-screen-* where the value must be a plain
     * named token (sm, md, lg, …) rather than an arbitrary expression.
     */
    public static function isAnyNonArbitrary(string $classPart): bool
    {
        return !self::isArbitraryValue($classPart) && !self::isArbitraryVariable($classPart);
    }

    /**
     * Matches Tailwind v3 arbitrary values — anything wrapped in square brackets.
     *
     * Examples: [3px], [#fff], [calc(100%-2px)], [color:red]
     */
    public static function isArbitraryValue(string $classPart): bool
    {
        return (bool) preg_match('/^\[.+\]$/', $classPart);
    }

    /**
     * Matches Tailwind v4 arbitrary CSS variable references — anything in parens.
     *
     * Examples: (--my-var), (--brand-color), (length:--my-length)
     *
     * NOTE: This unlabelled version matches ANY (…) token.  For contexts where
     * a specific type is required (length, image, etc.) use the labelled variants
     * isArbitraryVariableLength, isArbitraryVariableImage, etc.
     */
    public static function isArbitraryVariable(string $classPart): bool
    {
        return (bool) preg_match('/^\(.+\)$/', $classPart);
    }

    /**
     * Matches arbitrary length values in square brackets.
     *
     * Examples: [3px], [4em], [2.5rem], [50%], [length:var(--my-len)]
     * The explicit 'length:' prefix label is also accepted.
     */
    public static function isArbitraryLength(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'length', self::isLengthOnly(...));
    }

    /**
     * Matches arbitrary CSS variable references that are explicitly labelled
     * as lengths, e.g. (length:--my-spacing).
     *
     * Bare CSS variables like (--my-spacing) do NOT match — they fall through
     * to the unlabelled isArbitraryVariable / isAny validators so they are
     * attributed to the correct group (usually colour / spacing).
     */
    public static function isArbitraryVariableLength(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'length');
    }

    /**
     * Matches non-negative integer strings: 0, 1, 12, 100.
     *
     * Used for utilities like z-10, grid-cols-3, line-clamp-4.
     */
    public static function isInteger(string $classPart): bool
    {
        return (bool) preg_match('/^\d+$/', $classPart);
    }

    /**
     * Matches arbitrary numeric values in square brackets.
     *
     * Examples: [450], [number:var(--my-num)]
     * The explicit 'number:' label is also accepted.
     */
    public static function isArbitraryNumber(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'number', self::isNumber(...));
    }

    /**
     * Matches arbitrary CSS variable references labelled as numbers,
     * e.g. (number:--my-weight).
     */
    public static function isArbitraryVariableNumber(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'number');
    }

    /**
     * Matches numeric values including decimals: 0, 1.5, 2.5, .75.
     *
     * Used for utilities that accept a numeric scale value (opacity, scale, etc.).
     */
    public static function isNumber(string $classPart): bool
    {
        return (bool) preg_match('/^(\d+\.?\d*|\.\d+)$/', $classPart);
    }

    /**
     * Matches percentage values: 50%, 12.5%.
     *
     * Used for gradient-color-stop positions like from-50%.
     */
    public static function isPercent(string $classPart): bool
    {
        return (bool) preg_match('/^\d+\.?\d*%$/', $classPart);
    }

    /**
     * Matches arbitrary image values in square brackets.
     *
     * Detected by a leading 'image:' label, 'url:' prefix, 'url(', or
     * 'linear-gradient(' — all of which indicate a CSS image/gradient value.
     * Used to disambiguate bg-[image:…] from bg-[length:…] and bg-[#colour].
     */
    public static function isArbitraryImage(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'image', self::isImage(...));
    }

    /**
     * Matches arbitrary CSS variable references labelled as images,
     * e.g. (image:--my-bg-image).
     */
    public static function isArbitraryVariableImage(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'image');
    }

    /**
     * Matches arbitrary size values in square brackets with a 'size:' label,
     * e.g. [size:200px_100px] for background-size.
     *
     * The $testValue callback is isNever so unlabelled values never match
     * — only explicit 'size:…' labels are accepted.
     */
    public static function isArbitrarySize(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'size', self::isNever(...));
    }

    /**
     * Matches arbitrary CSS variable references labelled as sizes,
     * e.g. (size:--my-bg-size).
     */
    public static function isArbitraryVariableSize(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'size');
    }

    /**
     * Matches arbitrary position values in square brackets with a 'position:' label,
     * e.g. [position:center_top] for background-position.
     *
     * Only 'position:…' labelled values match — the isNever callback ensures
     * unlabelled arbitrary values don't fall into this group.
     */
    public static function isArbitraryPosition(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'position', self::isNever(...));
    }

    /**
     * Matches arbitrary CSS variable references labelled as positions,
     * e.g. (position:--my-bg-pos).
     */
    public static function isArbitraryVariablePosition(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'position');
    }

    /**
     * Matches arbitrary shadow values in square brackets.
     *
     * Shadow values are detected by looking for offset/colour patterns inside
     * the brackets (see isShadow below).  Unlike other typed arbitrary values,
     * shadow has no explicit label — the heuristic is used directly.
     */
    public static function isArbitraryShadow(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, '', self::isShadow(...));
    }

    /**
     * Matches arbitrary CSS variable references labelled as shadows,
     * e.g. (shadow:--my-box-shadow).
     */
    public static function isArbitraryVariableShadow(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'shadow');
    }

    /**
     * Matches fraction values like 1/2, 3/4, 5/6.
     *
     * Used for size utilities (w-1/2, h-3/4) and spacing fractions.
     */
    public static function isFraction(string $classPart): bool
    {
        return (bool) preg_match('/^\d+\/\d+$/', $classPart);
    }

    /**
     * Never matches — used as a no-op $testValue in getIsArbitraryValue when
     * a group should ONLY match via an explicit type label (size:, position:).
     */
    public static function isNever(string $classPart): bool
    {
        return false;
    }

    // =========================================================================
    // Private helper infrastructure
    // =========================================================================

    /**
     * Tests whether $classPart is an arbitrary VALUE (square brackets) that
     * belongs to the category identified by $label.
     *
     * Detection logic:
     *   1. The part must match /^\[.+\]$/.
     *   2. If $label is non-empty and the content starts with '$label:', → true.
     *   3. If $label is non-empty and the content starts with ANY other label
     *      (detected by /^[a-z-]+:/), → false  (belongs to a different category).
     *   4. Otherwise call $testValue on the raw content for heuristic matching.
     *
     * EXAMPLE (label='length'):
     *   '[3px]'            → step 4: isLengthOnly('3px') → true   ✓
     *   '[length:var(--x)]'→ step 2: starts with 'length:' → true ✓
     *   '[image:url(x)]'   → step 3: other label found → false    ✓
     *   '[#abc]'           → step 4: isLengthOnly('#abc') → false  ✓
     *
     * @param string   $classPart  The class suffix to test.
     * @param string   $label      Expected type label, or '' for shadow (heuristic only).
     * @param callable $testValue  Heuristic applied to the raw content when no label matched.
     */
    private static function getIsArbitraryValue(string $classPart, string $label, callable $testValue): bool
    {
        if (!preg_match('/^\[(.+)\]$/', $classPart, $matches)) {
            return false; // not an arbitrary value at all
        }

        $value = $matches[1]; // content inside the brackets

        // Explicit matching label wins immediately.
        if ($label !== '' && str_starts_with($value, $label . ':')) {
            return true;
        }

        // If there is a different label prefix, this value belongs to another category.
        if ($label !== '' && preg_match('/^[a-z-]+:/', $value)) {
            return false;
        }

        // No label — fall back to the heuristic.
        return $testValue($value);
    }

    /**
     * Tests whether $classPart is an arbitrary VARIABLE (parenthesis syntax) that
     * belongs to the category identified by $label.
     *
     * CRITICAL DESIGN DECISION — bare CSS variables must not be claimed by labelled groups:
     *
     *   bg-(--brand)   should go to bg-color   (isAny catches it)
     *                  NOT to bg-position, bg-size, bg-image
     *
     * If the label is non-empty we ONLY match when the content explicitly starts with
     * 'label:'.  A bare '--var' reference does NOT match a labelled validator.
     * This guarantees that (--brand) falls through all typed validators and is
     * eventually caught by the unlabelled isAny in the colour group.
     *
     * When label is empty (''), any CSS variable (--*) or any labelled expression
     * matches — this is used by the unlabelled isArbitraryVariable itself.
     *
     * EXAMPLES with label='position':
     *   '(--brand)'          → value='--brand', starts with 'position:'? No → false ✓
     *   '(position:center)'  → value='position:center', starts with 'position:'? Yes → true ✓
     *
     * EXAMPLES with label='':
     *   '(--brand)'    → starts with '--'? Yes → true ✓
     *   '(length:--x)' → contains ':'? Yes → true ✓
     *
     * @param string $classPart  The class suffix to test.
     * @param string $label      Expected type label, or '' for the unlabelled catch-all.
     */
    private static function getIsArbitraryVariable(string $classPart, string $label): bool
    {
        if (!preg_match('/^\((.+)\)$/', $classPart, $matches)) {
            return false; // not parenthesis syntax
        }

        $value = $matches[1]; // content inside the parentheses

        if ($label !== '') {
            // strict mode: only the explicit 'label:value' form matches.
            // Bare CSS variables MUST NOT match here — they will fall through to isAny.
            return str_starts_with($value, $label . ':');
        }

        // Unlabelled mode: match any CSS variable (--*) or any labelled expression.
        return str_starts_with($value, '--') || str_contains($value, ':');
    }

    /**
     * Returns true if $value is a CSS length with a recognised unit.
     *
     * Units covered: absolute (px, cm, mm, in, pt, pc), relative (em, rem, ex, ch),
     * viewport (vh, vw, vmin, vmax, svh, svw, lvh, lvw, dvh, dvw),
     * container query (cqw, cqh), percentage (%), and CSS math functions
     * (calc(), min(), max(), clamp()).
     *
     * Used as the $testValue callback in isArbitraryLength.
     */
    private static function isLengthOnly(string $value): bool
    {
        return (bool) preg_match(
            '/^\d+\.?\d*(px|em|rem|vh|vw|vmin|vmax|%|cm|mm|in|pt|pc|ex|ch|svh|svw|lvh|lvw|dvh|dvw|cqw|cqh)$/',
            $value
        ) || (bool) preg_match('/^(calc|min|max|clamp)\(.+\)$/', $value);
    }

    /**
     * Returns true if $value looks like a CSS image expression.
     *
     * Detection is based on the leading token:
     *   'image:…'             explicit type label
     *   'url:…'               URL reference (shorthand)
     *   'linear-gradient(…'   CSS gradient function
     *   'url(…'               CSS url() function
     *
     * Used as the $testValue callback in isArbitraryImage.
     */
    private static function isImage(string $value): bool
    {
        return str_starts_with($value, 'image:')
            || str_starts_with($value, 'url:')
            || str_starts_with($value, 'linear-gradient(')
            || str_starts_with($value, 'url(');
    }

    /**
     * Returns true if $value looks like a CSS box-shadow expression.
     *
     * Heuristic: a shadow value typically contains numeric offsets followed by
     * a length unit, or uses rgb/hsl colour functions, or starts with '#'.
     *
     * Examples that match: '0_2px_4px_rgba(0,0,0,0.1)', 'inset_0_1px_2px_#000'
     *
     * Used as the $testValue callback in isArbitraryShadow.
     */
    private static function isShadow(string $value): bool
    {
        return (bool) preg_match('/^(inset_)?(\d+\.?\d*(px|rem|em)\s*){2,3}/', $value)
            || str_contains($value, 'rgb')
            || str_contains($value, 'hsl')
            || str_contains($value, '#');
    }
}
