<?php

namespace Codewiser\Intl\Tests\Intl;

use Codewiser\Intl\IntlManager;
use Codewiser\Intl\Tests\Translator;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Orchestra\Testbench\PHPUnit\TestCase;

class SkeletonTest extends TestCase
{
    /**
     * Independent reference formatting, straight from ICU.
     *
     * Goes through the same two ICU calls skeleton() uses, but assembled here,
     * so a test fails on our plumbing rather than on CLDR data changing.
     */
    protected function ref(
        DateTimeInterface $datetime,
        string $skeleton,
        string $locale = 'en',
        string $timezone = 'UTC',
        int $calendar = IntlDateFormatter::GREGORIAN,
    ): ?string {
        $pattern = (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);

        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            $timezone,
            $calendar,
            $pattern
        );

        return $formatter->format($datetime);
    }

    protected function intl(
        string $locale = 'en',
        string $timezone = 'UTC',
        int $calendar = IntlDateFormatter::GREGORIAN,
    ): IntlManager {
        return new IntlManager(new Translator($locale), $timezone, $calendar, 'EUR', '');
    }

    protected function at(string $time = '2026-10-03 15:04:05', string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone($timezone));
    }

    public function test_skeleton(): void
    {
        $intl = $this->intl();
        $datetime = $this->at();

        foreach ([
                     'yMMMd',
                     'yMd',
                     'yMMMEd',
                     'Hm',
                     'yMMMMEEEEd',
                     'yQQQ',
                     'yQQQQ',
                     'MMMM',
                     'EEEE',
                     'GGGG',
                 ] as $skeleton) {
            $this->assertSame(
                $this->ref($datetime, $skeleton),
                $intl->date($datetime)->skeleton($skeleton),
                "Skeleton {$skeleton} does not match the reference formatter"
            );
        }
    }

    public function test_skeleton_shows_fields_the_styles_cannot(): void
    {
        $intl = $this->intl();
        $datetime = $this->at();

        // The strongest argument for skeletons: no combination of the fixed
        // styles produces a quarter, so format() cannot reach this at all.
        $quarter = $intl->date($datetime)->skeleton('yQQQ');

        $this->assertSame($this->ref($datetime, 'yQQQ'), $quarter);
        $this->assertMatchesRegularExpression(
            '/Q[1-4]\s+\d{4}/',
            $quarter,
            'A quarter skeleton must name the quarter and the year'
        );

        $this->assertStringContainsString(
            'Sat',
            $intl->date($datetime)->skeleton('EEE'),
            'A skeleton selects a single field the styles cannot isolate'
        );
    }

    public function test_skeleton_uses_translator_locale(): void
    {
        $datetime = $this->at();

        $english = $this->intl('en')->date($datetime)->skeleton('yMMMMEEEEd');
        $russian = $this->intl('ru')->date($datetime)->skeleton('yMMMMEEEEd');
        $french = $this->intl('fr')->date($datetime)->skeleton('yMMMMEEEEd');

        $this->assertSame($this->ref($datetime, 'yMMMMEEEEd', 'en'), $english);
        $this->assertSame($this->ref($datetime, 'yMMMMEEEEd', 'ru'), $russian);

        $this->assertNotSame($english, $russian, 'The locale must reach the skeleton');
        $this->assertNotSame($english, $french, 'The locale must reach the skeleton');
    }

    public function test_skeleton_uses_timezone(): void
    {
        $datetime = $this->at('2026-10-03 23:30:00');

        $utc = $this->intl('en', 'UTC')->date($datetime)->skeleton('yMdHms');
        $tokyo = $this->intl('en', 'Asia/Tokyo')->date($datetime)->skeleton('yMdHms');

        $this->assertSame($this->ref($datetime, 'yMdHms', 'en', 'UTC'), $utc);
        $this->assertSame($this->ref($datetime, 'yMdHms', 'en', 'Asia/Tokyo'), $tokyo);

        $this->assertNotSame($utc, $tokyo, 'The timezone must reach the skeleton');
    }

    public function test_skeleton_uses_calendar(): void
    {
        $datetime = $this->at();

        $gregorian = $this->intl('th', 'UTC', IntlDateFormatter::GREGORIAN)->date($datetime)->skeleton('yMMMd');
        $buddhist = $this->intl('th', 'UTC', IntlDateFormatter::TRADITIONAL)->date($datetime)->skeleton('yMMMd');

        $this->assertSame(
            $this->ref($datetime, 'yMMMd', 'th', 'UTC', IntlDateFormatter::GREGORIAN),
            $gregorian
        );

        $this->assertSame(
            $this->ref($datetime, 'yMMMd', 'th', 'UTC', IntlDateFormatter::TRADITIONAL),
            $buddhist
        );

        $this->assertNotSame($gregorian, $buddhist, 'The calendar must reach the skeleton');

        // A Thai Buddhist year is 543 years ahead of the Gregorian one.
        $this->assertStringContainsString('2569', $buddhist, 'The Thai Buddhist era year must be used');
        $this->assertStringContainsString('2026', $gregorian, 'The Gregorian year must be used');
    }

    public function test_skeleton_with_no_field_yields_null(): void
    {
        // ICU has no field symbol to map, so there is nothing it can format.
        $this->assertNull(
            $this->intl()->date($this->at())->skeleton(''),
            'An empty skeleton maps to no fields at all'
        );
    }

    public function test_unknown_locale_still_raises(): void
    {
        // The pattern generator falls back to the default locale rather than
        // raising, so it hands back a pattern for a locale ICU cannot resolve.
        // The formatter built from it then raises, exactly as format() does.
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Found unconstructed IntlDateFormatter');

        $this->intl('xx_YY')->date($this->at())->skeleton('yMMMd');
    }

    public function test_skeleton_is_a_separate_method_from_format(): void
    {
        $intl = $this->intl();
        $datetime = $this->at();

        $this->assertSame(
            $intl->date($datetime)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE),
            $intl->date($datetime)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE),
            'format() keeps taking the fixed styles'
        );

        // MEDIUM in English happens to be "Oct 3, 2026", which is what yMMMd gives,
        // so the comparison uses a skeleton no style can stand in for.
        $this->assertNotSame(
            $intl->date($datetime)->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE),
            $intl->date($datetime)->skeleton('yQQQ'),
            'A skeleton is not a format style'
        );
    }
}