<?php

namespace Codewiser\Intl;

use Carbon\CarbonPeriod;
use DateTimeInterface;
use IntlDateFormatter;

class IntlDate
{
    public string $locale;

    public function __construct(public null|DateTimeInterface|CarbonPeriod $dateTime, ?string $locale = null)
    {
        $this->locale = $locale ?? app()->getLocale();
    }

    /**
     * Tuesday, April 12, 1952 AD
     */
    public function fullDate(): string
    {
        return $this
            ->format(IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    }

    public function format(int $dateType = IntlDateFormatter::NONE, int $timeType = IntlDateFormatter::NONE): bool|string
    {
        if ($this->dateTime instanceof CarbonPeriod) {

            $start = $this->dateTime->start;
            $end = $this->dateTime->end;

            if ($dateType !== IntlDateFormatter::NONE && $start->isSameDay($end)) {
                return __(':date from :start to :end', [
                    'date'  => $this->formatDateTime($start, $dateType, IntlDateFormatter::NONE),
                    'start' => $this->formatDateTime($start, IntlDateFormatter::NONE, $timeType),
                    'end'   => $this->formatDateTime($end, IntlDateFormatter::NONE, $timeType)
                ]);
            } else {
                return __('from :start to :end', [
                    'start' => $this->formatDateTime($start, $dateType, $timeType),
                    'end'   => $this->formatDateTime($end, $dateType, $timeType)
                ]);
            }

        }

        return $this->formatDateTime($this->dateTime, $dateType, $timeType);
    }

    protected function formatDateTime(?DateTimeInterface $dateTime, int $dateType = IntlDateFormatter::NONE, int $timeType = IntlDateFormatter::NONE): bool|string
    {
        if ($dateTime) {
            $fmt = datefmt_create(
                $this->locale,
                $dateType,
                $timeType,
                config('app.timezone'),
                IntlDateFormatter::GREGORIAN,
            );

            return $fmt->format($dateTime);
        } else {
            return '';
        }
    }

    /**
     * 3:30:42pm PST
     */
    public function fullTime(): string
    {
        return $this
            ->format(IntlDateFormatter::NONE, IntlDateFormatter::FULL);
    }

    /**
     * Tuesday, April 12, 1952 AD 3:30:42pm PST
     */
    public function full(): string
    {
        return $this
            ->format(IntlDateFormatter::FULL, IntlDateFormatter::FULL);
    }

    /**
     * January 12, 1952
     */
    public function longDate(): string
    {
        return $this
            ->format(IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    }

    /**
     * 3:30:32pm
     */
    public function longTime(): string
    {
        return $this
            ->format(IntlDateFormatter::NONE, IntlDateFormatter::LONG);
    }

    /**
     * January 12, 1952 3:30:32pm
     */
    public function long(): string
    {
        return $this
            ->format(IntlDateFormatter::LONG, IntlDateFormatter::LONG);
    }

    /**
     * Jan 12, 1952
     */
    public function mediumDate(): string
    {
        return $this
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
    }

    /**
     * 3:30pm
     */
    public function mediumTime(): string
    {
        return $this
            ->format(IntlDateFormatter::NONE, IntlDateFormatter::MEDIUM);
    }

    /**
     * Jan 12, 1952 3:30pm
     */
    public function medium(): string
    {
        return $this
            ->format(IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
    }

    /**
     * 12/13/52
     */
    public function shortDate(): string
    {
        return $this
            ->format(IntlDateFormatter::SHORT, IntlDateFormatter::NONE);
    }

    /**
     * 3:30pm
     */
    public function shortTime(): string
    {
        return $this
            ->format(IntlDateFormatter::NONE, IntlDateFormatter::SHORT);
    }

    /**
     * 12/13/52 3:30pm
     */
    public function short(): string
    {
        return $this
            ->format(IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    }
}
