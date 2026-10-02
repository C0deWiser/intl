<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureCalendar;
use Codewiser\Intl\Intl\Traits\ConfigureTimezone;
use Codewiser\Intl\Intl\Traits\DateShortcuts;
use DatePeriod;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Psr\Log\LoggerAwareTrait;

class PeriodFormatter
{
    use Macroable, LoggerAwareTrait, ConfigureTimezone, ConfigureCalendar, DateShortcuts;

    public function __construct(
        protected DatePeriod $period,
        protected Translator $translator,
        protected string $timezone,
        protected int $calendar,
    ) {
        //
    }

    protected function dateFormatter(\DateTimeInterface $datetime): DateFormatter
    {
        return new DateFormatter($datetime, $this->translator, $this->timezone, $this->calendar);
    }

    public function format(
        int $date = IntlDateFormatter::NONE,
        int $time = IntlDateFormatter::NONE
    ): ?string {

        $start = $this->period->getStartDate();
        $end = $this->period->getEndDate();

        // Compared in the calendar timezone, since that is how the range
        // is rendered below. Comparing the raw DateTime zones would report
        // "spans days" for a range that renders as a single day.
        $tz = new DateTimeZone($this->timezone);

        if ($date !== IntlDateFormatter::NONE
            && $start->setTimezone($tz)->format('Y.m.d') === $end?->setTimezone($tz)->format('Y.m.d')
        ) {
            // Within one day
            return $this->translator->get('intl::calendar.time-period', [
                'date'  => $this->dateFormatter($start)->date($date),
                'start' => $this->dateFormatter($start)->time($time),
                'end'   => $this->dateFormatter($end)->time($time)
            ]);
        } else {
            return $this->translator->get('intl::calendar.date-period', [
                'start' => $this->dateFormatter($start)->format($date, $time),
                'end'   => $this->dateFormatter($end)->format($date, $time)
            ]);
        }
    }
}