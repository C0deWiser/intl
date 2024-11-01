# Intl helper

## Date and time localization

Format date and time in an app current locale.

```php
intl(now())->short();
# 12/13/52 3:30pm

intl(now())->shortDate();
# 12/13/52

intl(now())->full();
# Tuesday, April 12, 1952 AD 3:30:42pm PST

intl(now())->fullTime();
# 3:30:42pm PST

$interval = now()->toPeriod(now()->addHour());
intl($interval)->long();
# January 12, 1952 from 3:30:30pm to 4:30:30pm
```

## Multilingual model attributes

Such attribute stores in a database as a json object.

```php
use Illuminate\Database\Eloquent\Model;
use Codewiser\Intl\Casts\Multilingual;
use Illuminate\Support\Traits\Localizable;

/**
 * @property Multilingual $name 
 */
class User extends Model
{
    use Localizable;
    
    protected $casts = [
        'name' => Multilingual::class
    ];
}
```

### Storing

A new value will be implicitly stored in a current locale. 

However, you may explicitly define locale.

```php
// Set value in default locale
$user->name = 'Michael';

// Set value with explicit locale
$user->withLocale('en', fn() => $user->name = 'Michael');
$user->withLocale('es', fn() => $user->name = 'Miguel');

// Set values as array
$user->name = [
    'en' => 'Michael',
    'es' => 'Miguel',
];
```

### Reading

A value will be implicitly retrieved in a current locale. It would be enough 
to properly apply `Accept-Language` header from a User-Agent — and user will 
get content in a preferred language.

If value for requested locale is empty, the first not empty value will be 
returned.

You may explicitly define locale.

```php
// Get value in default locale
$nameInCurrentLocale = (string) $user->name;
$nameInCurrentLocale = $user->name->toString();

// Get value in given locale
$nameInEn = $user->withLocale('en', fn() => $user->name);
$nameInEs = $user->withLocale('es', fn() => $user->name);

// Get all values
$user->name->toArray();
```