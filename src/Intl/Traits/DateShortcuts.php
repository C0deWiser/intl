<?php

namespace Codewiser\Intl\Intl\Traits;

use IntlDateFormatter;

trait DateShortcuts
{
    /**
     * Format datetime by pattern.
     *
     * @example 'f'   or 'full'        makes full date, no time.
     * @example 'l-m' or 'long-medium' makes long date, medium time.
     * @example '-s'  or '-short'      makes short time, no date.
     */
    public function as(string $format): ?string
    {
        $format = explode('-', $format);

        $resolve = fn($f) => match ($f) {
            's', 'short'  => IntlDateFormatter::SHORT,
            'm', 'medium' => IntlDateFormatter::MEDIUM,
            'l', 'long'   => IntlDateFormatter::LONG,
            'f', 'full'   => IntlDateFormatter::FULL,
            default       => IntlDateFormatter::NONE,
        };

        $date = $resolve($format[0]);
        $time = $resolve($format[1] ?? null);

        return $this->format($date, $time);
    }

    /**
     * Format date only.
     */
    public function date(int $date, int $time = IntlDateFormatter::NONE): ?string
    {
        return $this->format($date, $time);
    }

    /**
     * Format time only.
     */
    public function time(int $time): ?string
    {
        return $this->format(IntlDateFormatter::NONE, $time);
    }

    /**
     * Short datetime format.
     *
     * @example 12/13/52 3:30pm
     */
    public function short(
        int $date = IntlDateFormatter::SHORT,
        int $time = IntlDateFormatter::SHORT
    ): ?string {
        return $this->format($date, $time);
    }

    /**
     * Medium datetime format.
     *
     * @example Jan 12, 1952 3:30pm
     */
    public function medium(
        int $date = IntlDateFormatter::MEDIUM,
        int $time = IntlDateFormatter::MEDIUM
    ): ?string {
        return $this->format($date, $time);
    }

    /**
     * Long datetime format.
     *
     * @example January 12, 1952 3:30:32pm
     */
    public function long(
        int $date = IntlDateFormatter::LONG,
        int $time = IntlDateFormatter::LONG
    ): ?string {
        return $this->format($date, $time);
    }

    /**
     * Full datetime format.
     *
     * @example Tuesday, April 12, 1952 AD 3:30:42pm PST
     */
    public function full(
        int $date = IntlDateFormatter::FULL,
        int $time = IntlDateFormatter::FULL
    ): ?string {
        return $this->format($date, $time);
    }
}