<?php

namespace Codewiser\Intl\Tests\Casts;

use Faker\Factory;
use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Casts\MultilingualArray;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

class MultilingualArrayTest extends TestCase
{
    public function testCastGet()
    {
        Multilingual::useLocale('ru');
        $cast = MultilingualArray::castUsing([]);

        $value = [];
        $value[] = [
            'ru' => Factory::create('ru')->company(),
            'en' => Factory::create('en')->company(),
        ];
        $value[] = [
            'ru' => Factory::create('ru')->company(),
            'en' => Factory::create('en')->company(),
        ];

        $casted = $cast->get(
            new TestMultilingualModel(),
            'names',
            json_encode($value),
            ['names' => json_encode($value)]
        );
        // Cast возвращается как массив Multilingual
        $this->assertIsArray($casted);
        $this->assertEquals(new Multilingual($value[0]), $casted[0]);
        $this->assertEquals(new Multilingual($value[1]), $casted[1]);

        // null остается null
        $casted = $cast->get(new TestMultilingualModel(), 'names', null, ['names' => null]);
        $this->assertNull($casted);
    }

    public function testCastSet()
    {
        Multilingual::useLocale('ru');
        $cast = MultilingualArray::castUsing([]);

        $value = [];
        $value[] = [
            'ru' => Factory::create('ru')->company(),
            'en' => Factory::create('en')->company(),
        ];
        $value[] = [
            'ru' => Factory::create('ru')->company(),
            'en' => Factory::create('en')->company(),
        ];

        $casted = $cast->set(new TestMultilingualModel(), 'names', $value, ['names' => json_encode($value)]);
        // Переданный массив заменяет всё
        $this->assertEquals(json_encode($value), $casted);

        $casted = $cast->set(new TestMultilingualModel(), 'names', collect($value), ['names' => json_encode($value)]);
        // Переданная коллекция заменяет всё
        $this->assertEquals(json_encode($value), $casted);

        $new = [
            'ru' => Factory::create('ru')->company(),
            'en' => Factory::create('en')->company(),
        ];
        $casted = $cast->set(new TestMultilingualModel(), 'names', $new, ['names' => json_encode($value)]);
        // Переданный элемент заменяет всё
        $this->assertEquals(json_encode($new), $casted);

        // null остается null
        $casted = $cast->set(new TestMultilingualModel(), 'names', null, ['names' => null]);
        $this->assertNull($casted);
    }

    public function test()
    {
        Multilingual::useLocale('ru');

        $m = new Multilingual([
            'ru' => Factory::create('ru_RU')->company(),
            'en' => Factory::create('en_EN')->company(),
        ]);

        $array = [
            'name'    => str('test'),
            'company' => $m
        ];

        $json = json_decode((new JsonResponse($array))->content(), true);

        $this->assertEquals($json['company'], $m['ru']);
    }
}
