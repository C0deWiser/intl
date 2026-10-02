<?php

namespace Codewiser\Intl;

use Codewiser\Intl\Intl\CurrencyFormatter;
use Codewiser\Intl\Intl\DateFormatter;
use Codewiser\Intl\Intl\LocaleFormatter;
use Codewiser\Intl\Intl\NumberFormatter;
use Codewiser\Intl\Intl\PeriodFormatter;
use Codewiser\Intl\Intl\Traits\ConfigureCalendar;
use Codewiser\Intl\Intl\Traits\ConfigureCurrency;
use Codewiser\Intl\Intl\Traits\ConfigureTimezone;
use Codewiser\Intl\Intl\Traits\ConfigureTransliterator;
use Codewiser\Intl\Intl\Transliterator;
use Illuminate\Contracts\Translation\Translator;
use Psr\Log\LoggerAwareTrait;

class IntlManager
{
    use LoggerAwareTrait, ConfigureTimezone, ConfigureCurrency, ConfigureCalendar, ConfigureTransliterator;

    /**
     * @param  Translator  $translator
     * @param  string  $timezone
     * @param  int  $calendar  \IntlDateFormatter::GREGORIAN etc.
     * @param  string  $currency  The 3-letter ISO 4217 currency code indicating the currency to use.
     * @param  string  $transliterator One of https://www.php.net/manual/en/transliterator.listids.php
     */
    public function __construct(
        protected Translator $translator,
        protected string $timezone,
        protected int $calendar,
        protected string $currency,
        protected string $transliterator,
    ) {
        //
    }

    /**
     * Format the date-time value as a string.
     */
    public function date(\DateTimeInterface $datetime): DateFormatter
    {
        $formatter = new DateFormatter($datetime, $this->translator, $this->timezone, $this->calendar);

        return $this->withLogger($formatter);
    }

    /**
     * Format the date period value as a string.
     */
    public function period(\DateTimeInterface|\DatePeriod|array $period, ?\DateTimeInterface $end = null): PeriodFormatter
    {
        $exception = new \InvalidArgumentException('Period should be either \DatePeriod, or two \DateTimeInterface');

        if (is_array($period)) {
            $start = $period[0] ?? null;
            $end = $period[1] ?? null;

            if (! $start instanceof \DateTimeInterface || ! $end instanceof \DateTimeInterface) {
                throw $exception;
            }

            $period = new \DatePeriod($start, $start->diff($end), $end);
        }

        if ($period instanceof \DateTimeInterface) {
            if (! $end instanceof \DateTimeInterface) {
                throw $exception;
            }

            $period = new \DatePeriod($period, $period->diff($end), $end);
        }

        $formatter = new PeriodFormatter($period, $this->translator, $this->timezone, $this->calendar);

        return $this->withLogger($formatter);
    }

    /**
     * Format a number.
     *
     * @param  int|float  $num  The number to format.
     */
    public function number(int|float $num): NumberFormatter
    {
        $formatter = new NumberFormatter($num, $this->translator, $this->currency);

        return $this->withLogger($formatter);
    }

    /**
     * Name a locale, e.g. to label a language switcher.
     *
     * @param  null|string  $locale  The locale to handle. NULL to inspect app's locale.
     */
    public function locale(?string $locale = null): LocaleFormatter
    {
        return $this->withLogger(
            new LocaleFormatter($this->translator,
            $locale ?? $this->translator->getLocale()
        ));
    }

    /**
     * Name a currency, e.g. to fill a currency selector.
     *
     * @param  null|string  $currency  The 3-letter ISO 4217 code. NULL to inspect app's default currency.
     */
    public function currency(?string $currency = null): CurrencyFormatter
    {
        return $this->withLogger(new CurrencyFormatter($this->translator, $currency ?? $this->currency));
    }

    /**
     * Convert text between writing systems, or between Unicode normal forms.
     */
    public function text(): Transliterator
    {
        return $this->withLogger(new Transliterator($this->translator, $this->transliterator));
    }

    /**
     * Hands the manager's logger to a formatter.
     *
     * @template T of object
     *
     * @param  T  $formatter
     *
     * @return T
     */
    protected function withLogger(object $formatter): object
    {
        if ($logger = $this->logger) {
            $formatter->setLogger($logger);
        }

        return $formatter;
    }
}