<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\Lib\Validators;

/**
 * ValidatorsTest — unit tests for every public method on the Validators class.
 *
 * Validators are small predicate functions used by DefaultConfig to describe
 * what suffix values a class group accepts.  For example, the 'p' (padding)
 * group uses isNumber, isFraction, isArbitraryLength, and isArbitraryVariable
 * to match 'p-4', 'p-1/2', 'p-[20px]', and 'p-(--my-spacing)' respectively.
 *
 * WHAT WE TEST
 * ────────────
 * • Each validator accepts the inputs it should and rejects those it shouldn't.
 * • The labelled-vs-unlabelled distinction for isArbitraryVariable* variants:
 *   - Bare CSS variables like (--brand) must NOT match labelled validators
 *     (isArbitraryVariablePosition, isArbitraryVariableLength, etc.) because
 *     they would otherwise steal the class from the colour / spacing group.
 *   - They MUST match the unlabelled isArbitraryVariable catch-all.
 */
class ValidatorsTest extends TestCase
{
    // =========================================================================
    // Square-bracket arbitrary values  [value]
    // =========================================================================

    public function testIsArbitraryValue(): void
    {
        // Any content inside [...] qualifies as an arbitrary value.
        $this->assertTrue(Validators::isArbitraryValue('[3px]'));
        $this->assertTrue(Validators::isArbitraryValue('[#fff]'));
        $this->assertTrue(Validators::isArbitraryValue('[calc(100%-2px)]'));

        // Without brackets it is not an arbitrary value.
        $this->assertFalse(Validators::isArbitraryValue('3px'));
        $this->assertFalse(Validators::isArbitraryValue(''));

        // Parenthesis syntax (v4 CSS variable) is a separate form — not matched here.
        $this->assertFalse(Validators::isArbitraryValue('(3px)'));
    }

    public function testIsArbitraryLength(): void
    {
        // Recognised length units are accepted.
        $this->assertTrue(Validators::isArbitraryLength('[3px]'));
        $this->assertTrue(Validators::isArbitraryLength('[4em]'));
        $this->assertTrue(Validators::isArbitraryLength('[2.5rem]'));

        // Explicit 'length:' label is accepted regardless of content.
        $this->assertTrue(Validators::isArbitraryLength('[length:var(--my-var)]'));

        // Non-length content is rejected (colour and other labels are different groups).
        $this->assertFalse(Validators::isArbitraryLength('[red]'));
        $this->assertFalse(Validators::isArbitraryLength('[image:url(x.png)]'));

        // Must be inside brackets — bare value is not arbitrary.
        $this->assertFalse(Validators::isArbitraryLength('3px'));
    }

    public function testIsArbitraryNumber(): void
    {
        // A plain number inside brackets matches.
        $this->assertTrue(Validators::isArbitraryNumber('[450]'));

        // Explicit 'number:' label is accepted.
        $this->assertTrue(Validators::isArbitraryNumber('[number:var(--value)]'));

        // Non-numeric arbitrary values are rejected.
        $this->assertFalse(Validators::isArbitraryNumber('[abc]'));

        // Bare value without brackets never matches.
        $this->assertFalse(Validators::isArbitraryNumber('450'));
    }

    public function testIsArbitraryImage(): void
    {
        // url() and linear-gradient() are treated as image values.
        $this->assertTrue(Validators::isArbitraryImage("[url('/path.png')]"));
        $this->assertTrue(Validators::isArbitraryImage('[linear-gradient(to-right,red,blue)]'));

        // Plain colour or length values are not images.
        $this->assertFalse(Validators::isArbitraryImage('[red]'));
        $this->assertFalse(Validators::isArbitraryImage('[3px]'));
    }

    // =========================================================================
    // Parenthesis arbitrary variables  (--var)  — Tailwind v4 syntax
    // =========================================================================

    public function testIsArbitraryVariable(): void
    {
        // Any content inside (...) qualifies as an arbitrary variable.
        $this->assertTrue(Validators::isArbitraryVariable('(--my-var)'));
        $this->assertTrue(Validators::isArbitraryVariable('(--brand)'));

        // Square-bracket syntax is a different form.
        $this->assertFalse(Validators::isArbitraryVariable('[--my-var]'));

        // Bare value without parentheses is not an arbitrary variable.
        $this->assertFalse(Validators::isArbitraryVariable('my-var'));
        $this->assertFalse(Validators::isArbitraryVariable('--brand'));
    }

    public function testIsArbitraryVariableLabelledOnly(): void
    {
        // ── Critical label-disambiguation rule ───────────────────────────────
        // Labelled validators (isArbitraryVariableLength, *Position, *Size, *Image)
        // must ONLY match when the explicit 'label:' prefix is present.
        //
        // A bare CSS variable like (--brand) must NOT match any labelled validator.
        // This ensures (--brand) falls through to the isAny colour fallback in
        // bg-color rather than being mis-attributed to bg-position or bg-size.

        // Length — labelled form matches, bare var does not.
        $this->assertTrue(Validators::isArbitraryVariableLength('(length:--my-var)'));
        $this->assertFalse(Validators::isArbitraryVariableLength('(--my-var)'));

        // Position — same rule.
        $this->assertTrue(Validators::isArbitraryVariablePosition('(position:center)'));
        $this->assertFalse(Validators::isArbitraryVariablePosition('(--brand)'));

        // Size — same rule.
        $this->assertTrue(Validators::isArbitraryVariableSize('(size:200px)'));
        $this->assertFalse(Validators::isArbitraryVariableSize('(--brand)'));

        // Image — same rule.
        $this->assertTrue(Validators::isArbitraryVariableImage('(image:url(x.png))'));
        $this->assertFalse(Validators::isArbitraryVariableImage('(--brand)'));
    }

    public function testIsArbitraryVariableUnlabelledMatchesBareVars(): void
    {
        // The unlabelled isArbitraryVariable is the catch-all for (--*) references.
        // It matches any CSS variable and any labelled expression.
        $this->assertTrue(Validators::isArbitraryVariable('(--brand)'));
        $this->assertTrue(Validators::isArbitraryVariable('(--my-color)'));

        // Square-bracket form is a different syntax — never matches.
        $this->assertFalse(Validators::isArbitraryVariable('[--brand]'));
        $this->assertFalse(Validators::isArbitraryVariable('--brand'));
    }

    // =========================================================================
    // Numeric and fractional validators
    // =========================================================================

    public function testIsInteger(): void
    {
        // Only non-negative whole numbers match.
        $this->assertTrue(Validators::isInteger('0'));
        $this->assertTrue(Validators::isInteger('123'));

        // Decimals, negative values, and text are rejected.
        $this->assertFalse(Validators::isInteger('1.5'));
        $this->assertFalse(Validators::isInteger('abc'));
        $this->assertFalse(Validators::isInteger('-1'));
    }

    public function testIsNumber(): void
    {
        // Integers and decimals (including leading-dot form) match.
        $this->assertTrue(Validators::isNumber('0'));
        $this->assertTrue(Validators::isNumber('1.5'));
        $this->assertTrue(Validators::isNumber('.5'));

        // Text and values with units do not match.
        $this->assertFalse(Validators::isNumber('abc'));
        $this->assertFalse(Validators::isNumber('1px'));
    }

    public function testIsPercent(): void
    {
        $this->assertTrue(Validators::isPercent('50%'));
        $this->assertTrue(Validators::isPercent('12.5%'));

        // A number without '%' is not a percentage.
        $this->assertFalse(Validators::isPercent('50'));
        $this->assertFalse(Validators::isPercent('50px'));
    }

    public function testIsFraction(): void
    {
        $this->assertTrue(Validators::isFraction('1/2'));
        $this->assertTrue(Validators::isFraction('3/4'));

        // Incomplete or missing slash forms are rejected.
        $this->assertFalse(Validators::isFraction('1'));
        $this->assertFalse(Validators::isFraction('1/'));
        $this->assertFalse(Validators::isFraction('/2'));
    }

    // =========================================================================
    // Sentinel validators
    // =========================================================================

    public function testIsAny(): void
    {
        // isAny is the catch-all — it returns true for every input including empty string.
        // Used as the last validator in $colors so that any unknown colour token is
        // attributed to the colour group rather than falling through to "unknown".
        $this->assertTrue(Validators::isAny('anything'));
        $this->assertTrue(Validators::isAny(''));
    }

    public function testIsNever(): void
    {
        // isNever always returns false.  Used as a no-op $testValue in
        // isArbitraryPosition and isArbitrarySize so that only the explicit
        // 'position:' / 'size:' labels are accepted — no heuristic fallback.
        $this->assertFalse(Validators::isNever('anything'));
        $this->assertFalse(Validators::isNever(''));
    }
}
