# tailwind-merge-php

A PHP port of [tailwind-merge](https://github.com/dcastil/tailwind-merge) — merge Tailwind CSS classes without style conflicts.

```php
use TailwindMerge\TailwindMerge;

$tw = new TailwindMerge();
echo $tw->merge('px-2 py-1 bg-red-500 hover:bg-dark-red', 'p-3 bg-[#B91C1C]');
// → 'hover:bg-dark-red p-3 bg-[#B91C1C]'
```

---

## Requirements

- PHP 8.1+
- Composer (for autoloading and running tests)

---

## Installation

```bash
composer require piplup/tailwind-merge-php
```

---

## Usage

### Instance usage

```php
use TailwindMerge\TailwindMerge;

$tw = new TailwindMerge();

// Basic conflict resolution — later class wins
$tw->merge('p-2 p-4');                      // → 'p-4'
$tw->merge('px-2 py-2 p-4');               // → 'p-4'
$tw->merge('border rounded px-2 py-1', 'p-5'); // → 'border rounded p-5'

// Multiple arguments
$tw->merge('text-sm', 'text-lg', 'text-xl'); // → 'text-xl'

// Responsive variants are scoped — only same-variant conflicts resolve
$tw->merge('md:text-sm md:text-lg');  // → 'md:text-lg'
$tw->merge('hover:p-2 focus:p-4');   // → 'hover:p-2 focus:p-4' (no conflict)

// Important modifier (!) is treated separately
$tw->merge('p-2 !p-4');   // → 'p-2 !p-4'
$tw->merge('!p-2 !p-4');  // → '!p-4'

// Arbitrary values
$tw->merge('p-4 p-[20px]');           // → 'p-[20px]'
$tw->merge('bg-red-500 bg-[#abc]');   // → 'bg-[#abc]'

// Opacity postfix shorthand
$tw->merge('bg-red-500/50 bg-blue-600'); // → 'bg-blue-600'
```

### Static helper

A shared singleton is available for convenience:

```php
use TailwindMerge\TailwindMerge;

echo TailwindMerge::tw('p-2 p-4'); // → 'p-4'
```

### Custom / extended config

```php
use TailwindMerge\TailwindMerge;

$tw = TailwindMerge::withConfig([
    'extend' => [
        'classGroups' => [
            // Add your own class group
            'my-group' => [['my-prefix' => ['value-a', 'value-b']]],
        ],
        'conflictingClassGroups' => [
            'my-group' => ['another-group'],
        ],
    ],
]);
```

---

## Architecture

```
src/
├── TailwindMerge.php            Main entry point (twMerge equivalent)
└── Lib/
    ├── DefaultConfig.php        All Tailwind v3 class groups & conflict maps
    ├── ClassGroupUtils.php      Trie-based class-group lookup
    ├── MergeClassList.php       Core merge algorithm (reverse-scan + conflict set)
    ├── ParseClassName.php       Splits class into modifiers / base / important
    ├── Validators.php           Arbitrary-value & type validator functions
    └── LruCache.php             LRU cache for merged results
```

### How it works

1. **Parse** — each class name is split into `modifiers` (e.g. `hover:`, `md:`), an `hasImportantModifier` flag, a `baseClassName`, and an optional postfix position (e.g. `/50`).

2. **Look up group** — the base class is walked through a trie built from `DefaultConfig::classGroups`. When a validator function matches a class part (e.g. `isNumber` for `p-[4]`), the group ID is returned.

3. **Conflict tracking** — classes are processed right-to-left. A conflict key of the form `{important}{variants}:{groupId}` is tracked. If the key is already in the set, the class is dropped. Conflicting groups (e.g. `p` conflicts with `px`, `py`, `pt`, …) are pre-marked when a winner is found.

4. **Cache** — the full input string is used as a cache key in an LRU cache (default 500 entries) so repeated calls are O(1).

---

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

Test coverage includes:

| File                     | What it tests                  |
| ------------------------ | ------------------------------ |
| `TailwindMergeTest.php`  | 60+ end-to-end merge scenarios |
| `ValidatorsTest.php`     | All validator functions        |
| `ParseClassNameTest.php` | Class-name parsing edge cases  |
| `LruCacheTest.php`       | Cache eviction and retrieval   |

---

## Differences from the JS version

| Feature                    | JS             | PHP                                    |
| -------------------------- | -------------- | -------------------------------------- |
| Tailwind v4 support        | ✓ (v3 on v2.x) | v3 (DefaultConfig)                     |
| `extendTailwindMerge`      | ✓              | `TailwindMerge::withConfig([...])`     |
| `twJoin`                   | ✓              | Not included (use `implode(' ', ...)`) |
| Theme getter (`fromTheme`) | ✓              | Not planned                            |
| TypeScript types           | ✓              | PHP docblocks + strict_types           |

---

## License

MIT
