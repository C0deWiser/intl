<?php

namespace Codewiser\Intl\Tests\Casts;

use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Casts\MultilingualArray;
use Illuminate\Database\Eloquent\Model;

/**
 * @property null|Multilingual $name
 * @property null|Multilingual[] $names
 */
class TestMultilingualModel extends Model
{
    protected $casts = [
        'name' => Multilingual::class,
        'names' => MultilingualArray::class,
    ];
}