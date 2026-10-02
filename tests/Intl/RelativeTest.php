<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Orchestra\Testbench\PHPUnit\TestCase;

class RelativeTest extends TestCase
{
    /**
     * The "now" every test measures against.
     *
     * Fixed rather than left to the clock, so the counts below are exact.
     */
    protected const NOW = '2026-10-03 15:04:05';

    protected function intl(string $locale = 'en'): IntlManager
    {
        return new IntlManager(new Translator($locale), 'UTC', IntlDateFormatter::GREGORIAN, 'EUR', '');
    }

    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(static::NOW, new DateTimeZone('UTC'));
    }

    protected function relativeTo(DateTimeImmutable $datetime, string $locale = 'en'): ?string
    {
        return $this->intl($locale)->date($datetime)->relative($this->now());
    }

    /**
     * Independent reference, straight from ICU.
     *
     * Reads the shipped translation and lets ICU pick the plural form, so a
     * test fails on our diffing rather than on CLDR changing its mind.
     */
    protected function ref(string $unit, int $count, string $locale, string $direction): ?string
    {
        return msgfmt_create($locale, $this->line($unit, $locale))?->format([$count, $direction]);
    }

    /**
     * The shipped translation for a unit, straight from lang/.
     */
    protected function line(string $unit, string $locale): string
    {
        $path = __DIR__.'/../../lang/'.$locale.'/relative.php';

        $this->assertFileExists($path, "lang/{$locale}/relative.php is not shipped");

        return (require $path)[$unit];
    }

    // ------------------------------------------------------------------ units

    public function test_it_picks_the_coarsest_unit(): void
    {
        // Every moment carries the same time of day as NOW, so the difference
        // is exactly one of that unit and nothing below it.
        foreach ([
                     'year' => '2025-10-03 15:04:05',
                     'month' => '2026-09-03 15:04:05',
                     'day' => '2026-10-02 15:04:05',
                     'hour' => '2026-10-03 14:04:05',
                     'minute' => '2026-10-03 15:03:05',
                     'second' => '2026-10-03 15:04:04',
                 ] as $unit => $moment) {
            $datetime = new DateTimeImmutable($moment, new DateTimeZone('UTC'));

            $this->assertSame(
                $this->ref($unit, 1, 'en', 'past'),
                $this->relativeTo($datetime),
                "A one-{$unit} difference must be reported in {$unit}s"
            );
        }
    }

    public function test_it_counts_the_unit_it_picked(): void
    {
        foreach ([
                     'year' => ['2022-10-03 15:04:05', 4],
                     'month' => ['2026-02-03 15:04:05', 8],
                     'day' => ['2026-09-26 15:04:05', 7],
                     'hour' => ['2026-10-03 07:04:05', 8],
                     'minute' => ['2026-10-03 15:00:05', 4],
                     'second' => ['2026-10-03 15:03:57', 8],
                 ] as $unit => [$moment, $count]) {
            $datetime = new DateTimeImmutable($moment, new DateTimeZone('UTC'));

            $this->assertSame(
                $this->ref($unit, $count, 'en', 'past'),
                $this->relativeTo($datetime),
                "A {$count}-{$unit} difference must count {$count} {$unit}s"
            );
        }
    }

    public function test_a_coarser_unit_wins_over_a_finer_one(): void
    {
        // 36 hours is "1 day" here, not "1 day and 12 hours".
        $datetime = new DateTimeImmutable('2026-10-02 03:04:05', new DateTimeZone('UTC'));

        $this->assertSame(
            $this->ref('day', 1, 'en', 'past'),
            $this->relativeTo($datetime),
            'Only the coarsest unit is reported'
        );
    }

    public function test_the_same_moment_is_zero_seconds(): void
    {
        // A difference of zero has no direction, and diff() reports it as not
        // inverted, so it reads as "from now".
        $this->assertSame(
            $this->ref('second', 0, 'en', 'future'),
            $this->relativeTo($this->now()),
            'A difference of nothing is reported as zero seconds'
        );
    }

    // -------------------------------------------------------------- direction

    public function test_it_reports_the_direction(): void
    {
        $past = new DateTimeImmutable('2026-10-01 15:04:05', new DateTimeZone('UTC'));
        $future = new DateTimeImmutable('2026-10-06 15:04:05', new DateTimeZone('UTC'));

        $this->assertSame($this->ref('day', 2, 'en', 'past'), $this->relativeTo($past));
        $this->assertSame($this->ref('day', 3, 'en', 'future'), $this->relativeTo($future));
    }

    public function test_a_past_moment_and_a_future_one_differ(): void
    {
        $past = $this->relativeTo(new DateTimeImmutable('2026-10-01', new DateTimeZone('UTC')));

        $future = $this->relativeTo(new DateTimeImmutable('2026-10-06', new DateTimeZone('UTC')));

        $this->assertNotSame($past, $future, 'The direction must reach the translation');
        $this->assertStringNotContainsString('ago', $future, 'A future moment must not read as past');
        $this->assertStringContainsString('ago', $past, 'A past moment must read as past');
    }

    // ---------------------------------------------------------------- plurals

    public function test_it_uses_the_locale_plural_forms(): void
    {
        $three = new DateTimeImmutable('2026-09-30', new DateTimeZone('UTC'));
        $five = new DateTimeImmutable('2026-09-28', new DateTimeZone('UTC'));

        // Russian needs three forms, which is the whole reason this is not a
        // Laravel "singular|plural" line.
        $this->assertSame($this->ref('day', 3, 'ru', 'past'), $this->relativeTo($three, 'ru'));
        $this->assertSame($this->ref('day', 5, 'ru', 'past'), $this->relativeTo($five, 'ru'));

        $this->assertNotSame(
            $this->relativeTo($three, 'ru'),
            $this->relativeTo($five, 'ru'),
            'Russian must distinguish the few from the many form'
        );

        // French treats zero as singular, where English does not, so a
        // "singular|plural" line would get this wrong. Asserted against ICU
        // rather than a shipped file: lang/ has no French line, and French here
        // only stands in for any locale whose rules differ from English's.
        $french = msgfmt_create('fr', '{0, plural, one {# jour} other {# jours}}');

        $this->assertSame('0 jour', $french?->format([0]), 'ICU must treat French zero as singular');
        $this->assertSame('2 jours', $french?->format([2]));

        $english = msgfmt_create('en', '{0, plural, one {# day} other {# days}}');

        $this->assertSame('0 days', $english?->format([0]), 'English zero is plural');
    }

    public function test_it_uses_the_translator_locale(): void
    {
        $datetime = new DateTimeImmutable('2026-09-28', new DateTimeZone('UTC'));

        $english = $this->relativeTo($datetime, 'en');
        $russian = $this->relativeTo($datetime, 'ru');

        $this->assertSame($this->ref('day', 5, 'en', 'past'), $english);
        $this->assertSame($this->ref('day', 5, 'ru', 'past'), $russian);

        $this->assertNotSame($english, $russian, 'The locale must reach the translation');
    }

    public function test_it_formats_the_number_per_locale(): void
    {
        $datetime = new DateTimeImmutable('2024-09-28', new DateTimeZone('UTC'));

        // "#" inside an ICU plural is number-formatted for the locale, so the
        // ru result groups its thousands and the en one does not.
        $english = $this->relativeTo($datetime, 'en');
        $russian = $this->relativeTo($datetime, 'ru');

        $this->assertStringContainsString('2 years', $english);
        $this->assertStringContainsString('2 года', $russian);
    }

    // ----------------------------------------------------------------- timing

    public function test_a_day_is_a_calendar_day_across_a_dst_change(): void
    {
        // 2026-03-08 12:00 to 2026-03-09 12:00 in New York is 23 real hours,
        // but it is "yesterday" all the same.
        // The spring-forward day: the clock jumps 02:00 to 03:00, so this pair is
        // 23 real hours apart.
        $formatter = $this->intl('en')
            ->date(new DateTimeImmutable('2026-03-08 12:00:00', new DateTimeZone('America/New_York')));

        $this->assertSame(
            $this->ref('day', 1, 'en', 'past'),
            $formatter->relative(new DateTimeImmutable('2026-03-09 12:00:00', new DateTimeZone('America/New_York'))),
            'A calendar day stays a day across a DST change'
        );
    }

    public function test_it_defaults_to_now(): void
    {
        $past = new DateTimeImmutable('-3 days');

        $formatter = $this->intl('en')->date($past);

        $this->assertMatchesRegularExpression(
            '/^3 days ago$/',
            (string) $formatter->relative(),
            'Without a reference the formatter measures against the current time'
        );
    }

    // ----------------------------------------------------------------- misses

    public function test_an_empty_translation_yields_null(): void
    {
        // A blank line is not a phrase, so it must not be mistaken for one.
        $translator = (new Translator('en'))->add('en', 'intl::relative.second', '');

        $formatter = (new IntlManager($translator, 'UTC', IntlDateFormatter::GREGORIAN, 'EUR', ''))
            ->date($this->now());

        $this->assertNull($formatter->relative($this->now()), 'An empty translation is not a phrase');
    }

    public function test_a_malformed_translation_yields_null(): void
    {
        // A select without its mandatory "other" branch is rejected by ICU, and
        // a pattern it cannot parse yields no formatter at all.
        $translator = (new Translator('en'))
            ->add('en', 'intl::relative.day', '{1, select, past { ago} future { from now}}');

        $formatter = (new IntlManager($translator, 'UTC', IntlDateFormatter::GREGORIAN, 'EUR', ''))
            ->date(new DateTimeImmutable('2026-10-01', new DateTimeZone('UTC')));

        $this->assertNull($formatter->relative($this->now()), 'A pattern ICU cannot parse must not throw');
    }

    public function test_the_translations_ship_and_parse(): void
    {
        foreach (['en', 'ru'] as $locale) {
            $lines = require __DIR__.'/../../lang/'.$locale.'/relative.php';

            foreach (['second', 'minute', 'hour', 'day', 'month', 'year'] as $unit) {
                $this->assertArrayHasKey($unit, $lines, "lang/{$locale}/relative.php is missing {$unit}");

                $this->assertNotFalse(
                    msgfmt_create($locale, $lines[$unit]),
                    "lang/{$locale}/relative.php: {$unit} is not a valid ICU message"
                );
            }
        }
    }
}