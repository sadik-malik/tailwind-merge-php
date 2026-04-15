<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\TailwindMerge;

/**
 * Tests for Tailwind CSS prefix support.
 *
 * Tailwind v4 introduces a variant-style prefix:
 *   @import "tailwindcss" prefix(tw);
 *   → classes look like  tw:flex  tw:hover:bg-red-500
 *
 * Tailwind v3 used a dash-style prefix:
 *   prefix: 'tw'  in tailwind.config.js
 *   → classes look like  tw-flex  hover:tw-bg-red-500
 *
 * Both formats are supported by passing 'prefix' to withConfig().
 * v4 style  →  prefix: 'tw'   (no trailing dash)
 * v3 style  →  prefix: 'tw-'  (trailing dash, OR auto-detected)
 */
class PrefixTest extends TestCase
{
    // =========================================================================
    // v4 variant-style prefix  (tw:flex)
    // =========================================================================

    /**
     * Helper: creates a TailwindMerge instance configured for Tailwind v4
     * variant-style prefix 'tw'.  Classes look like: tw:flex, tw:hover:bg-red-500.
     */
    private function v4(): TailwindMerge
    {
        return TailwindMerge::withConfig(['prefix' => 'tw']);
    }

    public function testV4PrefixBasicConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:p-4', $tw->merge('tw:p-2 tw:p-4'));
    }

    public function testV4PrefixPaddingOverridesPaddingXY(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:p-4', $tw->merge('tw:px-2 tw:py-2 tw:p-4'));
    }

    public function testV4PrefixBackgroundColorConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:bg-blue-500', $tw->merge('tw:bg-red-500 tw:bg-blue-500'));
    }

    public function testV4PrefixVariantConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:hover:bg-blue-500', $tw->merge('tw:hover:bg-red-500 tw:hover:bg-blue-500'));
    }

    public function testV4PrefixVariantBeforePrefix(): void
    {
        // variant can appear before the prefix token: hover:tw:bg-red
        $tw = $this->v4();
        $this->assertSame('hover:tw:bg-blue-500', $tw->merge('hover:tw:bg-red-500 hover:tw:bg-blue-500'));
    }

    public function testV4PrefixMixedVariantAndPrefixFirst(): void
    {
        // tw:hover:bg-red and hover:tw:bg-red should be treated as the same variant scope
        $tw = $this->v4();
        // Both express "hover + bg-color" with the tw prefix — last wins
        $result = $tw->merge('tw:hover:bg-red-500 hover:tw:bg-blue-500');
        $this->assertSame('hover:tw:bg-blue-500', $result);
    }

    public function testV4PrefixUnprefixedClassesPassThrough(): void
    {
        // Non-prefixed classes are kept verbatim (not subject to conflict resolution)
        $tw = $this->v4();
        $result = $tw->merge('tw:p-4 p-2');
        // p-2 is not prefixed so it passes through; tw:p-4 is resolved independently
        $this->assertStringContainsString('tw:p-4', $result);
        $this->assertStringContainsString('p-2', $result);
    }

    public function testV4PrefixNonTailwindClassesPassThrough(): void
    {
        $tw = $this->v4();
        $result = $tw->merge('tw:p-4 custom-class');
        $this->assertStringContainsString('tw:p-4', $result);
        $this->assertStringContainsString('custom-class', $result);
    }

    public function testV4PrefixImportantModifier(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:!p-4', $tw->merge('tw:!p-2 tw:!p-4'));
    }

    public function testV4PrefixImportantDoesNotConflictWithNonImportant(): void
    {
        $tw = $this->v4();
        $result = $tw->merge('tw:p-2 tw:!p-4');
        $this->assertStringContainsString('tw:p-2', $result);
        $this->assertStringContainsString('tw:!p-4', $result);
    }

    public function testV4PrefixArbitraryValue(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:p-[20px]', $tw->merge('tw:p-4 tw:p-[20px]'));
    }

    public function testV4PrefixOpacityPostfix(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:bg-blue-600', $tw->merge('tw:bg-red-500/50 tw:bg-blue-600'));
    }

    public function testV4PrefixWidthConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:w-full', $tw->merge('tw:w-1/2 tw:w-full'));
    }

    public function testV4PrefixFlexDirectionConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:flex-col', $tw->merge('tw:flex-row tw:flex-col'));
    }

    public function testV4PrefixResponsiveVariant(): void
    {
        $tw = $this->v4();
        // Same responsive variant, same property → later wins
        $this->assertSame('tw:md:text-lg', $tw->merge('tw:md:text-sm tw:md:text-lg'));
        // Different responsive variants → both kept
        $result = $tw->merge('tw:md:text-sm tw:lg:text-lg');
        $this->assertStringContainsString('tw:md:text-sm', $result);
        $this->assertStringContainsString('tw:lg:text-lg', $result);
    }

    public function testV4PrefixDarkModeVariant(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:dark:bg-gray-900', $tw->merge('tw:dark:bg-gray-800 tw:dark:bg-gray-900'));
    }

    public function testV4PrefixMultipleArgsNonConflicting(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:px-4 tw:py-2', $tw->merge('tw:px-4', 'tw:py-2'));
    }

    public function testV4PrefixEmptyResult(): void
    {
        $tw = $this->v4();
        $this->assertSame('', $tw->merge(''));
    }

    public function testV4PrefixSizeConflictsWithWidthHeight(): void
    {
        $tw = $this->v4();
        $result = $tw->merge('tw:w-4 tw:h-4 tw:size-4');
        $this->assertStringNotContainsString('tw:w-4', $result);
        $this->assertStringNotContainsString('tw:h-4', $result);
        $this->assertStringContainsString('tw:size-4', $result);
    }

    public function testV4PrefixBorderRadiusConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:rounded-lg', $tw->merge('tw:rounded tw:rounded-lg'));
    }

    public function testV4PrefixShadowConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:shadow-lg', $tw->merge('tw:shadow tw:shadow-lg'));
    }

    public function testV4PrefixTextSizeConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:text-xl', $tw->merge('tw:text-sm tw:text-xl'));
    }

    public function testV4PrefixFontWeightConflict(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:font-bold', $tw->merge('tw:font-normal tw:font-bold'));
    }

    public function testV4PrefixNegativeValue(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:-m-4', $tw->merge('tw:-m-2 tw:-m-4'));
    }

    // =========================================================================
    // v3 dash-style prefix  (tw-flex)
    // =========================================================================

    /**
     * Helper: creates a TailwindMerge instance configured for Tailwind v3
     * dash-style prefix.  Classes look like: tw-flex, hover:tw-bg-red-500.
     *
     * @param string $prefix  Defaults to 'tw-'; pass 'tw' to test auto-dash detection.
     */
    private function v3(string $prefix = 'tw-'): TailwindMerge
    {
        return TailwindMerge::withConfig(['prefix' => $prefix]);
    }

    public function testV3PrefixBasicConflict(): void
    {
        $tw = $this->v3();
        $this->assertSame('tw-p-4', $tw->merge('tw-p-2 tw-p-4'));
    }

    public function testV3PrefixPaddingOverridesPaddingXY(): void
    {
        $tw = $this->v3();
        $this->assertSame('tw-p-4', $tw->merge('tw-px-2 tw-py-2 tw-p-4'));
    }

    public function testV3PrefixBackgroundColorConflict(): void
    {
        $tw = $this->v3();
        $this->assertSame('tw-bg-blue-500', $tw->merge('tw-bg-red-500 tw-bg-blue-500'));
    }

    public function testV3PrefixWithVariant(): void
    {
        // variant sits outside the prefix in v3: hover:tw-bg-red-500
        $tw = $this->v3();
        $this->assertSame('hover:tw-bg-blue-500', $tw->merge('hover:tw-bg-red-500 hover:tw-bg-blue-500'));
    }

    public function testV3PrefixDifferentVariantsNoConflict(): void
    {
        $tw = $this->v3();
        $result = $tw->merge('hover:tw-bg-red-500 focus:tw-bg-red-500');
        $this->assertStringContainsString('hover:tw-bg-red-500', $result);
        $this->assertStringContainsString('focus:tw-bg-red-500', $result);
    }

    public function testV3PrefixUnprefixedPassThrough(): void
    {
        $tw = $this->v3();
        $result = $tw->merge('tw-p-4 p-2 custom-class');
        $this->assertStringContainsString('tw-p-4', $result);
        $this->assertStringContainsString('p-2', $result);
        $this->assertStringContainsString('custom-class', $result);
    }

    public function testV3PrefixImportantModifier(): void
    {
        // In v3 style, !important sits before the utility: !tw-p-4
        $tw = $this->v3();
        $this->assertSame('!tw-p-4', $tw->merge('!tw-p-2 !tw-p-4'));
    }

    public function testV3PrefixArbitraryValue(): void
    {
        $tw = $this->v3();
        $this->assertSame('tw-p-[20px]', $tw->merge('tw-p-4 tw-p-[20px]'));
    }

    public function testV3PrefixWidthConflict(): void
    {
        $tw = $this->v3();
        $this->assertSame('tw-w-full', $tw->merge('tw-w-1/2 tw-w-full'));
    }

    public function testV3PrefixResponsiveVariant(): void
    {
        $tw = $this->v3();
        $this->assertSame('md:tw-text-lg', $tw->merge('md:tw-text-sm md:tw-text-lg'));
    }

    public function testV3PrefixNoBorderBetweenPrefixedAndUnprefixed(): void
    {
        // tw-p-4 and unprefixed p-2 should NOT conflict with each other
        $tw = $this->v3();
        $result = $tw->merge('tw-p-4 p-2');
        $this->assertStringContainsString('tw-p-4', $result);
        $this->assertStringContainsString('p-2', $result);
    }

    public function testV3PrefixWithoutTrailingDash(): void
    {
        // Supplying 'tw' without trailing dash should also work for v3 classes
        $tw = TailwindMerge::withConfig(['prefix' => 'tw']);
        // 'tw-p-4' → dash-style: base after stripping 'tw-' is 'p-4'. Group: 'p'.
        $this->assertSame('tw-p-4', $tw->merge('tw-p-2 tw-p-4'));
    }

    public function testV3PrefixNegativeValue(): void
    {
        $tw = $this->v3();
        $this->assertSame('tw--m-4', $tw->merge('tw--m-2 tw--m-4'));
    }

    // =========================================================================
    // No prefix (default) — existing behaviour unaffected
    // =========================================================================

    public function testNoPrefixDefaultBehaviourUnchanged(): void
    {
        $tw = new TailwindMerge();
        $this->assertSame('p-4', $tw->merge('p-2 p-4'));
        $this->assertSame('bg-blue-500', $tw->merge('bg-red-500 bg-blue-500'));
        $this->assertSame('hover:text-lg', $tw->merge('hover:text-sm hover:text-lg'));
    }

    public function testNoPrefixWithCustomClassPassThrough(): void
    {
        $tw = new TailwindMerge();
        $result = $tw->merge('p-4 custom-class bg-red-500');
        $this->assertStringContainsString('p-4', $result);
        $this->assertStringContainsString('custom-class', $result);
        // bg-red-500 kept (no override)
        $this->assertStringContainsString('bg-red-500', $result);
    }

    // =========================================================================
    // withConfig prefix integration
    // =========================================================================

    public function testWithConfigPrefixKey(): void
    {
        $tw = TailwindMerge::withConfig(['prefix' => 'ui']);
        $this->assertSame('ui:p-4', $tw->merge('ui:p-2 ui:p-4'));
        // Non-prefixed classes pass through
        $result = $tw->merge('ui:p-4 p-2');
        $this->assertStringContainsString('ui:p-4', $result);
        $this->assertStringContainsString('p-2', $result);
    }

    public function testWithConfigPrefixAndExtend(): void
    {
        $tw = TailwindMerge::withConfig([
            'prefix' => 'tw',
            'extend' => [
                'classGroups' => [
                    'my-size' => [['my-size' => ['sm', 'md', 'lg']]],
                ],
            ],
        ]);

        // Custom group works with prefix
        $this->assertSame('tw:my-size-lg', $tw->merge('tw:my-size-sm tw:my-size-lg'));
        // Built-in groups still work
        $this->assertSame('tw:p-4', $tw->merge('tw:p-2 tw:p-4'));
    }

    public function testPrefixPreservedInOutput(): void
    {
        // The original class token (with prefix) must be preserved in the output, not stripped
        $tw = TailwindMerge::withConfig(['prefix' => 'tw']);
        $result = $tw->merge('tw:p-4');
        $this->assertSame('tw:p-4', $result);
    }

    public function testV3PrefixPreservedInOutput(): void
    {
        $tw = TailwindMerge::withConfig(['prefix' => 'tw-']);
        $result = $tw->merge('tw-p-4');
        $this->assertSame('tw-p-4', $result);
    }

    // =========================================================================
    // Arbitrary CSS properties with v4 prefix
    // =========================================================================

    public function testV4PrefixArbitraryCssVars(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:[--grid-column-span:5]', $tw->merge('tw:[--grid-column-span:12] tw:[--grid-column-span:5]'));
    }

    public function testV4PrefixArbitraryCss(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:[font-size:2rem]', $tw->merge('tw:[font-size:1rem] tw:[font-size:2rem]'));
    }

    public function testV4PrefixArbitraryCssWithVariant(): void
    {
        $tw = $this->v4();
        $this->assertSame('tw:hover:[font-size:2rem]', $tw->merge('tw:hover:[font-size:1rem] tw:hover:[font-size:2rem]'));
    }

    public function testV4PrefixArbitraryCssDifferentPropertiesNoConflict(): void
    {
        $tw = $this->v4();
        $result = $tw->merge('tw:[font-size:1rem] tw:[color:red]');
        $this->assertSame('tw:[font-size:1rem] tw:[color:red]', $result);
    }

    public function testV3PrefixArbitraryCssVars(): void
    {
        $tw = $this->v3();
        // In v3 dash-style prefix mode, arbitrary property classes like [--var:value]
        // don't carry the 'tw-' prefix so they pass through unresolved — both are kept.
        $result = $tw->merge('tw-p-4 [--grid-column-span:12] [--grid-column-span:5]');
        $this->assertStringContainsString('[--grid-column-span:12]', $result);
        $this->assertStringContainsString('[--grid-column-span:5]', $result);
        // tw-p-4 IS a prefixed class and still resolves normally
        $this->assertStringContainsString('tw-p-4', $result);
    }
}
