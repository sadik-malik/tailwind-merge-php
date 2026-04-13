<?php

declare(strict_types=1);

namespace TailwindMerge\Lib;

/**
 * Builds a lookup trie from config classGroups and provides getClassGroupId().
 * PHP port of tailwind-merge class-utils.ts / config-utils.ts
 *
 * KEY DESIGN: the class name is always split on '-' for trie navigation.
 * e.g. 'grid-cols-4' → ['grid','cols','4']
 * So every key in the trie is a single dash-free segment.
 * When a config key like 'grid-cols' is used as a prefix it is also split
 * into ['grid','cols'] before being inserted into the trie.
 */
class ClassGroupUtils
{
    private array $classMap;
    private array $conflictingClassGroups;
    private array $conflictingClassGroupModifiers;
    private array $theme;

    public function __construct(array $config)
    {
        $this->conflictingClassGroups         = $config['conflictingClassGroups'] ?? [];
        $this->conflictingClassGroupModifiers = $config['conflictingClassGroupModifiers'] ?? [];
        $this->theme                          = $config['theme'] ?? [];
        $this->classMap                       = $this->buildClassMap($config['classGroups'] ?? []);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the class-group ID for a bare class name (no modifiers).
     */
    /**
     * Class-group IDs whose utilities have negative-value variants in Tailwind CSS.
     * Only these groups are returned when a class name begins with '-'.
     */
    private const NEGATIVE_VALUE_GROUPS = [
        'm', 'mx', 'my', 'mt', 'mr', 'mb', 'ml', 'ms', 'me',
        'translate-x', 'translate-y',
        'rotate',
        'skew-x', 'skew-y',
        'top', 'right', 'bottom', 'left',
        'inset', 'inset-x', 'inset-y', 'start', 'end',
    ];

    public function getClassGroupId(string $className): ?string
    {
        // Use bracket-aware split so arbitrary values like [20px] and (--var)
        // are never broken by dashes inside them.
        $classParts = self::splitClassName($className);

        // Negative values: '-m-4' splits to ['','m','4']
        if ($classParts[0] === '' && count($classParts) > 1) {
            array_shift($classParts);
            $groupId = $this->getGroupRecursive($classParts, $this->classMap, true);
            // Gate: only return if this group actually supports negative values
            if ($groupId !== null && in_array($groupId, self::NEGATIVE_VALUE_GROUPS, true)) {
                return $groupId;
            }
            return null;
        }

        return $this->getGroupRecursive($classParts, $this->classMap, false);
    }

    /**
     * Split a class name on '-' but NOT inside [...] or (...) brackets.
     * e.g. 'bg-(--brand)'          → ['bg', '(--brand)']
     *      'p-[calc(100%-2px)]'    → ['p', '[calc(100%-2px)]']
     *      'translate-x-4'        → ['translate', 'x', '4']
     */
    private static function splitClassName(string $className): array
    {
        $parts = [];
        $current = '';
        $bracketDepth = 0;
        $parenDepth   = 0;
        $len = strlen($className);

        for ($i = 0; $i < $len; $i++) {
            $c = $className[$i];

            if ($c === '[') { $bracketDepth++; }
            elseif ($c === ']') { $bracketDepth--; }
            elseif ($c === '(') { $parenDepth++; }
            elseif ($c === ')') { $parenDepth--; }

            if ($c === '-' && $bracketDepth === 0 && $parenDepth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $c;
            }
        }
        $parts[] = $current;

        return $parts;
    }

    /**
     * Returns conflicting class-group IDs for a given group.
     */
    public function getConflictingClassGroupIds(string $classGroupId, bool $hasPostfixModifier): array
    {
        $conflicts = $this->conflictingClassGroups[$classGroupId] ?? [];

        if ($hasPostfixModifier && isset($this->conflictingClassGroupModifiers[$classGroupId])) {
            return array_merge($conflicts, $this->conflictingClassGroupModifiers[$classGroupId]);
        }

        return $conflicts;
    }

    // -------------------------------------------------------------------------
    // Trie construction
    // -------------------------------------------------------------------------

    private function buildClassMap(array $classGroups): array
    {
        $map = [];
        foreach ($classGroups as $groupId => $definitions) {
            $this->processClassGroup($groupId, $definitions, $map, []);
        }
        return $map;
    }

    /**
     * Walk a definition list and insert each entry into the trie.
     *
     * $prefixParts  Already-split trie path segments accumulated so far.
     *               e.g. for config key 'grid-cols' this will be ['grid','cols'].
     */
    private function processClassGroup(
        string $groupId,
        array  $definitions,
        array  &$map,
        array  $prefixParts
    ): void {
        foreach ($definitions as $definition) {
            if (is_string($definition)) {
                // Plain string value — may itself contain dashes (e.g. 'row-reverse')
                $this->addStringToMap($groupId, $prefixParts, $definition, $map);

            } elseif (is_callable($definition)) {
                // Validator function — hangs on the current node
                $this->attachValidator($groupId, $prefixParts, $definition, $map);

            } elseif (is_array($definition)) {
                if (array_is_list($definition)) {
                    // Indexed list — recurse at the same prefix level
                    $this->processClassGroup($groupId, $definition, $map, $prefixParts);
                } else {
                    // Associative object { 'key' => <value> }
                    // The key is a dash-containing segment like 'grid-cols' or 'scroll-m'
                    foreach ($definition as $key => $values) {
                        if ($key === '') {
                            // Empty key: the current prefix IS the end of the class
                            $this->processClassGroup($groupId, (array) $values, $map, $prefixParts);
                        } elseif (is_callable($values)) {
                            // { 'screen' => $isAnyNonArb } — validator behind a sub-key
                            $newParts = array_merge($prefixParts, explode('-', $key));
                            $this->attachValidator($groupId, $newParts, $values, $map);
                        } else {
                            // Nested object/list — split the key and recurse
                            $newParts = array_merge($prefixParts, explode('-', $key));
                            $this->processClassGroup($groupId, (array) $values, $map, $newParts);
                        }
                    }
                }
            }
        }
    }

    /**
     * Insert a plain string class-part value into the trie.
     *
     * The string may itself be multi-segment (e.g. 'row-reverse', 'col-dense').
     * It is split on '-' and each segment becomes one trie level, so:
     *   prefixParts=['grid','flow'], value='row-dense'
     *   → trie path: map['grid']['flow']['row']['dense']['__group__'] = $groupId
     *
     * Empty string '' means the prefix itself is a complete class:
     *   prefixParts=['rounded'] → map['rounded']['__group__'] = $groupId
     */
    private function addStringToMap(
        string $groupId,
        array  $prefixParts,
        string $value,
        array  &$map
    ): void {
        $node = &$map;
        foreach ($prefixParts as $part) {
            if (!isset($node[$part]) || !is_array($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }

        if ($value === '') {
            // The accumulated prefix IS the complete class
            $node['__group__'] ??= $groupId;
        } else {
            // Split the value on '-' and descend further
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
     * Attach a validator callable to the trie node at $prefixParts.
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
        $node['__validators__'][] = ['fn' => $fn, 'groupId' => $groupId];
    }

    // -------------------------------------------------------------------------
    // Trie lookup
    // -------------------------------------------------------------------------

    private function getGroupRecursive(array $classParts, array $map, bool $isNegative): ?string
    {
        // Base case: no more parts — check if this node IS a class
        if (empty($classParts)) {
            return $map['__group__'] ?? null;
        }

        $currentPart = $classParts[0];
        $remaining   = array_slice($classParts, 1);

        // 1. Direct trie descent
        if (isset($map[$currentPart]) && is_array($map[$currentPart])) {
            $result = $this->getGroupRecursive($remaining, $map[$currentPart], $isNegative);
            if ($result !== null) {
                return $result;
            }
        }

        // 2. Validators at this node — test the *entire remaining suffix*
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
}
