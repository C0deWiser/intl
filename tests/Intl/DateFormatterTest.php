<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\Intl\DateFormatter;
use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Error;
use IntlDateFormatter;
use InvalidArgumentException;
use Orchestra\Testbench\PHPUnit\TestCase;

class DateFormatterTest extends TestCase
{
    /**
     * Independent reference formatting, straight from ICU.
     *
     * Asserting against this instead of hard-coded strings keeps the tests
     * stable across ICU/CLDR versions while still proving the formatter gets
     * the right locale, timezone, calendar and date/time styles through.
     */
    protected function ref(
        DateTimeInterface $datetime,
        int $date = IntlDateFormatter::NONE,
        int $time = IntlDateFormatter::NONE,
        string $locale = 'en',
        string $timezone = 'UTC',
        int $calendar = IntlDateFormatter::GREGORIAN,
    ): ?string {
        return datefmt_create($locale, $date, $time, $timezone, $calendar)?->format($datetime);
    }

    protected function intl(
        string $locale = 'en',
        string $timezone = 'UTC',
        int $calendar = IntlDateFormatter::GREGORIAN,
        string $currency = 'EUR',
    ): IntlManager {
        return new IntlManager(new Translator($locale), $timezone, $currency, $calendar);
    }

    protected function at(string $time = '2026-10-02 10:39:17', string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone($timezone));
    }

    // ------------------------------------------------------------- datetime

    public function test_datetime()
    {
        $intl = $this->intl();

        foreach ([
                     'RELATIVE_FULL'   => IntlDateFormatter::RELATIVE_FULL,
                     'RELATIVE_LONG'   => IntlDateFormatter::RELATIVE_LONG,
                     'RELATIVE_MEDIUM' => IntlDateFormatter::RELATIVE_MEDIUM,
                     'RELATIVE_SHORT'  => IntlDateFormatter::RELATIVE_SHORT,
                     'FULL'            => IntlDateFormatter::FULL,
                     'LONG'            => IntlDateFormatter::LONG,
                     'MEDIUM'          => IntlDateFormatter::MEDIUM,
                     'SHORT'           => IntlDateFormatter::SHORT,
                     'NONE'            => IntlDateFormatter::NONE,
                 ] as $label => $style) {
            $this->assertSame(
                $this->ref(now()->subDay(), $style, IntlDateFormatter::NONE),
                $intl->date(now()->subDay())->format($style, IntlDateFormatter::NONE),
                "Date style {$label} does not match the reference formatter"
            );

            $this->assertSame(
                $this->ref(now()->subDay(), IntlDateFormatter::NONE, $style),
                $intl->date(now()->subDay())->format(IntlDateFormatter::NONE, $style),
                "Time style {$label} does not match the reference formatter"
            );
        }
    }

    public function test_datetime_defaults_to_both_styles_none(): void
    {
        $intl = $this->intl();
        $datetime = $this->at();

        $this->assertSame(
            $this->ref($datetime, IntlDateFormatter::NONE, IntlDateFormatter::NONE),
            $intl->date($datetime)->format()
        );
    }

    public function test_datetime_uses_translator_locale(): void
    {
        $datetime = $this->at();

        $english = $this->intl('en')->date($datetime)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        $russian = $this->intl('ru')->date($datetime)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        $french = $this->intl('fr')->date($datetime)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);

        $this->assertSame($this->ref($datetime, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, 'en'), $english);
        $this->assertSame($this->ref($datetime, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, 'ru'), $russian);

        $this->assertNotSame($english, $russian, 'The locale must reach the formatter');
        $this->assertNotSame($english, $french, 'The locale must reach the formatter');
    }

    public function test_datetime_uses_calendar_timezone(): void
    {
        $datetime = $this->at('2026-10-02 23:39:17');

        $utc = $this->intl('en', 'UTC')->date($datetime)->format(IntlDateFormatter::NONE, IntlDateFormatter::SHORT);
        $tokyo = $this->intl('en', 'Asia/Tokyo')->date($datetime)->format(IntlDateFormatter::NONE,
            IntlDateFormatter::SHORT);
        $newYork = $this->intl('en', 'America/New_York')->date($datetime)->format(IntlDateFormatter::NONE,
            IntlDateFormatter::SHORT);

        $this->assertSame($this->ref($datetime, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, 'en', 'UTC'), $utc);
        $this->assertSame($this->ref($datetime, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, 'en', 'Asia/Tokyo'),
            $tokyo);
        $this->assertSame($this->ref($datetime, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, 'en',
            'America/New_York'), $newYork);

        $this->assertNotSame($utc, $tokyo, 'The timezone must reach the formatter');
        $this->assertNotSame($utc, $newYork, 'The timezone must reach the formatter');
    }

    public function test_manager_use_timezone_and_calendar_are_fluent()
    {
        $intl = $this->intl();

        $this->assertSame($intl, $intl->useTimezone('Asia/Tokyo'));
        $this->assertSame($intl, $intl->useCalendar(IntlDateFormatter::TRADITIONAL));
    }

    public function test_use_timezone_affects_later_calls(): void
    {
        $datetime = $this->at('2026-10-02 23:39:17');

        $intl = $this->intl()->useTimezone('Asia/Tokyo');

        foreach ([IntlDateFormatter::SHORT, IntlDateFormatter::MEDIUM] as $style) {
            $this->assertSame(
                $this->ref($datetime, IntlDateFormatter::NONE, $style, 'en', 'Asia/Tokyo'),
                $intl->date($datetime)->format(IntlDateFormatter::NONE, $style),
                "The timezone must reach every later call, style {$style}"
            );
        }
    }

    public function test_invalid_timezone_yields_null(): void
    {
        $formatted = $this->intl('en', 'Not/AZone')
            ->date($this->at())
            ->format(IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);

        $this->assertNull($formatted, 'ICU refuses to construct a formatter for an unknown timezone');
    }

    public function test_invalid_calendar_yields_null(): void
    {
        $formatted = $this->intl('en', 'UTC', 9999)
            ->date($this->at())
            ->format(IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);

        $this->assertNull($formatted, 'ICU refuses to construct a formatter for an unknown calendar');
    }

    /**
     * An unknown locale yields an unconstructed formatter rather than null, and
     * format() on it raises an Error. By design: a locale nobody can format for
     * is a bug to hear about, not a value to swallow. Only a broken timezone or
     * calendar degrades to null, since those may well be a display preference.
     */
    public function test_unknown_locale_throws(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Found unconstructed IntlDateFormatter');

        $this->intl('xx_YY')
            ->date($this->at())
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
    }

    public function test_period_with_unknown_locale_throws(): void
    {
        $period = new DatePeriod(
            $this->at('2026-10-02 10:00:00'),
            new DateInterval('PT1H'),
            $this->at('2026-10-02 14:00:00')
        );

        // Without a registered line the translator returns the key verbatim,
        // which would hide the formatting. Register one so the replacements
        // actually run against the failed formatter.
        $translator = (new Translator('xx_YY'))
            ->add('xx_YY', 'intl::calendar.time-period', ':date from :start to :end');

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Found unconstructed IntlDateFormatter');

        (new IntlManager($translator))
            ->period($period)
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
    }

    // --------------------------------------------------------------- period

    public function test_period()
    {
        $day = new DatePeriod(
            $this->at('2026-10-02 09:39:17'),
            new DateInterval('PT1H'),
            $this->at('2026-10-02 10:39:17')
        );

        $month = new DatePeriod(
            $this->at('2026-09-02 09:39:17'),
            new DateInterval('P1M'),
            $this->at('2026-10-02 10:39:17')
        );

        $intl = $this->intl();

        $this->assertSame(
            $this->expectedWithinOneDay($day, IntlDateFormatter::FULL, IntlDateFormatter::SHORT),
            $intl->period($day)->format(IntlDateFormatter::FULL, IntlDateFormatter::SHORT)
        );

        $this->assertSame(
            $this->expectedWithinOneDay($day, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT),
            $intl->period($day)->format(IntlDateFormatter::SHORT, IntlDateFormatter::SHORT)
        );

        $this->assertSame(
            $this->expectedRange($month, IntlDateFormatter::FULL, IntlDateFormatter::SHORT),
            $intl->period($month)->format(IntlDateFormatter::FULL, IntlDateFormatter::SHORT)
        );

        $this->assertSame(
            $this->expectedRange($month, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT),
            $intl->period($month)->format(IntlDateFormatter::SHORT, IntlDateFormatter::SHORT)
        );
    }

    public function test_period_can_be_given_as_two_datetimes(): void
    {
        $start = $this->at('2026-10-02 10:00:00');
        $end = $this->at('2026-10-02 14:00:00');
        $interval = $start->diff($end);

        // Compared against the DatePeriod branch rather than literals: the point
        // is that the two shorthands build the same period, not what this ICU
        // version says a range looks like.
        $expected = $this->intl()
            ->period(new DatePeriod($start, new DateInterval('PT4H'), $end))
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        $this->assertSame(
            $expected,
            $this->intl()->period($start, $end)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT),
            'A start and an end are a period'
        );

        $this->assertSame(
            $expected,
            $this->intl()->period([$start, $end])->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT),
            'And so is a pair of them'
        );

        $this->assertSame(
            $expected,
            $this->intl()->period($start, $interval)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT),
            'A start and an interval are a period'
        );
    }

    public function test_period_rejects_input_it_cannot_read(): void
    {
        $start = $this->at('2026-10-02 10:00:00');

        $cases = [
            'a lone datetime with no end' => [$start],
            'a one-element array' => [[$start]],
            'an array of strings' => [['2026-10-02', '2026-10-03']],
            'an empty array' => [[]],
        ];

        foreach ($cases as $why => $arguments) {
            try {
                $this->intl()->period(...$arguments);

                $this->fail("period() must reject {$why}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    'Period should be either',
                    $e->getMessage(),
                    "Rejecting {$why} must say what a period is"
                );
            }
        }
    }

    public function test_period_within_one_day_repeats_the_date_once(): void
    {
        $period = new DatePeriod(
            $this->at('2026-10-02 10:00:00'),
            new DateInterval('PT1H'),
            $this->at('2026-10-02 14:00:00')
        );

        $formatted = $this->intl()->period($period)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        // "Oct 2, 2026 from 10:00 AM to 2:00 PM" - the date is stated once, up front.
        $this->assertMatchesRegularExpression('/ from .+ to /', $formatted);
        $this->assertSame(1,
            preg_match_all('/\b'.preg_quote($this->ref($this->at('2026-10-02 10:00:00'), IntlDateFormatter::MEDIUM,
                    IntlDateFormatter::NONE), '/').'\b/u', $formatted));
    }

    public function test_period_spanning_days_states_both_dates(): void
    {
        $start = $this->at('2026-10-02 22:00:00');
        $end = $this->at('2026-10-03 02:00:00');

        $period = new DatePeriod($start, new DateInterval('PT4H'), $end);

        $formatted = $this->intl()->period($period)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        // "from Oct 2, 2026, 10:00 PM to Oct 3, 2026, 2:00 AM" - both dates carry a time.
        $this->assertMatchesRegularExpression('/^from .+ to .+$/', $formatted);
        $this->assertStringContainsString($this->ref($start, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE),
            $formatted);
        $this->assertStringContainsString($this->ref($end, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE),
            $formatted);
    }

    /**
     * The within-one-day branch must be decided in the calendar timezone, not
     * in each DateTime's own zone, because that is how the range is rendered.
     */
    public function test_period_branch_follows_the_calendar_timezone(): void
    {
        // Spans midnight in UTC, but stays inside one day in New York.
        $period = new DatePeriod(
            $this->at('2026-10-02 22:00:00'),
            new DateInterval('PT4H'),
            $this->at('2026-10-03 02:00:00')
        );

        $newYork = $this->intl('en', 'America/New_York')
            ->period($period)
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        $this->assertMatchesRegularExpression(
            '/ from .+ to /',
            $newYork,
            'New York renders a single day, so it must take the within-one-day branch'
        );

        $this->assertSame(
            $this->expectedWithinOneDay($period, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT,
                'America/New_York'),
            $newYork
        );

        // The same range genuinely spans two days in UTC.
        $utc = $this->intl('en', 'UTC')
            ->period($period)
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        $this->assertSame(
            $this->expectedRange($period, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC'),
            $utc
        );
    }

    public function test_period_replaces_every_placeholder(): void
    {
        $period = new DatePeriod(
            $this->at('2026-10-02 10:00:00'),
            new DateInterval('PT1H'),
            $this->at('2026-10-05 14:00:00')
        );

        foreach ([IntlDateFormatter::MEDIUM, IntlDateFormatter::FULL] as $style) {
            $formatted = $this->intl()->period($period)->format($style, IntlDateFormatter::SHORT);

            foreach ([':date', ':start', ':end'] as $placeholder) {
                $this->assertStringNotContainsString(
                    $placeholder,
                    $formatted,
                    "Placeholder {$placeholder} leaked into the formatted period"
                );
            }
        }
    }

    public function test_period_with_date_none_takes_the_range_branch(): void
    {
        $start = $this->at('2026-10-02 10:00:00');
        $end = $this->at('2026-10-02 14:00:00');

        $period = new DatePeriod($start, new DateInterval('PT1H'), $end);

        $formatted = $this->intl()->period($period)->format(IntlDateFormatter::NONE, IntlDateFormatter::SHORT);

        $this->assertSame(
            sprintf(
                'from %s to %s',
                $this->ref($start, IntlDateFormatter::NONE, IntlDateFormatter::SHORT),
                $this->ref($end, IntlDateFormatter::NONE, IntlDateFormatter::SHORT)
            ),
            $formatted,
            'A NONE date style must skip the within-one-day branch'
        );
    }

    public function test_period_uses_translator_locale(): void
    {
        $period = new DatePeriod(
            $this->at('2026-10-02 10:00:00'),
            new DateInterval('PT1H'),
            $this->at('2026-10-05 14:00:00')
        );

        $russian = $this->intl('ru')->period($period)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        // Translator ships a distinct range sentence for ru.
        $this->assertMatchesRegularExpression('/^с .+ по .+$/u', $russian);
    }

    public function test_period_uses_calendar_timezone(): void
    {
        $period = new DatePeriod(
            $this->at('2026-10-02 10:00:00'),
            new DateInterval('PT1H'),
            $this->at('2026-10-05 14:00:00')
        );

        $this->assertSame(
            $this->expectedRange($period, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'Asia/Tokyo'),
            $this->intl('en', 'Asia/Tokyo')->period($period)->format(IntlDateFormatter::MEDIUM,
                IntlDateFormatter::SHORT)
        );
    }

    // ------------------------------------------------------------ macroable

    public function test_formatter_is_macroable(): void
    {
        $formatter = $this->intl()->date($this->at());

        DateFormatter::macro('shout', fn() => 'SHOUT');

        try {
            $this->assertTrue(DateFormatter::hasMacro('shout'));
            $this->assertSame('SHOUT', $formatter->shout());
        } finally {
            DateFormatter::flushMacros();
        }

        $this->assertFalse(DateFormatter::hasMacro('shout'));
    }

    // -------------------------------------------------------------- helpers

    protected function expectedWithinOneDay(DatePeriod $period, int $date, int $time, string $timezone = 'UTC'): string
    {
        $start = $period->getStartDate();

        return sprintf(
            '%s from %s to %s',
            $this->ref($start, $date, IntlDateFormatter::NONE, 'en', $timezone),
            $this->ref($start, IntlDateFormatter::NONE, $time, 'en', $timezone),
            $this->ref($period->getEndDate(), IntlDateFormatter::NONE, $time, 'en', $timezone)
        );
    }

    protected function expectedRange(DatePeriod $period, int $date, int $time, string $timezone = 'UTC'): string
    {
        return sprintf(
            'from %s to %s',
            $this->ref($period->getStartDate(), $date, $time, 'en', $timezone),
            $this->ref($period->getEndDate(), $date, $time, 'en', $timezone)
        );
    }
}