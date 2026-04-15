<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * MergeClassList — the core conflict-resolution algorithm.
 *
 * PHP port of tailwind-merge merge-classlist.ts.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  ALGORITHM                                                              │
 * │                                                                         │
 * │  Given: "px-2 py-1 bg-red hover:bg-dark-red p-3 bg-[#B91C1C]"          │
 * │                                                                         │
 * │  1. Split on whitespace → individual class tokens.                      │
 * │  2. Iterate RIGHT-TO-LEFT (later = higher priority).                    │
 * │  3. For each class:                                                     │
 * │     a. Parse → variant modifiers + important flag + base class.        │
 * │     b. Look up base class in the trie → class-group ID.                │
 * │     c. Build a conflict key:                                            │
 * │           {!}{sorted-variants:}{groupId}                               │
 * │        e.g.  'bg-color'  or  '!p'  or  'hover:focus:text-color'       │
 * │     d. If the key is already in the "seen" set → this class is         │
 * │        overridden; skip it.                                            │
 * │     e. Otherwise mark the key AND all conflicting group keys as seen,  │
 * │        then prepend the class to the output list.                      │
 * │  4. Return the output list joined with spaces.                         │
 * │                                                                         │
 * │  Result: "hover:bg-dark-red p-3 bg-[#B91C1C]"                          │
 * │  (px-2 and py-1 dropped by p-3; bg-red dropped by bg-[#B91C1C])        │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * RESPONSIBILITY CONTRACT
 * ───────────────────────
 * This class is a pure orchestrator.  It has NO knowledge of what constitutes
 * a valid class group — that belongs entirely to ClassGroupUtils.  It only:
 *   • Calls ParseClassName to split each token into its parts.
 *   • Calls ClassGroupUtils::getClassGroupId() to ask "what group is this?".
 *   • Manages the conflict-key set and builds the output.
 */
class MergeClassList
{
    /**
     * Merges a space-separated Tailwind class string, resolving conflicts.
     *
     * @param string          $classList        Space-separated class tokens.
     * @param ClassGroupUtils $classGroupUtils  Trie-based group resolver.
     * @param string          $prefix           Optional Tailwind prefix ('tw', 'tw-', …).
     *                                          When non-empty, only prefixed classes
     *                                          enter conflict resolution; all others
     *                                          are passed through verbatim.
     * @return string  Merged class string with conflicts resolved.
     */
    public static function merge(
        string $classList,
        ClassGroupUtils $classGroupUtils,
        string $prefix = ''
    ): string {
        // Split on any whitespace (spaces, tabs, newlines).
        $classNames = preg_split('/\s+/', trim($classList));

        if ($classNames === false || $classNames === ['']) {
            return '';
        }

        // ── Conflict tracking ─────────────────────────────────────────────────
        // Keys are conflict strings of the form "{!?}{variants:}{groupId}".
        // Using a hash-map (isset) gives O(1) lookup vs O(n) in_array.
        $classGroupsInConflict = [];

        // Output list; classes are prepended as we scan right-to-left so the
        // final array is already in the original left-to-right display order.
        $result = [];

        $classNames = array_values(array_filter($classNames, fn($c) => $c !== ''));
        $total      = count($classNames);

        // ── Main loop: right-to-left ───────────────────────────────────────────
        // Right-to-left means the LAST occurrence of any conflict group is
        // always processed first and recorded as the "winner".  Earlier classes
        // in the same group are then skipped when we reach them.
        for ($i = $total - 1; $i >= 0; $i--) {
            $originalClassName = $classNames[$i];

            // ── Step 1: Parse the class token ──────────────────────────────────
            // Returns: variant modifiers (hover, md, …), whether '!' is present,
            // the bare base class name, an optional /postfix position, and
            // whether the configured prefix was found and stripped.
            $parsed = ParseClassName::parseClassName($originalClassName, $prefix);
            [
                'modifiers'                    => $modifiers,
                'hasImportantModifier'         => $hasImportantModifier,
                'baseClassName'                => $baseClassName,
                'maybePostfixModifierPosition' => $maybePostfixModifierPosition,
                'hasPrefix'                    => $hasPrefix,
            ] = $parsed;

            // ── Step 2: Prefix gate ────────────────────────────────────────────
            // When a prefix is configured, any class that does NOT carry it is
            // treated as a non-Tailwind token and kept as-is without conflict
            // resolution (e.g. a custom CSS class or a third-party utility).
            if ($prefix !== '' && !$hasPrefix) {
                array_unshift($result, $originalClassName);
                continue;
            }

            // ── Step 3: Group lookup ───────────────────────────────────────────
            // Some classes use a /postfix for opacity variants (bg-red-500/50).
            // Try the base without the postfix first; if that finds a group the
            // /50 part is a legitimate postfix modifier and we record that fact.
            $hasPostfixModifier = $maybePostfixModifierPosition !== null;
            $classGroupId       = null;

            if ($hasPostfixModifier) {
                // e.g. 'bg-red-500' extracted from 'bg-red-500/50'
                $baseWithoutPostfix = substr($baseClassName, 0, $maybePostfixModifierPosition);
                $classGroupId       = $classGroupUtils->getClassGroupId($baseWithoutPostfix);
            }

            if ($classGroupId === null) {
                // Either no postfix, or the postfix-stripped form wasn't found.
                // Try the full base class name instead.
                $classGroupId       = $classGroupUtils->getClassGroupId($baseClassName);
                $hasPostfixModifier = false; // postfix wasn't meaningful
            }

            if ($classGroupId === null) {
                // ClassGroupUtils returned null for both attempts — this class
                // is not a recognised Tailwind utility; keep it verbatim.
                array_unshift($result, $originalClassName);
                continue;
            }

            // ── Step 4: Build the conflict key ─────────────────────────────────
            // The key uniquely identifies a "slot" in the final class list.
            // Two classes that produce the same key are in conflict; the later
            // one (already added) wins and the earlier one is dropped.
            //
            // Format:  {!?}{sortedVariant1:sortedVariant2:…}{groupId}
            //
            // Modifiers are sorted alphabetically so that "hover:focus:p" and
            // "focus:hover:p" are treated as the same conflict.  Arbitrary
            // variants ([&:nth-child(2)]) keep their insertion order.
            $sortedModifiers = ParseClassName::sortModifiers($modifiers);
            $variantPrefix   = count($sortedModifiers) > 0
                ? implode(':', $sortedModifiers) . ':'
                : '';
            $importantPrefix = $hasImportantModifier ? ParseClassName::IMPORTANT_MODIFIER : '';
            $modifierKey     = $importantPrefix . $variantPrefix;
            $conflictKey     = $modifierKey . $classGroupId;

            // ── Step 5: Skip overridden classes ────────────────────────────────
            // If this conflict key was already recorded, a LATER class (already
            // in $result) controls this slot — drop the current class.
            if (isset($classGroupsInConflict[$conflictKey])) {
                continue;
            }

            // ── Step 6: Record the winner and its related conflicts ─────────────
            // Mark this group as taken.
            $classGroupsInConflict[$conflictKey] = true;

            // Also mark any groups that this one is known to conflict with
            // (e.g. 'p' marks 'px', 'py', 'pt', 'pr', 'pb', 'pl' as taken).
            // $hasPostfixModifier triggers extra conflicts via conflictingClassGroupModifiers
            // (e.g. 'font-size/lineheight' also marks 'leading' as taken).
            foreach ($classGroupUtils->getConflictingClassGroupIds($classGroupId, $hasPostfixModifier) as $conflictGroupId) {
                $classGroupsInConflict[$modifierKey . $conflictGroupId] = true;
            }

            // Prepend so that left-to-right order is restored in the result.
            array_unshift($result, $originalClassName);
        }

        return implode(' ', $result);
    }
}
