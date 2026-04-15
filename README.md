# tailwind-merge-php

A complete PHP port of [tailwind-merge](https://github.com/dcastil/tailwind-merge) — merge Tailwind CSS class strings without style conflicts.

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
composer require tailwind-merge/tailwind-merge-php
```

Or clone and run `composer install`.

---

## Usage

### Basic merging

```php
use TailwindMerge\TailwindMerge;

$tw = new TailwindMerge();

// Later class wins
$tw->merge('p-2 p-4');                       // → 'p-4'

// Shorthand overrides individual sides
$tw->merge('px-2 py-2 p-4');                 // → 'p-4'
$tw->merge('border rounded px-2 py-1', 'p-5'); // → 'border rounded p-5'

// Multiple arguments are joined before merging
$tw->merge('text-sm', 'text-lg', 'text-xl'); // → 'text-xl'

// Responsive variants are scoped independently
$tw->merge('md:text-sm md:text-lg');         // → 'md:text-lg'
$tw->merge('hover:p-2 focus:p-4');           // → 'hover:p-2 focus:p-4'  (no conflict)

// Important modifier (!) scopes separately from non-important
$tw->merge('p-2 !p-4');                      // → 'p-2 !p-4'
$tw->merge('!p-2 !p-4');                     // → '!p-4'

// Arbitrary values
$tw->merge('p-4 p-[20px]');                  // → 'p-[20px]'
$tw->merge('bg-red-500 bg-[#abc]');          // → 'bg-[#abc]'

// Arbitrary CSS variables (Tailwind v4 parenthesis syntax)
$tw->merge('bg-red-500 bg-(--brand)');       // → 'bg-(--brand)'
$tw->merge('p-4 p-(--my-space)');            // → 'p-(--my-space)'

// Opacity postfix shorthand
$tw->merge('bg-red-500/50 bg-blue-600');     // → 'bg-blue-600'

// Arbitrary CSS property declarations
$tw->merge('[font-size:1rem] [font-size:2rem]');           // → '[font-size:2rem]'
$tw->merge('[--grid-column-span:12] [--grid-column-span:5]'); // → '[--grid-column-span:5]'
```

### Static helper

A shared no-prefix singleton for when you don't need custom config:

```php
TailwindMerge::tw('p-2 p-4');  // → 'p-4'
```

### Join without conflict resolution

```php
// twJoin equivalent: concatenate only, no conflict detection
TailwindMerge::join('px-4 py-2', $isError ? 'text-red-600' : '', null, false);
// → 'px-4 py-2 text-red-600'  (null/false/'' are dropped)
```

### Tailwind v4 variant-style prefix

Configured in CSS as `@import "tailwindcss" prefix(tw);` — classes look like `tw:flex`:

```php
$tw = TailwindMerge::withConfig(['prefix' => 'tw']);

$tw->merge('tw:px-2 tw:py-2', 'tw:p-4');         // → 'tw:p-4'
$tw->merge('tw:hover:bg-red-500', 'tw:hover:bg-blue-500'); // → 'tw:hover:bg-blue-500'

// Non-prefixed classes pass through untouched
$tw->merge('tw:p-4 p-2 custom-class');            // → 'tw:p-4 p-2 custom-class'
```

### Tailwind v3 dash-style prefix

Configured in JS as `prefix: 'tw'` in `tailwind.config.js` — classes look like `tw-flex`:

```php
$tw = TailwindMerge::withConfig(['prefix' => 'tw-']);

$tw->merge('tw-px-2 tw-py-2', 'tw-p-4');         // → 'tw-p-4'
$tw->merge('hover:tw-bg-red-500', 'hover:tw-bg-blue-500'); // → 'hover:tw-bg-blue-500'

// Non-prefixed classes pass through untouched
$tw->merge('tw-p-4 p-2 custom-class');            // → 'tw-p-4 p-2 custom-class'
```

### Custom class groups

```php
$tw = TailwindMerge::withConfig([
    'extend' => [
        'classGroups' => [
            // 'my-size-sm', 'my-size-md', 'my-size-lg' will now conflict
            'my-size' => [['my-size' => ['sm', 'md', 'lg']]],
        ],
        'conflictingClassGroups' => [
            // a my-size-* class will also displace w-* and h-*
            'my-size' => ['w', 'h'],
        ],
    ],
]);

$tw->merge('my-size-sm my-size-lg');  // → 'my-size-lg'
$tw->merge('w-4 h-4 my-size-lg');    // → 'my-size-lg'
```

### Exposing and composing configs

```php
// Inspect the default config
$config = TailwindMerge::getDefaultConfig();

// Compose two configs programmatically (useful for plugins)
$merged = TailwindMerge::mergeConfigs(
    TailwindMerge::getDefaultConfig(),
    ['extend' => ['classGroups' => ['my-group' => [...]]]]
);
$tw = new TailwindMerge($merged);
```

---

## Architecture

```
src/
├── TailwindMerge.php           Entry point: merge(), tw(), join(), withConfig()
└── Lib/
    ├── DefaultConfig.php       All Tailwind v3 class groups and conflict maps
    ├── ClassGroupUtils.php     Trie builder + group resolver (getClassGroupId)
    ├── MergeClassList.php      Core merge algorithm (right-to-left scan)
    ├── ParseClassName.php      Splits a class token into modifiers/base/postfix
    ├── Validators.php          Predicate functions used by DefaultConfig
    └── LruCache.php            O(1) LRU cache for memoising merge results
```

### How it works

**1. Parse** — `ParseClassName::parseClassName()` splits each class token into:
- `modifiers` — variant prefixes like `['hover', 'focus', 'md']`
- `hasImportantModifier` — whether `!` is present
- `baseClassName` — the bare utility name e.g. `bg-red-500/50`
- `maybePostfixModifierPosition` — position of `/` in the base (for opacity variants)
- `hasPrefix` — whether the configured Tailwind prefix was found and stripped

**2. Look up group** — `ClassGroupUtils::getClassGroupId()` walks a trie built from `DefaultConfig` to find the group ID for the base class. Three strategies in order:
1. *Negative gate* — strips leading `-`, looks up the trie, only returns a group if it's in the `NEGATIVE_VALUE_GROUPS` allowlist (e.g. `-m-4` → group `m`, but `-p-4` → `null`)
2. *Trie lookup* — descends segment by segment; on each node tries literal key matches first, then registered validator functions
3. *Arbitrary property fallback* — detects `[font-size:1rem]` or `[--var:value]` syntax and returns a synthetic group ID based on the property name

**3. Conflict tracking** — `MergeClassList::merge()` iterates **right-to-left** (later = higher priority). For each class it builds a conflict key:
```
{!?}{sorted-variants:}{groupId}
```
e.g. `'bg-color'`, `'!p'`, `'focus:hover:text-color'`. If the key is already in the seen-set, the class is dropped. Otherwise the key AND all related group keys are marked as seen.

**4. Cache** — the raw input string is used as an LRU cache key. Identical calls are returned immediately without re-parsing.

### Trie node structure

```
map['bg']['__validators__'] = [isArbitraryPosition, isArbitrarySize, isArbitraryImage, isAny]
map['bg']['red']['500']['__group__'] = 'bg-color'
map['bg']['fixed']['__group__'] = 'bg-attachment'
map['grid']['cols']['none']['__group__'] = 'grid-cols'
map['grid']['cols']['__validators__'] = [isInteger, isArbitraryNumber]
```

Validators are checked in registration order. More specific validators (e.g. `isArbitraryLength`) are registered before the catch-all `isAny` to prevent false matches.

### CSS variable disambiguation

Tailwind v4's `(--var)` syntax for CSS variables requires careful routing. The rule:
- **Labelled validators** (`isArbitraryVariablePosition`, `isArbitraryVariableLength`, etc.) only match when the value explicitly carries the `label:` prefix: `(position:center)`, `(length:--my-len)`.
- **Bare CSS variables** like `(--brand)` skip all labelled validators and fall through to the unlabelled `isAny` catch-all in the colour group.

This ensures `bg-(--brand)` is correctly attributed to `bg-color` rather than `bg-position` or `bg-size`.

---

## Running tests

```bash
composer install
vendor/bin/phpunit
```

Test coverage includes:

| File | Tests | What it covers |
|---|---|---|
| `TailwindMergeTest.php` | 181 | End-to-end merge scenarios for all utility categories |
| `PrefixTest.php` | 48 | v4 variant-style and v3 dash-style prefix handling |
| `ParseClassNameTest.php` | 17 | Class token parsing, arbitrary variants, postfix, sortModifiers |
| `ValidatorsTest.php` | 13 | Every validator function including label-disambiguation |
| `LruCacheTest.php` | 9 | LRU eviction, sentinel, empty-string caching |

---

## Differences from the JS package

| Feature | JS (tailwind-merge) | PHP port |
|---|---|---|
| Tailwind v3 class groups | ✓ (v2.x) | ✓ |
| Tailwind v4 prefix style | ✓ | ✓ (`prefix: 'tw'`) |
| Tailwind v3 prefix style | ✓ (v2.x) | ✓ (`prefix: 'tw-'`) |
| Arbitrary values `[value]` | ✓ | ✓ |
| Arbitrary variables `(--var)` | ✓ | ✓ |
| Arbitrary properties `[prop:val]` | ✓ | ✓ |
| Negative values (`-m-4`) | ✓ | ✓ |
| Opacity postfix (`bg-red/50`) | ✓ | ✓ |
| `twMerge` | ✓ | `TailwindMerge::tw()` |
| `twJoin` | ✓ | `TailwindMerge::join()` |
| `extendTailwindMerge` | ✓ | `TailwindMerge::withConfig(['extend' => …])` |
| `mergeConfigs` | ✓ | `TailwindMerge::mergeConfigs()` public static |
| `getDefaultConfig` | ✓ | `TailwindMerge::getDefaultConfig()` |
| `fromTheme()` | ✓ | Not planed |

---

## License

MIT
