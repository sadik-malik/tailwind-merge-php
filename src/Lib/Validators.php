<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * Validator functions for class parts.
 * PHP port of tailwind-merge validators.ts
 */
class Validators
{
    /**
     * Returns true for any value (use carefully).
     */
    public static function isAny(string $classPart): bool
    {
        return true;
    }

    /**
     * Returns true if the class part is not an arbitrary value.
     */
    public static function isAnyNonArbitrary(string $classPart): bool
    {
        return !self::isArbitraryValue($classPart) && !self::isArbitraryVariable($classPart);
    }

    /**
     * Matches arbitrary values like [3px], [#fff], [calc(100%-2px)].
     */
    public static function isArbitraryValue(string $classPart): bool
    {
        return (bool) preg_match('/^\[.+\]$/', $classPart);
    }

    /**
     * Matches arbitrary variables like (--my-var).
     */
    public static function isArbitraryVariable(string $classPart): bool
    {
        return (bool) preg_match('/^\(.+\)$/', $classPart);
    }

    /**
     * Matches arbitrary length values: [3%], [4px], [length:var(--my-var)].
     */
    public static function isArbitraryLength(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'length', self::isLengthOnly(...));
    }

    /**
     * Matches arbitrary variable length values like (--my-var).
     */
    public static function isArbitraryVariableLength(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'length');
    }

    /**
     * Matches integer strings like 0, 1, 12, 100.
     */
    public static function isInteger(string $classPart): bool
    {
        return (bool) preg_match('/^\d+$/', $classPart);
    }

    /**
     * Matches arbitrary integer values [450] or [number:var(--value)].
     */
    public static function isArbitraryNumber(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'number', self::isNumber(...));
    }

    /**
     * Matches arbitrary variable number values.
     */
    public static function isArbitraryVariableNumber(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'number');
    }

    /**
     * Matches numeric values including floats: 0, 1.5, 2.5, etc.
     */
    public static function isNumber(string $classPart): bool
    {
        return (bool) preg_match('/^(\d+\.?\d*|\.\d+)$/', $classPart);
    }

    /**
     * Matches percentage values like 50%.
     */
    public static function isPercent(string $classPart): bool
    {
        return (bool) preg_match('/^\d+\.?\d*%$/', $classPart);
    }

    /**
     * Matches arbitrary image values like [url('/img.png')], [linear-gradient(...)].
     */
    public static function isArbitraryImage(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'image', self::isImage(...));
    }

    /**
     * Matches arbitrary variable image values.
     */
    public static function isArbitraryVariableImage(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'image');
    }

    /**
     * Matches arbitrary size values.
     */
    public static function isArbitrarySize(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'size', self::isNever(...));
    }

    /**
     * Matches arbitrary variable size values.
     */
    public static function isArbitraryVariableSize(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'size');
    }

    /**
     * Matches arbitrary position values.
     */
    public static function isArbitraryPosition(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, 'position', self::isNever(...));
    }

    /**
     * Matches arbitrary variable position values.
     */
    public static function isArbitraryVariablePosition(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'position');
    }

    /**
     * Matches arbitrary shadow values.
     */
    public static function isArbitraryShadow(string $classPart): bool
    {
        return self::getIsArbitraryValue($classPart, '', self::isShadow(...));
    }

    /**
     * Matches arbitrary variable shadow values.
     */
    public static function isArbitraryVariableShadow(string $classPart): bool
    {
        return self::getIsArbitraryVariable($classPart, 'shadow');
    }

    /**
     * Matches fraction values like 1/2, 3/4, etc.
     */
    public static function isFraction(string $classPart): bool
    {
        return (bool) preg_match('/^\d+\/\d+$/', $classPart);
    }

    /**
     * Never matches.
     */
    public static function isNever(string $classPart): bool
    {
        return false;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function getIsArbitraryValue(string $classPart, string $label, callable $testValue): bool
    {
        if (!preg_match('/^\[(.+)\]$/', $classPart, $matches)) {
            return false;
        }

        $value = $matches[1];

        if ($label !== '' && str_starts_with($value, $label . ':')) {
            return true;
        }

        // If there's a label prefix and it doesn't match, bail
        if ($label !== '' && preg_match('/^[a-z-]+:/', $value)) {
            return false;
        }

        return $testValue($value);
    }

    private static function getIsArbitraryVariable(string $classPart, string $label): bool
    {
        if (!preg_match('/^\((.+)\)$/', $classPart, $matches)) {
            return false;
        }

        $value = $matches[1];

        if ($label !== '') {
            // When a label is required, ONLY match if the value carries that explicit
            // label prefix — e.g. (length:--my-var) or (position:center).
            // Bare CSS variables like (--brand) must NOT match labelled validators
            // so they fall through to the unlabelled isAny colour fallback instead
            // of being mis-attributed to bg-position / bg-size / bg-image.
            return str_starts_with($value, $label . ':');
        }

        // No label: match any CSS variable (--*) or any labelled value
        return str_starts_with($value, '--') || str_contains($value, ':');
    }

    private static function isLengthOnly(string $value): bool
    {
        // Matches length units: px, em, rem, vh, vw, vmin, vmax, %, cm, mm, in, pt, pc, ex, ch
        return (bool) preg_match('/^\d+\.?\d*(px|em|rem|vh|vw|vmin|vmax|%|cm|mm|in|pt|pc|ex|ch|svh|svw|lvh|lvw|dvh|dvw|cqw|cqh)$/', $value)
            || (bool) preg_match('/^(calc|min|max|clamp)\(.+\)$/', $value);
    }

    private static function isImage(string $value): bool
    {
        return str_starts_with($value, 'image:')
            || str_starts_with($value, 'url:')
            || str_starts_with($value, 'linear-gradient(')
            || str_starts_with($value, 'url(');
    }

    private static function isShadow(string $value): bool
    {
        // Shadow values contain colour or offset patterns
        return (bool) preg_match('/^(inset_)?(\d+\.?\d*(px|rem|em)\s*){2,3}/', $value)
            || str_contains($value, 'rgb')
            || str_contains($value, 'hsl')
            || str_contains($value, '#');
    }
}
