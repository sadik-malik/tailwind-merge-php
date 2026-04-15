<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\TailwindMerge;

/**
 * Comprehensive test suite for tailwind-merge PHP port.
 * Mirrors the core test cases from the original JS package.
 */
/**
 * TailwindMergeTest — comprehensive end-to-end tests for the TailwindMerge class.
 *
 * Each test creates a fresh TailwindMerge instance (via setUp) and exercises a
 * specific conflict-resolution scenario.  Tests are organised into sections that
 * mirror the Tailwind CSS documentation categories.
 *
 * READING THE TESTS
 * ─────────────────
 * Every assertSame call encodes:
 *   input  → the class string(s) passed to merge()
 *   output → what the CSS cascade would produce (later class always wins)
 *
 * Tests marked "no conflict" assert that BOTH classes are preserved because
 * they control different CSS properties or are scoped to different variants.
 *
 * Tests marked "X overrides Y" assert that class X causes Y to be dropped.
 */
class TailwindMergeTest extends TestCase
{
    private TailwindMerge $tw;

    protected function setUp(): void
    {
        $this->tw = new TailwindMerge();
    }

    // =========================================================================
    // Core merge behaviour
    // =========================================================================

    public function testEmptyStrings(): void
    {
        $this->assertSame('', $this->tw->merge(''));
        $this->assertSame('', $this->tw->merge('', ''));
    }

    public function testNoConflict(): void
    {
        $this->assertSame('px-2 py-1', $this->tw->merge('px-2 py-1'));
    }

    public function testMultipleArguments(): void
    {
        $this->assertSame('px-2 py-3', $this->tw->merge('px-2', 'py-3'));
    }

    public function testBasicOverride(): void
    {
        // p-3 overrides px-2 and py-1
        $this->assertSame('hover:bg-dark-red p-3 bg-[#B91C1C]', $this->tw->merge('px-2 py-1 bg-red hover:bg-dark-red', 'p-3 bg-[#B91C1C]'));
    }

    public function testLaterClassWins(): void
    {
        $this->assertSame('p-3', $this->tw->merge('p-2', 'p-3'));
    }

    public function testMergePaddingWithPaddingXY(): void
    {
        // px-4 overrides the x-axis from p-2
        $result = $this->tw->merge('p-2 px-4');
        $this->assertSame('p-2 px-4', $result);

        // p-4 overrides both px-2 and py-2
        $result = $this->tw->merge('px-2 py-2 p-4');
        $this->assertSame('p-4', $result);
    }

    // =========================================================================
    // Padding conflicts
    // =========================================================================

    public function testPaddingConflictsAllSides(): void
    {
        $this->assertSame('p-4', $this->tw->merge('pt-2 pr-2 pb-2 pl-2 p-4'));
    }

    public function testPaddingXOverridesLeftRight(): void
    {
        $result = $this->tw->merge('pl-2 pr-2 px-4');
        $this->assertSame('px-4', $result);
    }

    public function testPaddingYOverridesTopBottom(): void
    {
        $result = $this->tw->merge('pt-2 pb-2 py-4');
        $this->assertSame('py-4', $result);
    }

    // =========================================================================
    // Margin conflicts
    // =========================================================================

    public function testMarginConflict(): void
    {
        $this->assertSame('m-4', $this->tw->merge('m-2 m-4'));
        $this->assertSame('mx-auto', $this->tw->merge('ml-auto mr-auto mx-auto'));
    }

    // =========================================================================
    // Background colour conflicts
    // =========================================================================

    public function testBackgroundColorConflict(): void
    {
        $this->assertSame('bg-blue-500', $this->tw->merge('bg-red-500 bg-blue-500'));
    }

    public function testBackgroundColorWithArbitrary(): void
    {
        $this->assertSame('bg-[#B91C1C]', $this->tw->merge('bg-red-500 bg-[#B91C1C]'));
    }

    // =========================================================================
    // Text color conflicts
    // =========================================================================

    public function testTextColorConflict(): void
    {
        $this->assertSame('text-blue-500', $this->tw->merge('text-red-500 text-blue-500'));
    }

    public function testTextSizeConflict(): void
    {
        $this->assertSame('text-xl', $this->tw->merge('text-sm text-lg text-xl'));
    }

    // =========================================================================
    // Width / Height conflicts
    // =========================================================================

    public function testWidthConflict(): void
    {
        $this->assertSame('w-full', $this->tw->merge('w-1/2 w-full'));
    }

    public function testHeightConflict(): void
    {
        $this->assertSame('h-screen', $this->tw->merge('h-32 h-screen'));
    }

    public function testSizeConflictsWithWidthHeight(): void
    {
        // size-4 should conflict with w and h
        $this->assertSame('size-4', $this->tw->merge('w-4 h-4 size-4'));
    }

    // =========================================================================
    // Responsive variants
    // =========================================================================

    public function testResponsiveVariantsAreKeptSeparate(): void
    {
        $result = $this->tw->merge('text-sm md:text-lg lg:text-xl');
        $this->assertSame('text-sm md:text-lg lg:text-xl', $result);
    }

    public function testResponsiveVariantConflict(): void
    {
        $result = $this->tw->merge('md:text-sm md:text-lg');
        $this->assertSame('md:text-lg', $result);
    }

    public function testDifferentVariantsNoConflict(): void
    {
        $result = $this->tw->merge('hover:text-red-500 focus:text-red-500');
        $this->assertSame('hover:text-red-500 focus:text-red-500', $result);
    }

    public function testSameVariantConflicts(): void
    {
        $result = $this->tw->merge('hover:text-red-500 hover:text-blue-500');
        $this->assertSame('hover:text-blue-500', $result);
    }

    // =========================================================================
    // Important modifier
    // =========================================================================

    public function testImportantModifier(): void
    {
        $this->assertSame('!p-3', $this->tw->merge('!p-2 !p-3'));
    }

    public function testImportantDoesNotConflictWithNonImportant(): void
    {
        $result = $this->tw->merge('p-2 !p-3');
        $this->assertSame('p-2 !p-3', $result);
    }

    // =========================================================================
    // Arbitrary values
    // =========================================================================

    public function testArbitraryValue(): void
    {
        $this->assertSame('p-[20px]', $this->tw->merge('p-4 p-[20px]'));
    }

    public function testArbitraryColorValue(): void
    {
        $this->assertSame('bg-[#abc]', $this->tw->merge('bg-red-500 bg-[#abc]'));
    }

    public function testArbitraryWidthValue(): void
    {
        $this->assertSame('w-[200px]', $this->tw->merge('w-32 w-[200px]'));
    }

    // =========================================================================
    // Border radius
    // =========================================================================

    public function testBorderRadiusConflict(): void
    {
        $this->assertSame('rounded-lg', $this->tw->merge('rounded rounded-lg'));
    }

    public function testBorderRadiusConflictsWithCorners(): void
    {
        $result = $this->tw->merge('rounded rounded-tl-lg rounded-tr-lg');
        $this->assertSame('rounded rounded-tl-lg rounded-tr-lg', $result);
    }

    // =========================================================================
    // Display & position
    // =========================================================================

    public function testDisplayConflict(): void
    {
        $this->assertSame('flex', $this->tw->merge('block flex'));
        $this->assertSame('grid', $this->tw->merge('hidden flex grid'));
    }

    public function testPositionConflict(): void
    {
        $this->assertSame('absolute', $this->tw->merge('relative absolute'));
        $this->assertSame('fixed', $this->tw->merge('static fixed'));
    }

    // =========================================================================
    // Flexbox
    // =========================================================================

    public function testFlexConflict(): void
    {
        $this->assertSame('flex-col', $this->tw->merge('flex-row flex-col'));
        $this->assertSame('flex-wrap', $this->tw->merge('flex-nowrap flex-wrap'));
    }

    public function testJustifyConflict(): void
    {
        $this->assertSame('justify-end', $this->tw->merge('justify-start justify-end'));
    }

    public function testItemsConflict(): void
    {
        $this->assertSame('items-center', $this->tw->merge('items-start items-center'));
    }

    // =========================================================================
    // Grid
    // =========================================================================

    public function testGridColsConflict(): void
    {
        $this->assertSame('grid-cols-4', $this->tw->merge('grid-cols-2 grid-cols-4'));
    }

    public function testGapConflict(): void
    {
        $this->assertSame('gap-4', $this->tw->merge('gap-2 gap-4'));
        $this->assertSame('gap-x-4', $this->tw->merge('gap-x-2 gap-x-4'));
    }

    // =========================================================================
    // Font
    // =========================================================================

    public function testFontWeightConflict(): void
    {
        $this->assertSame('font-bold', $this->tw->merge('font-normal font-bold'));
    }

    public function testFontSizeConflict(): void
    {
        $this->assertSame('text-2xl', $this->tw->merge('text-base text-lg text-2xl'));
    }

    public function testTrackingConflict(): void
    {
        $this->assertSame('tracking-wide', $this->tw->merge('tracking-tight tracking-wide'));
    }

    // =========================================================================
    // Overflow
    // =========================================================================

    public function testOverflowConflict(): void
    {
        $this->assertSame('overflow-hidden', $this->tw->merge('overflow-auto overflow-hidden'));
    }

    public function testOverflowXConflict(): void
    {
        $this->assertSame('overflow-x-hidden', $this->tw->merge('overflow-x-auto overflow-x-hidden'));
    }

    // =========================================================================
    // Opacity
    // =========================================================================

    public function testOpacityConflict(): void
    {
        $this->assertSame('opacity-75', $this->tw->merge('opacity-50 opacity-75'));
    }

    // =========================================================================
    // Shadow
    // =========================================================================

    public function testShadowConflict(): void
    {
        $this->assertSame('shadow-lg', $this->tw->merge('shadow shadow-lg'));
    }

    // =========================================================================
    // Ring
    // =========================================================================

    public function testRingConflict(): void
    {
        $this->assertSame('ring-2', $this->tw->merge('ring ring-2'));
    }

    // =========================================================================
    // Transition & Animation
    // =========================================================================

    public function testTransitionConflict(): void
    {
        $this->assertSame('transition-colors', $this->tw->merge('transition transition-colors'));
    }

    public function testDurationConflict(): void
    {
        $this->assertSame('duration-500', $this->tw->merge('duration-150 duration-500'));
    }

    // =========================================================================
    // Transform
    // =========================================================================

    public function testScaleConflict(): void
    {
        $this->assertSame('scale-110', $this->tw->merge('scale-100 scale-110'));
    }

    public function testRotateConflict(): void
    {
        $this->assertSame('rotate-45', $this->tw->merge('rotate-0 rotate-45'));
    }

    // =========================================================================
    // Z-index
    // =========================================================================

    public function testZIndexConflict(): void
    {
        $this->assertSame('z-10', $this->tw->merge('z-0 z-10'));
    }

    // =========================================================================
    // Inset / Top / Right / Bottom / Left
    // =========================================================================

    public function testInsetConflict(): void
    {
        // inset overrides all four sides
        $result = $this->tw->merge('top-2 right-2 bottom-2 left-2 inset-4');
        $this->assertSame('inset-4', $result);
    }

    public function testTopConflict(): void
    {
        $this->assertSame('top-4', $this->tw->merge('top-2 top-4'));
    }

    // =========================================================================
    // Unknown classes — preserve as-is
    // =========================================================================

    public function testUnknownClassesPreserved(): void
    {
        $this->assertSame('custom-class', $this->tw->merge('custom-class'));
        $this->assertSame('custom-a custom-b', $this->tw->merge('custom-a custom-b'));
    }

    public function testUnknownAndKnownClasses(): void
    {
        $result = $this->tw->merge('custom-class px-4 py-2');
        $this->assertSame('custom-class px-4 py-2', $result);
    }

    // =========================================================================
    // Multiple variant modifiers
    // =========================================================================

    public function testMultipleVariants(): void
    {
        $result = $this->tw->merge('hover:focus:text-red-500 hover:focus:text-blue-500');
        $this->assertSame('hover:focus:text-blue-500', $result);
    }

    public function testDarkModeVariant(): void
    {
        $result = $this->tw->merge('dark:bg-gray-800 dark:bg-gray-900');
        $this->assertSame('dark:bg-gray-900', $result);
    }

    // =========================================================================
    // Whitespace handling
    // =========================================================================

    public function testExtraWhitespace(): void
    {
        $this->assertSame('p-2 m-2', $this->tw->merge('  p-2   m-2  '));
    }

    public function testNewlineInInput(): void
    {
        $this->assertSame('p-2 m-2', $this->tw->merge("p-2\nm-2"));
    }

    // =========================================================================
    // Caching
    // =========================================================================

    public function testCachedResultIsSameValue(): void
    {
        $first  = $this->tw->merge('p-2 p-4');
        $second = $this->tw->merge('p-2 p-4');
        $this->assertSame($first, $second);
        $this->assertSame('p-4', $first);
    }

    // =========================================================================
    // Static helper
    // =========================================================================

    public function testStaticHelper(): void
    {
        $result = TailwindMerge::tw('p-2 p-4');
        $this->assertSame('p-4', $result);
    }

    // =========================================================================
    // Postfix (opacity modifier e.g. bg-red-500/50)
    // =========================================================================

    public function testOpacityPostfix(): void
    {
        $result = $this->tw->merge('bg-red-500/50 bg-blue-600');
        $this->assertSame('bg-blue-600', $result);
    }

    // =========================================================================
    // Filter conflicts
    // =========================================================================

    public function testBlurConflict(): void
    {
        $this->assertSame('blur-lg', $this->tw->merge('blur blur-lg'));
    }

    public function testBrightnessConflict(): void
    {
        $this->assertSame('brightness-150', $this->tw->merge('brightness-100 brightness-150'));
    }

    // =========================================================================
    // Real-world examples
    // =========================================================================

    public function testButtonVariantMerge(): void
    {
        $base     = 'px-4 py-2 rounded bg-blue-500 text-white';
        $override = 'bg-green-500 rounded-lg';
        $result   = $this->tw->merge($base, $override);

        // bg-green-500 overrides bg-blue-500; rounded-lg overrides rounded
        $this->assertStringNotContainsString('bg-blue-500', $result);
        // 'rounded' should be gone (overridden by rounded-lg); use word-boundary check
        $this->assertDoesNotMatchRegularExpression('/\\brounded\\b(?!-)/', $result);
        $this->assertStringContainsString('bg-green-500', $result);
        $this->assertStringContainsString('rounded-lg', $result);
        // Non-conflicting classes are preserved
        $this->assertStringContainsString('px-4', $result);
        $this->assertStringContainsString('py-2', $result);
        $this->assertStringContainsString('text-white', $result);
    }

    public function testConditionalClassMerge(): void
    {
        $isError   = true;
        $isLarge   = false;
        $base      = 'px-4 py-2 text-sm text-gray-700 bg-white';
        $error     = $isError ? 'text-red-600 border-red-500' : '';
        $large     = $isLarge ? 'px-8 py-4 text-lg' : '';
        $result    = $this->tw->merge($base, $error, $large);

        // text-red-600 should override text-gray-700
        $this->assertStringNotContainsString('text-gray-700', $result);
        $this->assertStringContainsString('text-red-600', $result);
        // Large not applied so px-4 still there
        $this->assertStringContainsString('px-4', $result);
    }

    public function testComponentInputClassMerge(): void
    {
        // Simulating: base component has 'border rounded px-2 py-1', consumer passes 'p-5'
        $result = $this->tw->merge('border rounded px-2 py-1', 'p-5');
        $this->assertSame('border rounded p-5', $result);
    }

    // =========================================================================
    // Gradient
    // =========================================================================

    public function testGradientFromConflict(): void
    {
        $result = $this->tw->merge('from-red-500 from-blue-600');
        $this->assertSame('from-blue-600', $result);
    }

    // =========================================================================
    // Divide
    // =========================================================================

    public function testDivideConflict(): void
    {
        $result = $this->tw->merge('divide-x-2 divide-x-4');
        $this->assertSame('divide-x-4', $result);
    }

    // =========================================================================
    // Outline
    // =========================================================================

    public function testOutlineConflict(): void
    {
        $result = $this->tw->merge('outline-none outline-dashed');
        $this->assertSame('outline-dashed', $result);
    }

    // =========================================================================
    // Cursor
    // =========================================================================

    public function testCursorConflict(): void
    {
        $result = $this->tw->merge('cursor-pointer cursor-not-allowed');
        $this->assertSame('cursor-not-allowed', $result);
    }

    // =========================================================================
    // Select
    // =========================================================================

    public function testSelectConflict(): void
    {
        $result = $this->tw->merge('select-none select-text');
        $this->assertSame('select-text', $result);
    }

    // =========================================================================
    // Negative values  (-m-4, -translate-x-4, -rotate-45)
    // =========================================================================

    public function testNegativeMargin(): void
    {
        // -m-4 should conflict with -m-2
        $this->assertSame('-m-4', $this->tw->merge('-m-2 -m-4'));
    }

    public function testNegativeMarginConflictsWithPositive(): void
    {
        // positive m-4 and negative -m-4 target the same group → later wins
        $this->assertSame('-m-4', $this->tw->merge('m-4 -m-4'));
    }

    public function testNegativeTranslateX(): void
    {
        $this->assertSame('-translate-x-4', $this->tw->merge('-translate-x-2 -translate-x-4'));
    }

    public function testNegativeTranslateConflictsWithPositive(): void
    {
        $this->assertSame('-translate-x-4', $this->tw->merge('translate-x-2 -translate-x-4'));
    }

    public function testNegativeRotate(): void
    {
        $this->assertSame('-rotate-45', $this->tw->merge('-rotate-12 -rotate-45'));
    }

    public function testNegativePaddingDoesNotExist(): void
    {
        // Negative padding is not a Tailwind class — treated as unknown, both kept
        $result = $this->tw->merge('-p-2 -p-4');
        $this->assertSame('-p-2 -p-4', $result);
    }

    // =========================================================================
    // twJoin — concatenate without conflict resolution
    // =========================================================================

    public function testJoinBasic(): void
    {
        $this->assertSame('p-2 p-4', TailwindMerge::join('p-2', 'p-4'));
    }

    public function testJoinDropsFalsyValues(): void
    {
        $this->assertSame('p-2 p-4', TailwindMerge::join('p-2', null, false, '', 'p-4'));
    }

    public function testJoinDoesNotResolveConflicts(): void
    {
        // Unlike merge(), join() keeps both conflicting classes
        $result = TailwindMerge::join('bg-red-500', 'bg-blue-500');
        $this->assertSame('bg-red-500 bg-blue-500', $result);
    }

    public function testJoinEmptyArgs(): void
    {
        $this->assertSame('', TailwindMerge::join());
        $this->assertSame('', TailwindMerge::join('', null, false));
    }

    public function testJoinSingleArg(): void
    {
        $this->assertSame('px-4', TailwindMerge::join('px-4'));
    }

    // =========================================================================
    // withConfig — custom class groups
    // =========================================================================

    public function testWithConfigAddsNewClassGroup(): void
    {
        $tw = TailwindMerge::withConfig([
            'extend' => [
                'classGroups' => [
                    'my-size' => [['my-size' => ['sm', 'md', 'lg', 'xl']]],
                ],
            ],
        ]);

        // Two classes in the same custom group → later wins
        $this->assertSame('my-size-lg', $tw->merge('my-size-sm my-size-lg'));
        // Known + custom — no conflict
        $this->assertSame('p-4 my-size-lg', $tw->merge('p-4 my-size-lg'));
    }

    public function testWithConfigAddsConflict(): void
    {
        $tw = TailwindMerge::withConfig([
            'extend' => [
                'classGroups' => [
                    'my-size' => [['my-size' => ['sm', 'md', 'lg']]],
                ],
                'conflictingClassGroups' => [
                    'my-size' => ['w', 'h'],
                ],
            ],
        ]);

        // my-size-lg should displace w-* and h-*
        $result = $tw->merge('w-4 h-4 my-size-lg');
        $this->assertStringNotContainsString('w-4', $result);
        $this->assertStringNotContainsString('h-4', $result);
        $this->assertStringContainsString('my-size-lg', $result);
    }

    public function testWithConfigOverridesClassGroup(): void
    {
        // Completely replace (not extend) a class group
        $tw = TailwindMerge::withConfig([
            'classGroups' => [
                'custom-only' => ['custom-a', 'custom-b'],
            ],
            'conflictingClassGroups' => [],
            'conflictingClassGroupModifiers' => [],
        ]);

        $this->assertSame('custom-b', $tw->merge('custom-a custom-b'));
    }

    public function testGetDefaultConfigReturnsArray(): void
    {
        $config = TailwindMerge::getDefaultConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('classGroups', $config);
        $this->assertArrayHasKey('conflictingClassGroups', $config);
        $this->assertArrayHasKey('cacheSize', $config);
    }

    public function testMergeConfigsUtility(): void
    {
        $base = TailwindMerge::getDefaultConfig();
        $extended = TailwindMerge::mergeConfigs($base, [
            'extend' => [
                'classGroups' => [
                    'plugin-group' => [['plugin' => ['a', 'b']]],
                ],
            ],
        ]);

        $this->assertArrayHasKey('plugin-group', $extended['classGroups']);
        // Original groups still present
        $this->assertArrayHasKey('p', $extended['classGroups']);
    }

    public function testResetInstanceCreatesNewSingleton(): void
    {
        TailwindMerge::resetInstance();
        $result1 = TailwindMerge::tw('p-2 p-4');
        TailwindMerge::resetInstance();
        $result2 = TailwindMerge::tw('p-2 p-4');
        $this->assertSame($result1, $result2);
        $this->assertSame('p-4', $result1);
    }

    // =========================================================================
    // font-size / leading conflict (conflictingClassGroupModifiers)
    // =========================================================================

    public function testFontSizeAloneDoesNotRemoveLeading(): void
    {
        // text-lg and leading-tight are independent → both kept
        $result = $this->tw->merge('text-lg leading-tight');
        $this->assertSame('text-lg leading-tight', $result);
    }

    public function testFontSizeWithPostfixRemovesLeading(): void
    {
        // text-lg/8 (with line-height postfix) conflicts with explicit leading-*
        $result = $this->tw->merge('leading-tight text-lg/8');
        $this->assertStringNotContainsString('leading-tight', $result);
        $this->assertStringContainsString('text-lg/8', $result);
    }

    public function testLeadingConflict(): void
    {
        $this->assertSame('leading-loose', $this->tw->merge('leading-tight leading-loose'));
    }

    public function testLeadingNoneConflict(): void
    {
        $this->assertSame('leading-relaxed', $this->tw->merge('leading-none leading-relaxed'));
    }

    // =========================================================================
    // line-clamp — conflicts with overflow and display
    // =========================================================================

    public function testLineClampConflictsWithOverflow(): void
    {
        // line-clamp-3 should displace overflow-*
        $result = $this->tw->merge('overflow-hidden line-clamp-3');
        $this->assertStringNotContainsString('overflow-hidden', $result);
        $this->assertStringContainsString('line-clamp-3', $result);
    }

    public function testLineClampConflictsWithDisplay(): void
    {
        $result = $this->tw->merge('flex line-clamp-2');
        $this->assertStringNotContainsString('flex', $result);
        $this->assertStringContainsString('line-clamp-2', $result);
    }

    public function testLineClampSelfConflict(): void
    {
        $this->assertSame('line-clamp-4', $this->tw->merge('line-clamp-2 line-clamp-4'));
    }

    public function testLineClampNone(): void
    {
        $this->assertSame('line-clamp-none', $this->tw->merge('line-clamp-3 line-clamp-none'));
    }

    // =========================================================================
    // text-decoration conflicts
    // =========================================================================

    public function testTextDecorationConflict(): void
    {
        $this->assertSame('line-through', $this->tw->merge('underline line-through'));
    }

    public function testTextDecorationStyleConflict(): void
    {
        $this->assertSame('decoration-dotted', $this->tw->merge('decoration-solid decoration-dotted'));
    }

    public function testTextDecorationColorConflict(): void
    {
        $this->assertSame('decoration-blue-500', $this->tw->merge('decoration-red-500 decoration-blue-500'));
    }

    public function testTextDecorationThicknessConflict(): void
    {
        $this->assertSame('decoration-4', $this->tw->merge('decoration-2 decoration-4'));
    }

    public function testUnderlineOffsetConflict(): void
    {
        $this->assertSame('underline-offset-4', $this->tw->merge('underline-offset-2 underline-offset-4'));
    }

    public function testTextDecorationAndStyleNoConflict(): void
    {
        // underline (text-decoration group) vs decoration-dotted (text-decoration-style group) — no conflict
        $result = $this->tw->merge('underline decoration-dotted');
        $this->assertSame('underline decoration-dotted', $result);
    }

    // =========================================================================
    // border-spacing conflicts
    // =========================================================================

    public function testBorderSpacingConflict(): void
    {
        $this->assertSame('border-spacing-4', $this->tw->merge('border-spacing-2 border-spacing-4'));
    }

    public function testBorderSpacingXConflict(): void
    {
        $this->assertSame('border-spacing-x-4', $this->tw->merge('border-spacing-x-2 border-spacing-x-4'));
    }

    public function testBorderSpacingYConflict(): void
    {
        $this->assertSame('border-spacing-y-4', $this->tw->merge('border-spacing-y-2 border-spacing-y-4'));
    }

    public function testBorderSpacingOverridesXY(): void
    {
        // border-spacing should conflict with border-spacing-x and border-spacing-y
        $result = $this->tw->merge('border-spacing-x-2 border-spacing-y-2 border-spacing-4');
        $this->assertStringNotContainsString('border-spacing-x-2', $result);
        $this->assertStringNotContainsString('border-spacing-y-2', $result);
        $this->assertStringContainsString('border-spacing-4', $result);
    }

    // =========================================================================
    // touch action conflicts
    // =========================================================================

    public function testTouchAutoConflictsWithPanX(): void
    {
        // touch-auto (touch group) vs touch-pan-x (touch-x group) — different groups, no conflict
        $result = $this->tw->merge('touch-auto touch-pan-x');
        $this->assertSame('touch-auto touch-pan-x', $result);
    }

    public function testTouchPanXConflict(): void
    {
        $this->assertSame('touch-pan-right', $this->tw->merge('touch-pan-x touch-pan-right'));
    }

    public function testTouchPanYConflict(): void
    {
        $this->assertSame('touch-pan-down', $this->tw->merge('touch-pan-y touch-pan-down'));
    }

    // =========================================================================
    // text-overflow (fixed: text-ellipsis and text-clip use text- prefix)
    // =========================================================================

    public function testTextOverflowConflict(): void
    {
        $this->assertSame('text-ellipsis', $this->tw->merge('truncate text-ellipsis'));
    }

    public function testTextClipConflict(): void
    {
        $this->assertSame('text-clip', $this->tw->merge('text-ellipsis text-clip'));
    }

    public function testTruncateConflict(): void
    {
        $this->assertSame('truncate', $this->tw->merge('text-clip truncate'));
    }

    // =========================================================================
    // Arbitrary variants [&:hover], [.parent_&]
    // =========================================================================

    public function testArbitraryVariantIsKeptSeparate(): void
    {
        // [&:hover]:p-4 and hover:p-4 are different variants → no conflict
        $result = $this->tw->merge('[&:hover]:p-4 hover:p-4');
        $this->assertSame('[&:hover]:p-4 hover:p-4', $result);
    }

    public function testArbitraryVariantSelfConflict(): void
    {
        $result = $this->tw->merge('[&:hover]:p-2 [&:hover]:p-4');
        $this->assertSame('[&:hover]:p-4', $result);
    }

    public function testArbitraryVariantWithColonInsideBrackets(): void
    {
        // The colon inside [&:nth-child(3)] must NOT split the modifier
        $result = $this->tw->merge('[&:nth-child(3)]:text-sm [&:nth-child(3)]:text-lg');
        $this->assertSame('[&:nth-child(3)]:text-lg', $result);
    }

    // =========================================================================
    // Scale conflicts (scale vs scale-x / scale-y)
    // =========================================================================

    public function testScaleConflictsWithAxes(): void
    {
        $result = $this->tw->merge('scale-x-75 scale-y-75 scale-110');
        $this->assertStringNotContainsString('scale-x-75', $result);
        $this->assertStringNotContainsString('scale-y-75', $result);
        $this->assertStringContainsString('scale-110', $result);
    }

    // =========================================================================
    // Empty-string result is cached correctly
    // =========================================================================

    public function testEmptyMergeResultCached(): void
    {
        // Merging only empty strings should give '' and be handled gracefully
        $first  = $this->tw->merge('');
        $second = $this->tw->merge('');
        $this->assertSame('', $first);
        $this->assertSame('', $second);
    }

    // =========================================================================
    // Overflow full conflict chain
    // =========================================================================

    public function testOverflowConflictsWithXAndY(): void
    {
        $result = $this->tw->merge('overflow-x-auto overflow-y-auto overflow-hidden');
        $this->assertStringNotContainsString('overflow-x-auto', $result);
        $this->assertStringNotContainsString('overflow-y-auto', $result);
        $this->assertStringContainsString('overflow-hidden', $result);
    }

    // =========================================================================
    // Scroll margin / padding conflicts
    // =========================================================================

    public function testScrollMarginConflict(): void
    {
        $this->assertSame('scroll-m-4', $this->tw->merge('scroll-m-2 scroll-m-4'));
    }

    public function testScrollMarginXConflictsWithSides(): void
    {
        $result = $this->tw->merge('scroll-mr-2 scroll-ml-2 scroll-mx-4');
        $this->assertStringNotContainsString('scroll-mr-2', $result);
        $this->assertStringNotContainsString('scroll-ml-2', $result);
        $this->assertStringContainsString('scroll-mx-4', $result);
    }

    public function testScrollPaddingConflict(): void
    {
        $this->assertSame('scroll-p-4', $this->tw->merge('scroll-p-2 scroll-p-4'));
    }

    // =========================================================================
    // Ring conflicts
    // =========================================================================

    public function testRingWidthConflict(): void
    {
        $this->assertSame('ring-4', $this->tw->merge('ring-2 ring-4'));
    }

    public function testRingColorConflict(): void
    {
        $this->assertSame('ring-blue-500', $this->tw->merge('ring-red-500 ring-blue-500'));
    }

    public function testRingOffsetConflict(): void
    {
        $this->assertSame('ring-offset-4', $this->tw->merge('ring-offset-2 ring-offset-4'));
    }

    // =========================================================================
    // Aspect ratio
    // =========================================================================

    public function testAspectRatioConflict(): void
    {
        $this->assertSame('aspect-video', $this->tw->merge('aspect-square aspect-video'));
    }

    public function testAspectRatioArbitrary(): void
    {
        $this->assertSame('aspect-[4/3]', $this->tw->merge('aspect-video aspect-[4/3]'));
    }

    // =========================================================================
    // Grid conflicts
    // =========================================================================

    public function testGridFlowConflict(): void
    {
        $this->assertSame('grid-flow-col', $this->tw->merge('grid-flow-row grid-flow-col'));
    }

    public function testAutoColsConflict(): void
    {
        $this->assertSame('auto-cols-max', $this->tw->merge('auto-cols-auto auto-cols-max'));
    }

    // =========================================================================
    // Object position / fit
    // =========================================================================

    public function testObjectFitConflict(): void
    {
        $this->assertSame('object-cover', $this->tw->merge('object-contain object-cover'));
    }

    public function testObjectPositionConflict(): void
    {
        $this->assertSame('object-center', $this->tw->merge('object-top object-center'));
    }

    // =========================================================================
    // Visibility
    // =========================================================================

    public function testVisibilityConflict(): void
    {
        $this->assertSame('invisible', $this->tw->merge('visible invisible'));
        $this->assertSame('collapse', $this->tw->merge('invisible collapse'));
    }

    // =========================================================================
    // Isolation
    // =========================================================================

    public function testIsolationConflict(): void
    {
        $this->assertSame('isolation-auto', $this->tw->merge('isolate isolation-auto'));
    }

    // =========================================================================
    // Columns
    // =========================================================================

    public function testColumnsConflict(): void
    {
        $this->assertSame('columns-3', $this->tw->merge('columns-2 columns-3'));
    }

    // =========================================================================
    // Will change
    // =========================================================================

    public function testWillChangeConflict(): void
    {
        $this->assertSame('will-change-transform', $this->tw->merge('will-change-scroll will-change-transform'));
    }

    // =========================================================================
    // Mix blend / bg blend
    // =========================================================================

    public function testMixBlendConflict(): void
    {
        $this->assertSame('mix-blend-multiply', $this->tw->merge('mix-blend-normal mix-blend-multiply'));
    }

    public function testBgBlendConflict(): void
    {
        $this->assertSame('bg-blend-screen', $this->tw->merge('bg-blend-normal bg-blend-screen'));
    }

    // =========================================================================
    // Backdrop filter group conflicts
    // =========================================================================

    public function testBackdropBlurConflict(): void
    {
        $this->assertSame('backdrop-blur-lg', $this->tw->merge('backdrop-blur backdrop-blur-lg'));
    }

    public function testBackdropBrightnessConflict(): void
    {
        $this->assertSame('backdrop-brightness-150', $this->tw->merge('backdrop-brightness-100 backdrop-brightness-150'));
    }

    // =========================================================================
    // Animate
    // =========================================================================

    public function testAnimateConflict(): void
    {
        $this->assertSame('animate-spin', $this->tw->merge('animate-pulse animate-spin'));
    }

    // =========================================================================
    // List style
    // =========================================================================

    public function testListStyleTypeConflict(): void
    {
        $this->assertSame('list-decimal', $this->tw->merge('list-none list-decimal'));
    }

    public function testListStylePositionConflict(): void
    {
        $this->assertSame('list-outside', $this->tw->merge('list-inside list-outside'));
    }

    // =========================================================================
    // Content
    // =========================================================================

    public function testContentConflict(): void
    {
        $this->assertSame("content-['world']", $this->tw->merge("content-none content-['world']"));
    }

    // =========================================================================
    // Place content / items / self
    // =========================================================================

    public function testPlaceContentConflict(): void
    {
        $this->assertSame('place-content-end', $this->tw->merge('place-content-center place-content-end'));
    }

    public function testPlaceItemsConflict(): void
    {
        $this->assertSame('place-items-end', $this->tw->merge('place-items-center place-items-end'));
    }

    public function testPlaceSelfConflict(): void
    {
        $this->assertSame('place-self-end', $this->tw->merge('place-self-center place-self-end'));
    }

    // =========================================================================
    // Ease / delay
    // =========================================================================

    public function testEaseConflict(): void
    {
        $this->assertSame('ease-out', $this->tw->merge('ease-linear ease-out'));
    }

    public function testDelayConflict(): void
    {
        $this->assertSame('delay-300', $this->tw->merge('delay-150 delay-300'));
    }

    // =========================================================================
    // Arbitrary variable support (CSS custom properties via ())
    // =========================================================================

    public function testArbitraryVariablePadding(): void
    {
        $this->assertSame('p-(--my-space)', $this->tw->merge('p-4 p-(--my-space)'));
    }

    public function testArbitraryVariableColor(): void
    {
        $this->assertSame('bg-(--brand)', $this->tw->merge('bg-red-500 bg-(--brand)'));
    }

    // =========================================================================
    // Skew
    // =========================================================================

    public function testSkewXConflict(): void
    {
        $this->assertSame('skew-x-6', $this->tw->merge('skew-x-3 skew-x-6'));
    }

    public function testSkewYConflict(): void
    {
        $this->assertSame('skew-y-6', $this->tw->merge('skew-y-3 skew-y-6'));
    }

    // =========================================================================
    // Translate conflicts
    // =========================================================================

    public function testTranslateXConflict(): void
    {
        $this->assertSame('translate-x-4', $this->tw->merge('translate-x-2 translate-x-4'));
    }

    public function testTranslateYConflict(): void
    {
        $this->assertSame('translate-y-4', $this->tw->merge('translate-y-2 translate-y-4'));
    }

    // =========================================================================
    // Hyphens / whitespace / break
    // =========================================================================

    public function testHyphensConflict(): void
    {
        $this->assertSame('hyphens-auto', $this->tw->merge('hyphens-none hyphens-auto'));
    }

    public function testWhitespaceConflict(): void
    {
        $this->assertSame('whitespace-nowrap', $this->tw->merge('whitespace-normal whitespace-nowrap'));
    }

    public function testWordBreakConflict(): void
    {
        $this->assertSame('break-all', $this->tw->merge('break-normal break-all'));
    }

    // =========================================================================
    // Resize
    // =========================================================================

    public function testResizeConflict(): void
    {
        $this->assertSame('resize-y', $this->tw->merge('resize-none resize-y'));
    }

    // =========================================================================
    // Appearance
    // =========================================================================

    public function testAppearanceConflict(): void
    {
        $this->assertSame('appearance-auto', $this->tw->merge('appearance-none appearance-auto'));
    }

    // =========================================================================
    // SVG fill / stroke
    // =========================================================================

    public function testFillConflict(): void
    {
        $this->assertSame('fill-blue-500', $this->tw->merge('fill-red-500 fill-blue-500'));
    }

    public function testStrokeColorConflict(): void
    {
        $this->assertSame('stroke-blue-500', $this->tw->merge('stroke-red-500 stroke-blue-500'));
    }

    public function testStrokeWidthConflict(): void
    {
        $this->assertSame('stroke-2', $this->tw->merge('stroke-1 stroke-2'));
    }

    // =========================================================================
    // Snap
    // =========================================================================

    public function testSnapAlignConflict(): void
    {
        $this->assertSame('snap-end', $this->tw->merge('snap-start snap-end'));
    }

    public function testSnapTypeConflict(): void
    {
        $this->assertSame('snap-y', $this->tw->merge('snap-x snap-y'));
    }

    // =========================================================================
    // Float / clear
    // =========================================================================

    public function testFloatConflict(): void
    {
        $this->assertSame('float-right', $this->tw->merge('float-left float-right'));
    }

    public function testClearConflict(): void
    {
        $this->assertSame('clear-both', $this->tw->merge('clear-left clear-both'));
    }

    // =========================================================================
    // Order / basis
    // =========================================================================

    public function testOrderConflict(): void
    {
        $this->assertSame('order-last', $this->tw->merge('order-first order-last'));
    }

    public function testFlexBasisConflict(): void
    {
        $this->assertSame('basis-1/2', $this->tw->merge('basis-full basis-1/2'));
    }

    // =========================================================================
    // Caption
    // =========================================================================

    public function testCaptionConflict(): void
    {
        $this->assertSame('caption-bottom', $this->tw->merge('caption-top caption-bottom'));
    }

    // =========================================================================
    // Forced color adjust
    // =========================================================================

    public function testForcedColorAdjustConflict(): void
    {
        $this->assertSame('forced-color-adjust-none', $this->tw->merge('forced-color-adjust-auto forced-color-adjust-none'));
    }

    // =========================================================================
    // Accent color
    // =========================================================================

    public function testAccentColorConflict(): void
    {
        $this->assertSame('accent-blue-500', $this->tw->merge('accent-red-500 accent-blue-500'));
    }

    // =========================================================================
    // Caret color
    // =========================================================================

    public function testCaretColorConflict(): void
    {
        $this->assertSame('caret-blue-500', $this->tw->merge('caret-red-500 caret-blue-500'));
    }

    // =========================================================================
    // Placeholder color
    // =========================================================================

    public function testPlaceholderColorConflict(): void
    {
        $this->assertSame('placeholder-blue-500', $this->tw->merge('placeholder-red-500 placeholder-blue-500'));
    }

    // =========================================================================
    // Box decoration / box sizing
    // =========================================================================

    public function testBoxDecorationConflict(): void
    {
        $this->assertSame('box-decoration-clone', $this->tw->merge('box-decoration-slice box-decoration-clone'));
    }

    public function testBoxSizingConflict(): void
    {
        $this->assertSame('box-content', $this->tw->merge('box-border box-content'));
    }

    // =========================================================================
    // Transform
    // =========================================================================

    public function testTransformConflict(): void
    {
        $this->assertSame('transform-gpu', $this->tw->merge('transform transform-gpu'));
    }

    public function testTransformOriginConflict(): void
    {
        $this->assertSame('origin-center', $this->tw->merge('origin-top origin-center'));
    }

    // =========================================================================
    // Arbitrary CSS properties  [property:value]  and  [--var:value]
    // =========================================================================

    public function testArbitraryCssVars(): void
    {
        // Two declarations for the same CSS custom property — later wins
        $this->assertSame('[--grid-column-span:5]', $this->tw->merge('[--grid-column-span:12] [--grid-column-span:5]'));
    }

    public function testArbitraryCss(): void
    {
        // Two declarations for the same CSS property — later wins
        $this->assertSame('[font-size:2rem]', $this->tw->merge('[font-size:1rem] [font-size:2rem]'));
    }

    public function testArbitraryCssNoConflictDifferentProperties(): void
    {
        // Different properties — both kept
        $result = $this->tw->merge('[font-size:1rem] [color:red]');
        $this->assertSame('[font-size:1rem] [color:red]', $result);
    }

    public function testArbitraryCssWithVariant(): void
    {
        // Same property under same variant — later wins
        $this->assertSame('hover:[font-size:2rem]', $this->tw->merge('hover:[font-size:1rem] hover:[font-size:2rem]'));
    }

    public function testArbitraryCssVariantsDontConflict(): void
    {
        // Same property but different variants — no conflict
        $result = $this->tw->merge('hover:[font-size:1rem] focus:[font-size:2rem]');
        $this->assertSame('hover:[font-size:1rem] focus:[font-size:2rem]', $result);
    }

    public function testArbitraryCssWithKnownClasses(): void
    {
        // Arbitrary property mixed with regular classes — each resolves independently
        $result = $this->tw->merge('p-4 [font-size:1rem] p-8 [font-size:2rem]');
        $this->assertSame('p-8 [font-size:2rem]', $result);
    }

    public function testArbitraryCssImportant(): void
    {
        // Important modifier scopes separately from non-important
        $result = $this->tw->merge('[font-size:1rem] ![font-size:2rem]');
        $this->assertStringContainsString('[font-size:1rem]', $result);
        $this->assertStringContainsString('![font-size:2rem]', $result);
    }
}
