<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\Lib\ParseClassName;

/**
 * ParseClassNameTest — unit tests for ParseClassName::parseClassName() and
 * ParseClassName::sortModifiers().
 *
 * parseClassName() splits a single class token into four components:
 *   modifiers                  — variant prefixes e.g. ['hover', 'focus']
 *   hasImportantModifier       — true when '!' is present
 *   baseClassName              — the bare utility name e.g. 'p-4'
 *   maybePostfixModifierPosition — byte offset of '/' in baseClassName, or null
 *
 * KEY CORRECTNESS REQUIREMENTS
 * ────────────────────────────
 * • A ':' inside [...] or (...) must NOT be treated as a modifier separator.
 *   [&:hover]:p-4  → modifiers=['[&:hover]'], NOT ['[&', 'hover]']
 *
 * • The postfix '/' position must be relative to the START of baseClassName
 *   (after the leading '!' is stripped), so that MergeClassList can call
 *   substr($base, 0, $pos) to get 'bg-red-500' from 'bg-red-500/50'.
 *
 * sortModifiers() canonicalises the order so 'hover:focus:p' and
 * 'focus:hover:p' produce the same conflict key in MergeClassList.
 */
class ParseClassNameTest extends TestCase
{
    // =========================================================================
    // parseClassName — basic structure
    // =========================================================================

    public function testSimpleClass(): void
    {
        // A plain class has no modifiers, no important flag, and no postfix.
        $r = ParseClassName::parseClassName('p-4');
        $this->assertSame([], $r['modifiers']);
        $this->assertFalse($r['hasImportantModifier']);
        $this->assertSame('p-4', $r['baseClassName']);
        $this->assertNull($r['maybePostfixModifierPosition']);
    }

    public function testSingleVariant(): void
    {
        $r = ParseClassName::parseClassName('hover:p-4');
        $this->assertSame(['hover'], $r['modifiers']);
        $this->assertFalse($r['hasImportantModifier']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testParsesMultipleVariants(): void
    {
        // Multiple variants are collected in left-to-right order.
        $r = ParseClassName::parseClassName('hover:focus:p-4');
        $this->assertSame(['hover', 'focus'], $r['modifiers']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testParsesDarkModeVariant(): void
    {
        $r = ParseClassName::parseClassName('dark:bg-gray-900');
        $this->assertSame(['dark'], $r['modifiers']);
        $this->assertSame('bg-gray-900', $r['baseClassName']);
    }

    // =========================================================================
    // parseClassName — important modifier  '!'
    // =========================================================================

    public function testParsesImportantModifier(): void
    {
        // '!' at the start of the base class marks it as important.
        $r = ParseClassName::parseClassName('!p-4');
        $this->assertTrue($r['hasImportantModifier']);
        $this->assertSame('p-4', $r['baseClassName']);
        $this->assertSame([], $r['modifiers']);
    }

    public function testImportantWithVariant(): void
    {
        // '!' comes after the variant separator but before the utility name.
        $r = ParseClassName::parseClassName('hover:!p-4');
        $this->assertSame(['hover'], $r['modifiers']);
        $this->assertTrue($r['hasImportantModifier']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testImportantWithMultipleVariants(): void
    {
        $r = ParseClassName::parseClassName('md:hover:!text-sm');
        $this->assertSame(['md', 'hover'], $r['modifiers']);
        $this->assertTrue($r['hasImportantModifier']);
        $this->assertSame('text-sm', $r['baseClassName']);
    }

    // =========================================================================
    // parseClassName — arbitrary variants  (colon inside brackets)
    // =========================================================================

    public function testArbitraryVariant(): void
    {
        // The ':' inside [&:hover] is inside brackets and must NOT split the modifier.
        $r = ParseClassName::parseClassName('[&:hover]:p-4');
        $this->assertSame(['[&:hover]'], $r['modifiers']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testArbitraryVariantWithParentheses(): void
    {
        // Parentheses inside brackets must also be handled without splitting.
        $r = ParseClassName::parseClassName('[&:nth-child(3)]:p-4');
        $this->assertSame(['[&:nth-child(3)]'], $r['modifiers']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    // =========================================================================
    // parseClassName — postfix modifier  '/value'  (opacity shorthand)
    // =========================================================================

    public function testPostfixModifier(): void
    {
        // 'bg-red-500/50' — the '/' is the postfix separator.
        // maybePostfixModifierPosition is the byte offset of '/' in baseClassName.
        $r = ParseClassName::parseClassName('bg-red-500/50');
        $this->assertSame([], $r['modifiers']);
        $this->assertSame('bg-red-500/50', $r['baseClassName']);
        $this->assertNotNull($r['maybePostfixModifierPosition']);
        // 'bg-red-500' is 10 chars → '/' is at index 10 in the base string.
        $this->assertSame(10, $r['maybePostfixModifierPosition']);
    }

    public function testPostfixWithVariant(): void
    {
        // The postfix position must be relative to the base, not the full class string.
        $r = ParseClassName::parseClassName('hover:bg-red-500/50');
        $this->assertSame(['hover'], $r['modifiers']);
        $this->assertSame('bg-red-500/50', $r['baseClassName']);
        $this->assertNotNull($r['maybePostfixModifierPosition']);
    }

    public function testNoPostfixModifier(): void
    {
        // A class with no '/' has a null postfix position.
        $r = ParseClassName::parseClassName('text-lg');
        $this->assertNull($r['maybePostfixModifierPosition']);
    }

    // =========================================================================
    // sortModifiers — canonical conflict-key ordering
    // =========================================================================

    public function testSortModifiersSortsAlphabetically(): void
    {
        // 'hover:focus:p-4' and 'focus:hover:p-4' must produce the same conflict key.
        $this->assertSame(['focus', 'hover'], ParseClassName::sortModifiers(['hover', 'focus']));
    }

    public function testSortModifiersSingleElement(): void
    {
        $this->assertSame(['hover'], ParseClassName::sortModifiers(['hover']));
    }

    public function testSortModifiersEmpty(): void
    {
        $this->assertSame([], ParseClassName::sortModifiers([]));
    }

    public function testSortModifiersAlreadySorted(): void
    {
        $sorted = ['dark', 'focus', 'hover'];
        $this->assertSame($sorted, ParseClassName::sortModifiers($sorted));
    }

    public function testSortModifiersArbitraryVariantNotSorted(): void
    {
        // Arbitrary variants ([&:nth-child(2)]) must preserve their relative order
        // because their position can be semantically significant.
        $result = ParseClassName::sortModifiers(['hover', '[&:nth-child(2)]']);
        $this->assertCount(2, $result);
        // The arbitrary variant must still be present.
        $this->assertContains('[&:nth-child(2)]', $result);
    }
}
