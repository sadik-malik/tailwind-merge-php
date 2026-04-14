<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * Core merge logic.
 * PHP port of tailwind-merge merge-classlist.ts
 *
 * When a prefix is configured (v3 dash-style or v4 variant-style) only classes
 * that carry that prefix are subject to conflict resolution. Unprefixed classes
 * are passed through untouched, just like unknown classes.
 */
class MergeClassList
{
    /**
     * Merges a class list string, resolving conflicts so that later classes win.
     *
     * @param string          $classList       Space-separated class string
     * @param ClassGroupUtils $classGroupUtils Lookup helper built from config
     * @param string          $prefix          Optional Tailwind prefix (e.g. 'tw' or 'tw-')
     */
    public static function merge(
        string $classList,
        ClassGroupUtils $classGroupUtils,
        string $prefix = ''
    ): string {
        $classNames = preg_split('/\s+/', trim($classList));

        if ($classNames === false || $classNames === ['']) {
            return '';
        }

        // O(1) hash map for conflict tracking
        $classGroupsInConflict = [];
        $result = [];

        $classNames = array_values(array_filter($classNames, fn($c) => $c !== ''));
        $total = count($classNames);

        for ($i = $total - 1; $i >= 0; $i--) {
            $originalClassName = $classNames[$i];

            $parsed = ParseClassName::parseClassName($originalClassName, $prefix);
            [
                'modifiers'                    => $modifiers,
                'hasImportantModifier'         => $hasImportantModifier,
                'baseClassName'                => $baseClassName,
                'maybePostfixModifierPosition' => $maybePostfixModifierPosition,
                'hasPrefix'                    => $hasPrefix,
            ] = $parsed;

            // When a prefix is configured, skip classes that don't carry it —
            // they are unknown from tailwind-merge's perspective and kept as-is.
            if ($prefix !== '' && !$hasPrefix) {
                array_unshift($result, $originalClassName);
                continue;
            }

            $hasPostfixModifier = $maybePostfixModifierPosition !== null;

            // Try to find group ID with postfix stripped, then without
            $classGroupId = null;

            if ($hasPostfixModifier) {
                $baseWithoutPostfix = substr($baseClassName, 0, $maybePostfixModifierPosition);
                $classGroupId = $classGroupUtils->getClassGroupId($baseWithoutPostfix);
            }

            if ($classGroupId === null) {
                $classGroupId = $classGroupUtils->getClassGroupId($baseClassName);
                $hasPostfixModifier = false;
            }

            if ($classGroupId === null) {
                // Last chance: arbitrary property classes like [font-size:1rem] or [--var:value]
                // These have no trie entry but share a group based on the property name.
                $classGroupId = self::getArbitraryPropertyGroupId($baseClassName);
            }

            if ($classGroupId === null) {
                // Truly unknown class — preserve it verbatim
                array_unshift($result, $originalClassName);
                continue;
            }

            // Build conflict key: {important?}{sortedVariants:}groupId
            $sortedModifiers = ParseClassName::sortModifiers($modifiers);
            $variantPrefix   = count($sortedModifiers) > 0
                ? implode(':', $sortedModifiers) . ':'
                : '';
            $importantPrefix = $hasImportantModifier ? ParseClassName::IMPORTANT_MODIFIER : '';
            $modifierKey     = $importantPrefix . $variantPrefix;
            $conflictKey     = $modifierKey . $classGroupId;

            if (isset($classGroupsInConflict[$conflictKey])) {
                continue; // overridden by a later class
            }

            $classGroupsInConflict[$conflictKey] = true;

            foreach ($classGroupUtils->getConflictingClassGroupIds($classGroupId, $hasPostfixModifier) as $conflictGroupId) {
                $classGroupsInConflict[$modifierKey . $conflictGroupId] = true;
            }

            array_unshift($result, $originalClassName);
        }

        return implode(' ', $result);
    }

    /**
     * Detects arbitrary property classes and returns a group ID based on the
     * CSS property or custom property name, so that duplicate declarations
     * of the same property resolve correctly.
     *
     * '[font-size:1rem]'         → 'arbitrary..font-size'
     * '[--grid-column-span:12]'  → 'arbitrary..--grid-column-span'
     * '[#B91C1C]'                → null  (arbitrary value, not a property)
     * '[&:hover]'                → null  (arbitrary variant selector)
     */
    private static function getArbitraryPropertyGroupId(string $baseClassName): ?string
    {
        // Must be wrapped in square brackets
        if (!preg_match('/^\[(.+)\]$/', $baseClassName, $outer)) {
            return null;
        }

        $content = $outer[1];

        // Must contain a colon that separates property from value
        $colonPos = strpos($content, ':');
        if ($colonPos === false) {
            return null;
        }

        $property = substr($content, 0, $colonPos);

        // Valid CSS property names: letters and hyphens only (e.g. font-size, margin-top)
        // Valid CSS custom properties: start with --
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $property) || str_starts_with($property, '--')) {
            return 'arbitrary..' . $property;
        }

        return null;
    }
}
