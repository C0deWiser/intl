<?php

namespace Codewiser\Intl\Intl\Traits;

trait ConfigureCalendar
{
    /**
     * Set default Calendar for DateFormatter.
     *
     * @param  int  $calendar  \IntlDateFormatter::GREGORIAN etc.
     */
    public function useCalendar(int $calendar): static
    {
        $this->calendar = $calendar;

        return $this;
    }
}