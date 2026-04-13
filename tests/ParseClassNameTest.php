<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\Lib\ParseClassName;

class ParseClassNameTest extends TestCase
{
    // =========================================================================
    // parseClassName
    // =========================================================================

    public function testSimpleClass(): void
    {
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
        $r = ParseClassName::parseClassName('hover:focus:p-4');
        $this->assertSame(['hover', 'focus'], $r['modifiers']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testParsesImportantModifier(): void
    {
        $r = ParseClassName::parseClassName('!p-4');
        $this->assertTrue($r['hasImportantModifier']);
        $this->assertSame('p-4', $r['baseClassName']);
        $this->assertSame([], $r['modifiers']);
    }

    public function testImportantWithVariant(): void
    {
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

    public function testArbitraryVariant(): void
    {
        // Colon inside [...] must NOT be treated as a modifier separator
        $r = ParseClassName::parseClassName('[&:hover]:p-4');
        $this->assertSame(['[&:hover]'], $r['modifiers']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testArbitraryVariantWithParentheses(): void
    {
        $r = ParseClassName::parseClassName('[&:nth-child(3)]:p-4');
        $this->assertSame(['[&:nth-child(3)]'], $r['modifiers']);
        $this->assertSame('p-4', $r['baseClassName']);
    }

    public function testPostfixModifier(): void
    {
        $r = ParseClassName::parseClassName('bg-red-500/50');
        $this->assertSame([], $r['modifiers']);
        $this->assertSame('bg-red-500/50', $r['baseClassName']);
        // Position of '/' within base class name
        $this->assertNotNull($r['maybePostfixModifierPosition']);
        // 'bg-red-500/50' — slash is at index 10
        $this->assertSame(10, $r['maybePostfixModifierPosition']);
    }

    public function testPostfixWithVariant(): void
    {
        $r = ParseClassName::parseClassName('hover:bg-red-500/50');
        $this->assertSame(['hover'], $r['modifiers']);
        $this->assertSame('bg-red-500/50', $r['baseClassName']);
        $this->assertNotNull($r['maybePostfixModifierPosition']);
    }

    public function testNoPostfixModifier(): void
    {
        $r = ParseClassName::parseClassName('text-lg');
        $this->assertNull($r['maybePostfixModifierPosition']);
    }

    public function testParsesDarkModeVariant(): void
    {
        $r = ParseClassName::parseClassName('dark:bg-gray-900');
        $this->assertSame(['dark'], $r['modifiers']);
        $this->assertSame('bg-gray-900', $r['baseClassName']);
    }

    // =========================================================================
    // sortModifiers
    // =========================================================================

    public function testSortModifiersSortsAlphabetically(): void
    {
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
        $this->assertSame(['dark', 'focus', 'hover'], ParseClassName::sortModifiers(['dark', 'focus', 'hover']));
    }

    public function testSortModifiersArbitraryVariantNotSorted(): void
    {
        // Arbitrary variants ([...]) are kept in order relative to each other
        $result = ParseClassName::sortModifiers(['hover', '[&:nth-child(2)]']);
        $this->assertCount(2, $result);
    }
}
