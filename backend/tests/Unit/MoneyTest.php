<?php

namespace Tests\Unit;

use App\Domain\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parsing_and_formatting(): void
    {
        $this->assertSame(123450, Money::toCents('1234.5'));
        $this->assertSame(123450, Money::toCents('1234,50'));
        $this->assertSame(100000, Money::toCents(1000));
        $this->assertSame(-5005, Money::toCents('-50.05'));
        $this->assertSame('1234.50', Money::fromCents(123450));
        $this->assertSame('-0.05', Money::fromCents(-5));
        $this->assertSame('0.00', Money::fromCents(0));
    }

    public function test_percent_rounding(): void
    {
        $this->assertSame(8642, Money::percentOf(123456, '7.00'));   // 86,4192 → 86,42
        $this->assertSame(10000, Money::percentOf(100000, '10'));    // 1000 × %10 = 100
        $this->assertSame(1005, Money::percentOf(8040, '12.50'));    // 80,40 × %12,5 = 10,05
        $this->assertSame(1, Money::percentOf(10, '5.00'));          // 0,005 → 0,01 (yukarı)
        $this->assertSame(0, Money::percentOf(10000, '0'));
    }

    public function test_invalid_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toCents('12.345');
    }
}
