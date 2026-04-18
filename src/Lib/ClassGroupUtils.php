<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * ClassGroupUtils — single authority for resolving a class name to a group ID.
 *
 * PHP port of tailwind-merge class-utils.ts / config-utils.ts.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  TRIE STRUCTURE                                                         │
 * │                                                                         │
 * │  Class names are always split on '-' for trie navigation, so the trie  │
 * │  key for a class like 'grid-cols-4' is the path ['grid','cols','4'].   │
 * │                                                                         │
 * │  Config keys like 'grid-cols' are also split, giving prefix path       │
 * │  ['grid','cols'], so the stored structure mirrors the lookup path.      │
 * │                                                                         │
 * │  Each trie node is an associative array that may contain:              │
 * │    '__group__'      → string   The group ID if this path is a complete  │
 * │                                class (e.g. map['rounded']['__group__']  │
 * │                                = 'rounded' for the bare 'rounded'       │
 * │                                utility).                               │
 * │    '__validators__' → array    Validator callables for open-ended       │
 * │                                suffixes (e.g. is_num for 'p-4',        │
 * │                                is_arb_len for 'p-[20px]').             │
 * │    '<segment>'      → array    Child node for the next dash segment.    │
 * │                                                                         │
 * │  EXAMPLE (simplified):                                                  │
 * │    map['p']['__validators__'] = [isArbLen, isNum, isFrac]  → group 'p' │
 * │    map['grid']['cols']['__validators__'] = [isInt]         → 'grid-cols'│
 * │    map['grid']['cols']['none']['__group__'] = 'grid-cols'               │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * RESPONSIBILITY CONTRACT
 * ───────────────────────
 * This class is the ONLY place that knows what constitutes a class group.
 * MergeClassList calls getClassGroupId() and acts on the result — it never
 * contains group-detection logic itself.
 */
class ClassGroupUtils
{
    /**
     * The root of the trie built from config classGroups.
     * Each node is an array; see class docblock for the node structure.
     *
     * @var array<string,mixed>
     */
    private array $classMap;

    /**
     * Maps a group ID to the list of other group IDs it conflicts with.
     * e.g. 'p' → ['px','py','pt','pr','pb','pl']
     *
     * @var array<string, string[]>
     */
    private array $conflictingClassGroups;

    /**
     * Extra conflicts that only apply when the winning class used a postfix
     * modifier (the /lineheight syntax on font-size utilities).
     * e.g. 'font-size' → ['leading']  (text-lg/8 overrides leading-tight)
     *
     * @var array<string, string[]>
     */
    private array $conflictingClassGroupModifiers;

    /**
     * Theme values from the config (reserved for future use / plugins).
     *
     * @var array<string,mixed>
     */
    private array $theme;

    /**
     * @param array $config  Merged config array (from DefaultConfig + any user overrides).
     */
    public function __construct(array $config)
    {
        $this->conflictingClassGroups         = $config['conflictingClassGroups'] ?? [];
        $this->conflictingClassGroupModifiers = $config['conflictingClassGroupModifiers'] ?? [];
        $this->theme                          = $config['theme'] ?? [];
        $this->classMap                       = $this->buildClassMap($config['classGroups'] ?? []);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Group IDs whose utilities have negative-value variants in Tailwind CSS.
     *
     * Only these groups are returned when the class name starts with '-'.
     * This prevents '-p-4' from being falsely matched as a 'p' group class
     * (negative padding doesn't exist), while '-m-4' correctly resolves to 'm'.
     *
     * The list covers: margin (m/mx/my/mt/mr/mb/ml/ms/me), directional transforms
     * (translate-x/y, rotate, skew-x/y), and positional utilities
     * (top/right/bottom/left, inset/inset-x/inset-y, start/end).
     */
    private const NEGATIVE_VALUE_GROUPS = [
        'm', 'mx', 'my', 'mt', 'mr', 'mb', 'ml', 'ms', 'me',
        'translate-x', 'translate-y',
        'rotate',
        'skew-x', 'skew-y',
        'top', 'right', 'bottom', 'left',
        'inset', 'inset-x', 'inset-y', 'start', 'end', 'outline-offset',
    ];

    /**
     * Returns the class-group ID for a bare class name (modifiers already stripped).
     *
     * Three resolution strategies are attempted in order:
     *
     *   1. NEGATIVE GATE — if the class starts with '-', strip it, look up the
     *      remaining name in the trie, and only return the group if it appears in
     *      NEGATIVE_VALUE_GROUPS.  This prevents -p-4 from falsely matching 'p'.
     *
     *   2. TRIE LOOKUP — walk the prefix trie segment by segment.  On each node,
     *      first try a direct key match; if that fails, try registered validator
     *      functions (e.g. isNumber matches '4' in 'p-4').
     *
     *   3. ARBITRARY PROPERTY FALLBACK — if the trie finds nothing, check whether
     *      the class is an arbitrary property declaration like '[font-size:1rem]'
     *      or '[--grid-span:5]'.  These are not in the trie but should conflict
     *      with other declarations targeting the same CSS property.
     *
     * @param string $className  Bare base class, e.g. 'p-4', 'bg-red-500', '-m-2'.
     * @return string|null       Group ID (e.g. 'p', 'bg-color') or null if unknown.
     */
    public function getClassGroupId(string $className): ?string
    {
        // Always use a bracket-aware split so that dashes inside arbitrary values
        // like [calc(100%-2px)] or (--my-var) are not treated as separators.
        $classParts = self::splitClassName($className);

        // ── Strategy 1: negative gate ─────────────────────────────────────────
        // A leading '-' produces an empty first segment: '-m-4' → ['','m','4'].
        if ($classParts[0] === '' && count($classParts) > 1) {
            array_shift($classParts); // drop the empty leading segment
            $groupId = $this->getGroupRecursive($classParts, $this->classMap, true);

            // Only honour the result if this group actually has negative variants.
            if ($groupId !== null && in_array($groupId, self::NEGATIVE_VALUE_GROUPS, true)) {
                return $groupId;
            }
            return null; // e.g. '-p-4' is not a real Tailwind class
        }

        // ── Strategy 2: trie lookup ───────────────────────────────────────────
        $groupId = $this->getGroupRecursive($classParts, $this->classMap, false);
        if ($groupId !== null) {
            return $groupId;
        }

        // ── Strategy 3: arbitrary property fallback ───────────────────────────
        // '[font-size:1rem]' and '[--grid-span:5]' are not in the trie (their
        // values are open-ended) but two classes sharing the same CSS property
        // name should still conflict.  The synthetic group ID encodes the property.
        return self::getArbitraryPropertyGroupId($className);
    }

    /**
     * Detects an arbitrary CSS property class and returns a synthetic group ID
     * derived from the property name, ensuring that two declarations of the
     * same property correctly conflict with each other.
     *
     * This lives in ClassGroupUtils — not in MergeClassList — because resolving
     * a class name to a group is entirely this class's responsibility.
     *
     * EXAMPLES
     * ────────
     *   '[font-size:1rem]'        → 'arbitrary..font-size'
     *   '[font-size:2rem]'        → 'arbitrary..font-size'  ← same group → conflict ✓
     *   '[--grid-column-span:12]' → 'arbitrary..--grid-column-span'
     *   '[#B91C1C]'               → null  (no colon → not a property declaration)
     *   '[&:hover]'               → null  ('&' is not a valid CSS property name)
     *
     * @param string $className  The base class name to inspect.
     * @return string|null       Synthetic group ID or null if not an arbitrary property.
     */
    public static function getArbitraryPropertyGroupId(string $className): ?string
    {
        // Must be entirely wrapped in square brackets.
        if (!preg_match('/^\[(.+)\]$/', $className, $m)) {
            return null;
        }

        $content  = $m[1];

        // Must contain ':' to be a property:value declaration.
        $colonPos = strpos($content, ':');
        if ($colonPos === false) {
            return null;
        }

        $property = substr($content, 0, $colonPos);

        // Accept standard CSS property names (letters, digits, hyphens, starting
        // with a letter) and CSS custom properties (starting with '--').
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $property) || str_starts_with($property, '--')) {
            // Use '..' as a separator that cannot appear in real group IDs so there
            // is no accidental collision with trie-based group names.
            return 'arbitrary..' . $property;
        }

        return null;
    }

    /**
     * Returns the list of group IDs that conflict with $classGroupId.
     *
     * When $hasPostfixModifier is true (e.g. 'text-lg/8'), extra conflicts from
     * conflictingClassGroupModifiers are included — for Tailwind v3 this means
     * a font-size class with an explicit line-height postfix also displaces any
     * standalone leading-* class.
     *
     * @param string $classGroupId      The group whose conflicts are requested.
     * @param bool   $hasPostfixModifier  True when the class used a /value postfix.
     * @return string[]  Group IDs that the given group conflicts with.
     */
    public function getConflictingClassGroupIds(string $classGroupId, bool $hasPostfixModifier): array
    {
        $conflicts = $this->conflictingClassGroups[$classGroupId] ?? [];

        if ($hasPostfixModifier && isset($this->conflictingClassGroupModifiers[$classGroupId])) {
            return array_merge($conflicts, $this->conflictingClassGroupModifiers[$classGroupId]);
        }

        return $conflicts;
    }

    // =========================================================================
    // Trie construction
    // =========================================================================

    /**
     * Iterates over every class group definition and inserts it into the trie.
     *
     * @param array $classGroups  The classGroups section of the config.
     * @return array<string,mixed>  The root trie node.
     */
    private function buildClassMap(array $classGroups): array
    {
        $map = [];
        foreach ($classGroups as $groupId => $definitions) {
            $this->processClassGroup($groupId, $definitions, $map, []);
        }
        return $map;
    }

    /**
     * Recursively walks a class-group definition and inserts each entry into the trie.
     *
     * A definition can be:
     *   string     → a literal class value, e.g. 'none', 'auto', 'row-reverse'
     *   callable   → a validator function, e.g. Validators::isNumber(...)
     *   list array → recurse at the same prefix level
     *   assoc array → { 'key' => <value> } where 'key' becomes the next trie path
     *                 segment(s); value can be a nested list, a callable, or ''
     *                 (meaning the prefix itself IS a valid class end).
     *
     * CONFIG EXAMPLE:
     *   'grid-cols' => [['grid-cols' => ['none', 'subgrid', $isInt, $isArbNum]]]
     *   → processClassGroup('grid-cols', [...], map, [])
     *     → assoc key 'grid-cols' → explode('-') → prefixParts=['grid','cols']
     *     → string 'none'      → map['grid']['cols']['none']['__group__'] = 'grid-cols'
     *     → callable $isInt   → map['grid']['cols']['__validators__'] += isInt
     *     → callable $isArbNum → map['grid']['cols']['__validators__'] += isArbNum
     *
     * @param string            $groupId      The ID being registered, e.g. 'grid-cols'.
     * @param array             $definitions  Definition entries from the config.
     * @param array             $map          The trie being mutated (passed by reference).
     * @param string[]          $prefixParts  Trie path accumulated so far.
     */
    private function processClassGroup(
        string $groupId,
        array  $definitions,
        array  &$map,
        array  $prefixParts
    ): void {
        foreach ($definitions as $definition) {
            if (is_string($definition)) {
                // A plain string value like 'none', 'auto', or 'row-reverse'.
                // Multi-segment values (containing '-') are split further in addStringToMap.
                $this->addStringToMap($groupId, $prefixParts, $definition, $map);

            } elseif (is_callable($definition)) {
                // A validator function — attached to the current node so it can
                // match any suffix that the trie doesn't have a literal key for.
                $this->attachValidator($groupId, $prefixParts, $definition, $map);

            } elseif (is_array($definition)) {
                if (array_is_list($definition)) {
                    // Flat indexed list — all entries share the current prefix level.
                    $this->processClassGroup($groupId, $definition, $map, $prefixParts);
                } else {
                    // Associative { 'key' => value } — 'key' extends the trie path.
                    foreach ($definition as $key => $values) {
                        if ($key === '') {
                            // Empty key: the current prefix is itself a complete class end.
                            // e.g. 'grow' => [['grow' => ['', $isNum]]] — bare 'grow' is valid.
                            $this->processClassGroup($groupId, (array) $values, $map, $prefixParts);
                        } elseif (is_callable($values)) {
                            // { 'screen' => $isAnyNonArb } — validator behind a named sub-key.
                            // Used for e.g. max-w-screen-sm where 'screen' is the segment.
                            $newParts = array_merge($prefixParts, explode('-', $key));
                            $this->attachValidator($groupId, $newParts, $values, $map);
                        } else {
                            // Nested group definition — split the key and descend.
                            $newParts = array_merge($prefixParts, explode('-', $key));
                            $this->processClassGroup($groupId, (array) $values, $map, $newParts);
                        }
                    }
                }
            }
        }
    }

    /**
     * Inserts a string class-value into the trie at the path defined by $prefixParts.
     *
     * Multi-segment values like 'row-reverse' or 'col-dense' are split on '-' so
     * each segment becomes its own trie level:
     *   prefixParts=['grid','flow'], value='row-dense'
     *   → trie path: map['grid']['flow']['row']['dense']['__group__'] = $groupId
     *
     * An empty value '' means the prefix itself is the complete class name:
     *   prefixParts=['rounded'], value=''
     *   → map['rounded']['__group__'] = 'rounded'
     *   → the bare class 'rounded' (no suffix) belongs to the 'rounded' group.
     *
     * @param string  $groupId      The group ID to register.
     * @param array   $prefixParts  Already-split trie path segments.
     * @param string  $value        The class value to add (may be '' or multi-segment).
     * @param array   $map          The trie root (passed by reference).
     */
    private function addStringToMap(
        string $groupId,
        array  $prefixParts,
        string $value,
        array  &$map
    ): void {
        $node = &$map;

        // Navigate (or create) the trie nodes for the prefix path.
        foreach ($prefixParts as $part) {
            if (!isset($node[$part]) || !is_array($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }

        if ($value === '') {
            // The prefix path IS the complete class — mark this node as a group end.
            $node['__group__'] ??= $groupId;
        } else {
            // Descend further, one segment at a time, for the value itself.
            $valueParts = explode('-', $value);
            foreach ($valueParts as $vp) {
                if (!isset($node[$vp]) || !is_array($node[$vp])) {
                    $node[$vp] = [];
                }
                $node = &$node[$vp];
            }
            $node['__group__'] ??= $groupId;
        }
    }

    /**
     * Attaches a validator callable to the trie node at $prefixParts.
     *
     * Validators are stored in a '__validators__' list on their node.  When the
     * trie walker reaches a node and has remaining class parts that don't match
     * any literal child key, it reconstructs the suffix and tests each validator
     * in registration order until one returns true.
     *
     * ORDER MATTERS: validators registered earlier take priority.  This is why
     * more specific validators (isArbitraryLength, isArbitraryNumber) must be
     * registered before the catch-all isAny — see DefaultConfig.
     *
     * @param string   $groupId     The group ID the validator identifies.
     * @param array    $prefixParts Trie path to the node where the validator lives.
     * @param callable $fn          The validator, e.g. Validators::isNumber(...).
     * @param array    $map         The trie root (passed by reference).
     */
    private function attachValidator(
        string   $groupId,
        array    $prefixParts,
        callable $fn,
        array    &$map
    ): void {
        $node = &$map;
        foreach ($prefixParts as $part) {
            if (!isset($node[$part]) || !is_array($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }
        // Append — keeps registration order, which determines validator priority.
        $node['__validators__'][] = ['fn' => $fn, 'groupId' => $groupId];
    }

    // =========================================================================
    // Trie lookup
    // =========================================================================

    /**
     * Recursively walks the trie to find the group ID for the given class parts.
     *
     * ALGORITHM
     * ─────────
     * 1. Base case: no parts remaining → return __group__ if this node is a
     *    class end, or null.
     * 2. Try to descend into the child keyed by the current segment.  If the
     *    subtree produces a match, return it.
     * 3. If no literal child matched, test the node's validators with the
     *    ENTIRE remaining suffix rejoined as a string.  The first validator
     *    that returns true gives the group ID.
     * 4. Return null if nothing matched.
     *
     * The $isNegative flag is threaded through the recursion but is not currently
     * used inside this method — the negative gate is applied by getClassGroupId()
     * before calling this method.
     *
     * @param string[] $classParts  Remaining trie path segments.
     * @param array    $map         The current trie node.
     * @param bool     $isNegative  True when the original class started with '-'.
     * @return string|null          Group ID or null.
     */
    private function getGroupRecursive(array $classParts, array $map, bool $isNegative): ?string
    {
        // ── Base case ─────────────────────────────────────────────────────────
        if (empty($classParts)) {
            // All segments consumed — this node is a class end if it has __group__.
            return $map['__group__'] ?? null;
        }

        $currentPart = $classParts[0];
        $remaining   = array_slice($classParts, 1);

        // ── Literal descent ───────────────────────────────────────────────────
        // If this segment has a dedicated child node, descend into it.
        // A match in the subtree takes priority over any validator at this level.
        if (isset($map[$currentPart]) && is_array($map[$currentPart])) {
            $result = $this->getGroupRecursive($remaining, $map[$currentPart], $isNegative);
            if ($result !== null) {
                return $result;
            }
        }

        // ── Validator fallback ────────────────────────────────────────────────
        // No literal child matched.  Reconstruct the full suffix ('4', '[20px]',
        // '(--my-var)', '1/2', …) and test each registered validator in order.
        if (isset($map['__validators__'])) {
            $reconstructed = implode('-', $classParts);
            foreach ($map['__validators__'] as $entry) {
                if (($entry['fn'])($reconstructed)) {
                    return $entry['groupId'];
                }
            }
        }

        return null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Splits a class name on '-' while respecting [...] and (...) brackets.
     *
     * Standard PHP explode('-', …) would break inside arbitrary values:
     *   explode('-', 'bg-(--brand)')    → ['bg','(','','brand)']  ← WRONG
     *   splitClassName('bg-(--brand)')  → ['bg','(--brand)']      ← correct
     *   splitClassName('p-[calc(100%-2px)]') → ['p','[calc(100%-2px)]']
     *
     * The depth counters ensure that a '-' inside [...] or (...) is treated
     * as part of the current segment, not as a separator.
     *
     * @param string $className  The bare class name to split.
     * @return string[]          Dash-separated segments with brackets preserved.
     */
    private static function splitClassName(string $className): array
    {
        $parts        = [];
        $current      = '';
        $bracketDepth = 0; // depth inside [...]
        $parenDepth   = 0; // depth inside (...)
        $len          = strlen($className);

        for ($i = 0; $i < $len; $i++) {
            $c = $className[$i];

            // Update depth counters BEFORE checking for '-', so that the opening
            // bracket itself is included in the current segment, not separated.
            if ($c === '[')     { $bracketDepth++; }
            elseif ($c === ']') { $bracketDepth--; }
            elseif ($c === '(') { $parenDepth++; }
            elseif ($c === ')') { $parenDepth--; }

            if ($c === '-' && $bracketDepth === 0 && $parenDepth === 0) {
                // Top-level dash — finish this segment and start a new one.
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $c;
            }
        }

        // Append the final segment (may be '' for trailing dashes, though Tailwind
        // class names never end with a dash in practice).
        $parts[] = $current;

        return $parts;
    }
}
