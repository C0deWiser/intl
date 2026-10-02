# Intl Helper

## Configuration

`IntlManager` service is a helper to access main `intl` php functions.

You may configure `IntlManager` defaults in your application's 
`AppServiceProvider` class:

```php
use Codewiser\Intl\IntlManager;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->extend(IntlManager::class, fn (IntlManager $intl) => $intl
        ->useCalendar(\IntlDateFormatter::GREGORIAN)
        ->useCurrency('USD')
        // see https://www.php.net/manual/en/transliterator.listids.php
        ->useTransliterator('Any-Latin')
    );
}
```

`intl()` is a synonym of `app(IntlManager::class)`.

## Datetime

Format date and time respecting the app's current locale.

```php
intl()->date($date)->format(
    date: \IntlDateFormatter::FULL, 
    time: \IntlDateFormatter::SHORT
);
// Saturday, April 12, 1952 at 3:30 PM

intl()->date($date)->format(date: \IntlDateFormatter::SHORT);
// 4/12/52

intl()->date($date)->format(time: \IntlDateFormatter::FULL);
// 3:30:42 PM Coordinated Universal Time
```

### Skeletons

The styles above only offer the fixed `LONG`/`MEDIUM`/`SHORT` sets, so they
cannot express "Sat, Oct 3" or "Q4 2026". A skeleton names the fields to show and
lets ICU choose the pattern the locale prefers:

```php
intl()->date($date)->skeleton('yMMMEd');
// Sat, Oct 3, 2026

intl()->date($date)->skeleton('yQQQ');
// Q4 2026

intl()->date($date)->skeleton('Hm');
// 15:04
```

### Relative time

```php
intl()->date(now()->subDays(3))->relative();
// 3 days ago

intl()->date(now()->addHours(2))->relative();
// 2 hours from now

intl()->date($then)->relative($now);
// Measured against another moment rather than the current time
```

## Date period

Format date period respecting the app's current locale.

Date period may be passed either as `\DatePeriod` object,
or as two `\DateTimeInterface` objects (array or variadic).

```php
$period = now()->toPeriod(now()->addHour());

intl()
    ->period($period)
    ->format(\IntlDateFormatter::LONG, \IntlDateFormatter::LONG);
# April 12, 1952 from 3:30:42 PM UTC to 4:30:42 PM UTC
```

### Translations

The period sentences and the relative-time patterns come from the translations
shipped with the package. Publish them to override:

```shell
php artisan vendor:publish --tag=intl
```

## Numbers

```php
// Shortcuts:

intl()->number(1234.56)->currency('EUR'); // €1,234.56
intl()->number(1234.56)->decimal();       // 1,234.56
intl()->number(1234.56)->percent();       // 123,456%
intl()->number(1234.56)->spellout();      // one thousand two hundred thirty-four point five six
intl()->number(1234.56)->ordinal();       // 1,235th
intl()->number(1234.56)->duration();      // 20:35
intl()->number(1234.56)->scientific();    // 1.23456E3

// Base:
intl()->number(1234.56)->format(\NumberFormatter::DECIMAL);
```

## Names

A locale and a currency both have names, which is what a language switcher and a
currency selector need:

```php
intl()->locale('zh_Hant_TW')->display();
// Chinese (Traditional, Taiwan)

intl()->locale('pt_BR')->region();
// Brazil

intl()->locale('de')->display('ru');
// немецкий (locale override)

intl()->currency()->name();
// Euro — the app's default currency

intl()->currency('RUB')->name('fr');
// rouble russe (locale override)

intl()->currency('RUB')->symbol('ru');
// ₽ in a Russian locale

intl()->currency('RUB')->symbol('en');
// RUB in an English one
```

## Transliteration

Useful for turning a name into something a URL or a search box can carry:

```php
intl()->text()->convert('Шёлковый пух', 'Russian-Latin/BGN');
// Shëlkovyy pukh

intl()->text()->convert('北京', 'Han-Latin');
// běi jīng
```

Normalization is the same machinery and matters because Unicode writes the same
text in more than one way:

```php
intl()->text()->normalize("e\u{0301}");     // é as one code point
intl()->text()->isNormalized($text);        // already in NFC?
```

# Multilingual Model Attributes

Such an attribute is stored in a database as a JSON object.

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Casts\AsMultilingual;
use Codewiser\Intl\Traits\HasMultilingual;

/**
 * @property null|string $name
 */
class User extends Model
{
    use HasMultilingual;

    protected function casts(): array
    {
        return [
            'name' => AsMultilingual::class
        ];
    }
}
```

## Storing

A new value will be implicitly stored in the current locale.

However, you may explicitly define a locale.

```php
// Set value in default locale
$user->name = 'Michael';

// Set value with explicit locale
$user->withLocale('en', fn() => $user->name = 'Michael');
$user->withLocale('es', fn() => $user->name = 'Miguel');

// Set values as array (or Arrayable) to replace all values
$user->name = [
    'en' => 'Michael',
    'es' => 'Miguel',
];
```

## Reading

A plain value will be implicitly retrieved in the current locale. It is enough
to properly apply the `Accept-Language` header from a User-Agent — and the user
will get content in a preferred language.

If the value for the requested locale is empty, the fallback locale will be 
tried, or the first non-empty value will be returned.

You may explicitly define a locale.

```php
// Get value in current locale
$name = $user->name;

// Get value in given locale
$nameInEn = $user->withLocale('en', fn() => $user->name);
$nameInEs = $user->withLocale('es', fn() => $user->name);
```

## Hydrating

Reading gives a plain value, so ask for the `Multilingual` object when you need
another locale, or every locale at once.

```php
// One attribute
$user->multilingual('name');
// Multilingual<string>

// Several attributes at once
$user->multilingual(fn() => [$user->name, $user->description]);

// From outside the model
Multilingual::of(fn() => $user->name);
```

A hydrated attribute gives back the whole object.

```php
$user->multilingual('name')->get();
// 'Michael'

$user->multilingual('name')['es'];
// 'Miguel'

$user->multilingual('name')->missing(['en', 'es', 'it']);
// ['it']

$user->multilingual('name')->toArray();
// ['en' => 'Michael', 'es' => 'Miguel']

$user->multilingual('name')->isEmpty();
// false
```

Hydrating a non-multilingual attribute throws an `InvalidArgumentException`.

## Serializing

Serialization is never hydrated implicitly — `$model->toArray()` holds plain
values.

```php
$user->toArray();
// ['id' => 1, 'name' => 'Michael']

$user->multilingual(fn() => $user->toArray());
// ['id' => 1, 'name' => ['en' => 'Michael', 'es' => 'Miguel']]
```

Ask for it explicitly wherever every locale belongs in the output.

## Language tags

You may access a hydrated `Multilingual` using language tags as well. 
Package uses 
[locale_filter_matches](https://www.php.net/manual/ru/locale.filtermatches.php)
to find the best variant:

```php
$user->name = [
    'en'    => 'Michael',
    'en_US' => 'Mike',
];

$user->multilingual('name')['en'];
// Michael
$user->multilingual('name')['en_GB'];
// Michael
$user->multilingual('name')['en-US'];
// Mike
$user->multilingual('name')['it'];
// null
```

## Strict mode

If you try to get a value that is missing, the `Multilingual` will try to
return any convenient value — using the fallback locale or just the first one:

```php
$user->name = [
    'en' => 'Michael',
    'es' => 'Miguel'
];

$user->withLocale('it', fn() => $user->name);
// Michael (using fallback locale)
```

You may enable `strict` mode, and then `Multilingual` will return the exact
value:

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Casts\AsMultilingual;
use Codewiser\Intl\Traits\HasMultilingual;

/**
 * @property null|float $score
 */
class User extends Model
{
    use HasMultilingual;

    protected function casts(): array
    {
        return [
            'score' => AsMultilingual::strict()
        ];
    }
}

$user->score = [
    'en' => 1.0,
    'es' => 1.1
];

$user->withLocale('en', fn() => $user->score);
// 1

$user->withLocale('it', fn() => $user->score);
// null (exact value, no fallback)
```

## Multilingual arrays

As we may keep multilingual scalars, we may keep multilingual arrays as well:

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Casts\AsMultilingual;
use Codewiser\Intl\Traits\HasMultilingual;

/**
 * @property null|array $keywords
 */
class User extends Model
{
    use HasMultilingual;

    protected function casts(): array
    {
        return [
            'keywords' => AsMultilingual::class
        ];
    }
}

$user->keywords = [
    'en' => ['one', 'two'],
    'es' => ['uno', 'dos'],
];

$user->withLocale('en', fn() => $user->keywords);
// ['one', 'two']

$user->withLocale('es', fn() => $user->keywords);
// ['uno', 'dos']

$user->multilingual('keywords')['es'][0];
// 'uno'

$user->multilingual('keywords')->toArray();
// ['en' => ['one', 'two'], 'es' => ['uno', 'dos']]
```

## Map into object

Multiligual array could be 
[mapped into](https://laravel.com/framework/docs/13.x/collections#method-mapinto)
an object:

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Casts\AsMultilingual;
use Codewiser\Intl\Traits\HasMultilingual;

/**
 * @property null|Username $name
 */
class User extends Model
{
    use HasMultilingual;

    protected function casts(): array
    {
        return [
            'name' => AsMultilingual::using(Username::class)
        ];
    }
}

$user->name = [
    'en' => ['first_name' => 'John', 'last_name' => 'Smith'],
    'es' => ['first_name' => 'Juan', 'last_name' => 'Herrera'],
];

$user->withLocale('en', fn() => $user->name);
// Username(['first_name' => 'John', 'last_name' => 'Smith'])

$user->withLocale('es', fn() => $user->keywords);
// Username(['first_name' => 'Juan', 'last_name' => 'Herrera'])
```

### Changing a mapped object

The very same object is handed out on every read, so changing it changes the
attribute. The change is written back right before the attributes are read,
dirtied, serialized or saved, so it behaves like any other change of the model.

```php
$user->name->first_name = 'Johnny';

$user->name->first_name;
// Johnny, the change was not thrown away by the next read

$user->isDirty('name');
// true

$user->multilingual('name')->toArray();
// ['en' => ['first_name' => 'Johnny', ...], 'es' => ['first_name' => 'Juan', ...]]

$user->save();
// the change is in the database
```

Assigning the attribute replaces the object, so a change made to the old one is
gone:

```php
$hydrated = $user->name;

$user->name = ['en' => ['first_name' => 'Jane', 'last_name' => 'Doe']];

$hydrated->first_name = 'Nobody';
// does not touch the attribute anymore
```

The write back hangs on `getAttributes()`, which is what `getDirty()`,
`toArray()`, `save()` and friends funnel through. A model defining its own
`getAttributes()` overrides the one from the trait, and then it has to call
`flushMultilingualAttributes()` itself.

## Array of multilingual

It is possible to keep an array where each element is a `Multilingual`:

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\Multilingual;
use Codewiser\Intl\Traits\HasMultilingual;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Casts\AsCollection;

/**
 * @property null|Collection<int, Multilingual<string>> $keywords
 */
class User extends Model
{
    use HasMultilingual;

    protected function casts(): array
    {
        return [
            'keywords' => AsCollection::of(Multilingual::class)
        ];
    }
}

$user->keywords = [
    ['en' => 'one', 'es' => 'uno'],
    ['en' => 'two', 'es' => 'dos'],
];

$user->keywords->first()->get();
// one

$user->withLocale('es', fn() => $user->keywords->first()->get());
// uno
```

`AsCollection::of()` maps into the class directly, so it never routes through
the cast. Elements are always `Multilingual` objects here, and hydrating has no
effect on them.

## Multilingual collection

Casting an attribute into a collection of `Multilingual` instead routes through
the cast, so it may be mapped into objects as well:

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\AsMultilingual;
use Codewiser\Intl\Traits\HasMultilingual;
use Illuminate\Support\Collection;

/**
 * @property null|Collection<int, Username> $names
 */
class User extends Model
{
    use HasMultilingual;

    protected function casts(): array
    {
        return [
            'names' => AsMultilingual::collect(Username::class)
        ]
    }
}

$user->names = [
    ['en' => ['first_name' => 'John', 'last_name' => 'Smith']],
    ['en' => ['first_name' => 'Gregory', 'last_name' => 'Johnson']],
];

$user->names->first();
// Username(['first_name' => 'John', 'last_name' => 'Smith'])

$user->multilingual('names')->first();
// Multilingual<Username> holding the very same object

$user->names->first()->first_name = 'Johnny';
// changes the attribute, just like a mapped attribute does
```
