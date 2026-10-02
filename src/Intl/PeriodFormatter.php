<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureCalendar;
use Codewiser\Intl\Intl\Traits\ConfigureTimezone;
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
    use Macroable, LoggerAwareTrait, ConfigureTimezone, ConfigureCalendar;

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
                'date'  => $this->dateFormatter($start)->format(date: $date),
                'start' => $this->dateFormatter($start)->format(time: $time),
                'end'   => $this->dateFormatter($end)->format(time: $time)
            ]);
        } else {
            return $this->translator->get('intl::calendar.date-period', [
                'start' => $this->dateFormatter($start)->format($date, $time),
                'end'   => $this->dateFormatter($end)->format($date, $time)
            ]);
        }
    }

    /**
     * Describe the date relative to another one, e.g. "3 days ago".
     *
     * The unit is the coarsest one that is non-zero, so a difference of 36
     * hours is "1 day". That is a calendar day rather than 24 hours, which is
     * the point: across a DST change the two are not the same length, and
     * "yesterday" should still come out as one day.
     *
     * Plural forms and number formatting come from ICU rather than from a
     * Laravel "singular|plural" line, since only the former knows that Russian
     * needs three forms and French treats zero as singular.
     */
    public function relative(): ?string
    {
        return $this
            ->dateFormatter($this->period->getStartDate())
            ->relative($this->period->getEndDate());
    }
}