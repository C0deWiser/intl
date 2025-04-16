<?php

namespace Codewiser\Intl\Tests\Casts;

use Faker\Factory;
use Codewiser\Intl\Casts\Multilingual;
use PHPUnit\Framework\TestCase;

class MultilingualTest extends TestCase
{
    public function testToString()
    {
        $alt = new Multilingual([
            'ru'      => 'one',
            'ru_RU'   => 'two',
            'en_GB'   => 'four',
            'en-GB'   => 'five',
            'en_US'   => 'six',
            'en-US'   => 'seven',
            'de-DEVA' => 'nine',
            'de-DE'   => 'ten'
        ]);

        Multilingual::useLocale('ru', 'ru');
        $this->assertEquals('one', $alt->toString());

        Multilingual::useLocale('ru_RU');
        $this->assertEquals('two', $alt->toString());

        Multilingual::useLocale('en');
        $this->assertEquals('four', $alt->toString());

        Multilingual::useLocale('en-GB');
        $this->assertEquals('four', $alt->toString());

        Multilingual::useLocale('en_US');
        $this->assertEquals('six', $alt->toString());

        Multilingual::useLocale('de');
        $this->assertEquals('ten', $alt->toString());

        Multilingual::useLocale('it');
        $this->assertEquals('one', $alt->toString());

        // Arrays

        $alt = new Multilingual([
            'ru' => ['one', 'two']
        ]);

        Multilingual::useLocale('it');
        $this->assertEquals(['one', 'two'], $alt->toString());
        $this->assertEquals('["one","two"]', (string)$alt);
    }

    public function testCastGet()
    {
        Multilingual::useLocale('ru');
        $cast = Multilingual::castUsing([]);

        $value = [
            'ru' => Factory::create('ru_RU')->company(),
            'en' => Factory::create('en_GB')->company(),
        ];

        // json превращается в объект
        $casted = $cast->get(new TestMultilingualModel(), 'name', json_encode($value), ['name' => json_encode($value)]);
        $this->assertEquals(new Multilingual($value), $casted);

        // null остается null
        $casted = $cast->get(new TestMultilingualModel(), 'name', null, ['name' => null]);
        $this->assertNull($casted);

        // Arrays

        $value = [
            'ru' => [
                Factory::create('ru_RU')->company(),
                Factory::create('ru_RU')->company()
            ]
        ];

        // json превращается в объект
        $casted = $cast->get(new TestMultilingualModel(), 'name', json_encode($value), ['name' => json_encode($value)]);
        $this->assertEquals(new Multilingual($value), $casted);
    }

    public function testCastSet()
    {
        Multilingual::useLocale('ru');
        $cast = Multilingual::castUsing([]);

        $value = [
            'ru' => Factory::create('ru_RU')->company(),
            'en' => Factory::create('en_GB')->company(),
        ];

        // Строка заменяет значение и возвращается json
        $new = Factory::create('ru_RU')->company();
        $casted = $cast->set(new TestMultilingualModel(), 'name', $new, ['name' => json_encode($value)]);
        $this->assertEquals(json_encode([
            'ru' => $new,
            'en' => $value['en']
        ]), $casted);

        // Массив заменяет всё и возвращается json
        $new = [
            'ru' => Factory::create('ru')->company(),
            'en' => Factory::create('en')->company(),
        ];
        $casted = $cast->set(new TestMultilingualModel(), 'name', $new, ['name' => json_encode($value)]);
        $this->assertEquals(json_encode($new), $casted);

        // Объект заменяет всё и возвращается json
        $casted = $cast->set(new TestMultilingualModel(), 'name', new Multilingual($new),
            ['name' => json_encode($value)]);
        $this->assertEquals(json_encode($new), $casted);

        // null остается null
        $casted = $cast->set(new TestMultilingualModel(), 'name', null, ['name' => null]);
        $this->assertNull($casted);

        // Arrays

        $value = [
            'ru' => [
                Factory::create('ru_RU')->company(),
                Factory::create('ru_RU')->company()
            ]
        ];

        $ru = [
            Factory::create('ru_RU')->company(),
            Factory::create('ru_RU')->company()
        ];

        // Заменяет значение и возвращается json
        $casted = $cast->set(new TestMultilingualModel(), 'name', $ru, ['name' => json_encode($value)]);
        $this->assertEquals(json_encode([
            'ru' => $ru
        ]), $casted);

    }

    public function testLocale()
    {
        $alt = [
            'ru'      => 'one',
            'ru_RU'   => 'two',
            'en_GB'   => 'four',
            'en-GB'   => 'five',
            'en_US'   => 'six',
            'en-US'   => 'seven',
            'en'      => 'eight',
            'de-DEVA' => 'nine',
            'de-DE'   => 'ten',
        ];

        $values = $alt;

        $lang = locale_canonicalize('en_GB');

        $locales = array_filter(
            array_keys($values),
            fn($locale) => locale_filter_matches($locale, $lang),
        );

        usort($locales, function ($a, $b) {
            if (strlen($a) == strlen($b)) {
                return 0;
            }
            return (strlen($a) < strlen($b)) ? -1 : 1;
        });

        $this->assertEquals(['en_GB', 'en-GB'], $locales);
    }
}
