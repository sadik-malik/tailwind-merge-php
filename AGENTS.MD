AGENTS — AI Agent Rules for tailwind-merge-php
=============================================

Purpose
-------

This document defines the expectations and non-functional rules that any automated AI agent (or human contributor) must follow when modifying, extending, or reviewing code in this repository. The goal is to ensure changes remain consistent with the project's design patterns, API contracts, and test-driven approach.

Project Design Patterns (summary)
---------------------------------

- **Strict types & explicit typing:** All source files use `declare(strict_types=1);` and explicit type hints for arguments and return values. See [src/TailwindMerge.php](src/TailwindMerge.php#L1-L4).
- **PSR-4 namespacing & autoloading:** Namespace root `TailwindMerge\` maps to `src/` in `composer.json`. See [composer.json](composer.json#L23-L28).
- **Single Responsibility & small helpers:** Parsing, trie lookup, validation, merging, and caching are separated across `ParseClassName`, `ClassGroupUtils`, `Validators`, `MergeClassList`, and `LruCache`. See [src/Lib/ParseClassName.php](src/Lib/ParseClassName.php#L1-L20) and [src/Lib/MergeClassList.php](src/Lib/MergeClassList.php#L1-L20).
- **Stateless pure helpers + orchestration:** Parsers and validators are stateless/pure; `MergeClassList` is the orchestrator and does not embed group logic. See [src/Lib/MergeClassList.php](src/Lib/MergeClassList.php#L1-L30).
- **Trie-powered group resolution & validators:** `ClassGroupUtils` builds a trie and validator lists (`__validators__`, `__group__`) for flexible class matching. See [src/Lib/ClassGroupUtils.php](src/Lib/ClassGroupUtils.php#L1-L20) and [src/Lib/Validators.php](src/Lib/Validators.php#L1-L20).
- **Config layering & extension:** Defaults live in `DefaultConfig::get()`; extensions must be applied via `TailwindMerge::withConfig()` and/or `mergeConfigs()`. See [src/Lib/DefaultConfig.php](src/Lib/DefaultConfig.php#L1-L20) and [src/TailwindMerge.php](src/TailwindMerge.php#L60-L120).
- **Caching for performance:** The repository uses an `LruCache` to memoize merge results; preserve cache semantics. See [src/Lib/LruCache.php](src/Lib/LruCache.php#L1-L20).
- **Test-driven correctness:** A comprehensive test suite exists under `tests/` and is the canonical specification for behavior. See [tests/](tests/) and [phpunit.xml](phpunit.xml#L1-L20).

Rules for AI Agents (must-follow)
--------------------------------

1. **Read the tests first.** Before proposing any behavioural change, read the tests covering the area and run them locally. Tests define intended behavior.

2. **Preserve public API and semantics.** Do not change method signatures or observable behavior of public APIs (`merge`, `tw`, `join`, `withConfig`, `getDefaultConfig`, `mergeConfigs`, `resetInstance`) without a clear, tested migration plan and deprecation strategy.

3. **Never edit `DefaultConfig.php` in-place for feature extensions.** Use `TailwindMerge::withConfig(['extend' => ...])` or `mergeConfigs()` to add or override groups. The project expects defaults to remain stable.

4. **Follow existing class boundaries.** Add logic to the correct layer:
   - Parsing → `ParseClassName`
   - Group lookup → `ClassGroupUtils`
   - Validation helpers → `Validators`
   - Orchestration → `MergeClassList`
   - API / caching → `TailwindMerge`

5. **Maintain `declare(strict_types=1);` and type signatures.** New files must include strict types and accurate return types and parameter types.

6. **Add tests for any behavioral change.** Unit tests should mirror existing style in `tests/` and assert behaviour end-to-end where appropriate.

7. **Keep changes small and atomic.** One behavior change per commit/PR, with a clear test that would fail before the change and pass after.

8. **Avoid global mutable state.** Only `TailwindMerge::tw()` maintains a deliberately shallow singleton; prefer injecting dependencies or returning new instances. Use `resetInstance()` in tests when needed.

9. **Performance-conscious but Correct.** Optimize only when backed by benchmarks and tests. For cache size changes ensure behavior unaffected.

10. **Run the test-scripts & CI locally before pushing.** Use `composer test` (alias to `vendor/bin/phpunit`) and ensure all tests pass on PHP 8.1+. See [composer.json](composer.json#L17-L21).

Do / Don't examples
--------------------

- **Don't** change the default map inside `DefaultConfig::get()` to add a new user group.
- **Do** provide the new group via `TailwindMerge::withConfig(['extend' => ['classGroups' => [...]]])` and add tests that use `withConfig()` to validate the extension.

Enforcement & Suggested CI / Hooks
---------------------------------

- Recommended minimal CI step for PRs: Run `composer test` (phpunit). A GitHub Action should run `composer test` on PHP 8.1.
- Optionally enforce tests in a pre-commit or pre-push hook for contributors using a simple script:

```bash
# .git/hooks/pre-push (example, not installed automatically)
vendor/bin/phpunit || { echo "Unit tests failed"; exit 1; }
```

How to propose a change (workflow)
----------------------------------

1. Create a feature branch named `fix/` or `feat/` with concise subject.
2. Add or update unit tests under `tests/` that demonstrate the bug or new behaviour.
3. Run `composer test` locally and fix issues until tests are green.
4. Open a PR with a clear description, links to related tests, and mention backwards-incompatible changes.
5. Update `AGENTS.MD` and `README.md` if the public behaviour changed.

Contact / Review
----------------
If unsure about cross-cutting changes (trie format, validators, or caching semantics), open an issue first describing the intended change and ask for a short design review.

— End of AGENTS rules
