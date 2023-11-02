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
use \Illuminate\Database\Eloquent\Model;
use \Codewiser\Intl\Casts\AsMultiLingual;
use \Codewiser\Intl\Traits\HasLocalizations;

/**
 * @property string $name 
 */
class User extends Model
{
    use HasLocalizations;
    
    protected $casts = [
        'name' => AsMultiLingual::class
    ];
}
```

### Storing

A new value will be implicitly stored in a current locale. 

However, you may explicitly define locale.

```php
$user->withLocale('en', fn(User $user) => $user->name = 'Michael');
$user->withLocale('es', fn(User $user) => $user->name = 'Miguel');
```

### Reading
A value will be implicitly retrieved in a current locale. It would be enough 
to properly apply `Accept-Language` header from a User-Agent — and user will 
get content in a preferred language.

If value for requested locale is empty, the first not empty value will be 
returned.

You may explicitly define locale.

```php
$nameInEn = $user->withLocale('en', fn(User $user) => $user->name);
$nameInEs = $user->withLocale('es', fn(User $user) => $user->name);
```
