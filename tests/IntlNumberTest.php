<?php

namespace Codewiser\Intl\Tests;

use Codewiser\Intl\IntlNumber;
use NumberFormatter;
use PHPUnit\Framework\TestCase;

class IntlNumberTest extends TestCase
{
    public function test_decimal()
    {
        $fmt = new IntlNumber(1100.101, 'ru');
        $this->assertEquals('1 100,101', $fmt->decimal());
    }

    public function test_percent()
    {
        $fmt = new IntlNumber(.891, 'ru');
        $this->assertEquals('89,10 %', $fmt->percent([
            NumberFormatter::FRACTION_DIGITS => 2
        ]));
    }

    public function test_scientific()
    {
        $fmt = new IntlNumber(1100.101, 'ru');
        $this->assertEquals('1,100101E3', $fmt->scientific());
    }

    public function test_spellout()
    {
        $fmt = new IntlNumber(101, 'ru');
        $this->assertEquals('сто один', $fmt->spellout());

        $fmt = new IntlNumber(101, 'en');
        $this->assertEquals('one hundred one', $fmt->spellout());
    }

    public function test_currency()
    {
        $fmt = new IntlNumber(101, 'ru');
        $this->assertEquals('101,00 ₽', $fmt->currency('RUB'));

        $fmt = new IntlNumber(101, 'en');
        $this->assertEquals('€101.00', $fmt->currency('EUR'));
    }
}
