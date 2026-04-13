<?php

declare(strict_types=1);

namespace TailwindMerge\Tests;

use PHPUnit\Framework\TestCase;
use TailwindMerge\Lib\Validators;

class ValidatorsTest extends TestCase
{
    public function testIsArbitraryValue(): void
    {
        $this->assertTrue(Validators::isArbitraryValue('[3px]'));
        $this->assertTrue(Validators::isArbitraryValue('[#fff]'));
        $this->assertTrue(Validators::isArbitraryValue('[calc(100%-2px)]'));
        $this->assertFalse(Validators::isArbitraryValue('3px'));
        $this->assertFalse(Validators::isArbitraryValue(''));
        $this->assertFalse(Validators::isArbitraryValue('(3px)'));
    }

    public function testIsArbitraryVariable(): void
    {
        $this->assertTrue(Validators::isArbitraryVariable('(--my-var)'));
        $this->assertFalse(Validators::isArbitraryVariable('[--my-var]'));
        $this->assertFalse(Validators::isArbitraryVariable('my-var'));
    }

    public function testIsInteger(): void
    {
        $this->assertTrue(Validators::isInteger('0'));
        $this->assertTrue(Validators::isInteger('123'));
        $this->assertFalse(Validators::isInteger('1.5'));
        $this->assertFalse(Validators::isInteger('abc'));
        $this->assertFalse(Validators::isInteger('-1'));
    }

    public function testIsNumber(): void
    {
        $this->assertTrue(Validators::isNumber('0'));
        $this->assertTrue(Validators::isNumber('1.5'));
        $this->assertTrue(Validators::isNumber('.5'));
        $this->assertFalse(Validators::isNumber('abc'));
        $this->assertFalse(Validators::isNumber('1px'));
    }

    public function testIsPercent(): void
    {
        $this->assertTrue(Validators::isPercent('50%'));
        $this->assertTrue(Validators::isPercent('12.5%'));
        $this->assertFalse(Validators::isPercent('50'));
        $this->assertFalse(Validators::isPercent('50px'));
    }

    public function testIsFraction(): void
    {
        $this->assertTrue(Validators::isFraction('1/2'));
        $this->assertTrue(Validators::isFraction('3/4'));
        $this->assertFalse(Validators::isFraction('1'));
        $this->assertFalse(Validators::isFraction('1/'));
        $this->assertFalse(Validators::isFraction('/2'));
    }

    public function testIsAny(): void
    {
        $this->assertTrue(Validators::isAny('anything'));
        $this->assertTrue(Validators::isAny(''));
    }

    public function testIsNever(): void
    {
        $this->assertFalse(Validators::isNever('anything'));
        $this->assertFalse(Validators::isNever(''));
    }

    public function testIsArbitraryLength(): void
    {
        $this->assertTrue(Validators::isArbitraryLength('[3px]'));
        $this->assertTrue(Validators::isArbitraryLength('[4em]'));
        $this->assertTrue(Validators::isArbitraryLength('[length:var(--my-var)]'));
        $this->assertFalse(Validators::isArbitraryLength('[red]'));
        $this->assertFalse(Validators::isArbitraryLength('3px'));
    }

    public function testIsArbitraryNumber(): void
    {
        $this->assertTrue(Validators::isArbitraryNumber('[450]'));
        $this->assertTrue(Validators::isArbitraryNumber('[number:var(--value)]'));
        $this->assertFalse(Validators::isArbitraryNumber('[abc]'));
        $this->assertFalse(Validators::isArbitraryNumber('450'));
    }

    public function testIsArbitraryImage(): void
    {
        $this->assertTrue(Validators::isArbitraryImage("[url('/path.png')]"));
        $this->assertTrue(Validators::isArbitraryImage('[linear-gradient(to-right,red,blue)]'));
        $this->assertFalse(Validators::isArbitraryImage('[red]'));
        $this->assertFalse(Validators::isArbitraryImage('[3px]'));
    }
}
