# Intl helper

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

$interval = now()->diffAsCarbonInterval(now()->addHour());
intl($interval)->long();
# January 12, 1952 from 3:30:30pm to 4:30:30pm
```
