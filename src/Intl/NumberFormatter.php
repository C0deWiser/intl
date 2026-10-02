<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureCurrency;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use Psr\Log\LoggerAwareTrait;

class NumberFormatter
{
    use Macroable, LoggerAwareTrait, ConfigureCurrency;

    public function __construct(
        protected int|float $num,
        protected Translator $translator,
        protected string $currency,
    ) {
        //
    }

    protected function formatter(int $format): ?\NumberFormatter
    {
        return numfmt_create($this->translator->getLocale(), $format);
    }

    /**
     * Format a number.
     *
     * @param  int  $format \NumberFormatter::DECIMAL, \NumberFormatter::PERCENT, etc.
     */
    public function format(int $format): ?string
    {
        if ($format === \NumberFormatter::CURRENCY) {
            return $this->currency();
        }

        return $this
            ->formatter($format)
            ?->format($this->num) ?: null;
    }

    // Shortcuts to format() for a style that needs nothing but the number
    // itself. Anything else goes through format() directly.

    /**
     * Format the number as a decimal.
     */
    public function decimal(): ?string
    {
        return $this->format(\NumberFormatter::DECIMAL);
    }

    /**
     * Format the number as a percentage of 1.
     */
    public function percent(): ?string
    {
        return $this->format(\NumberFormatter::PERCENT);
    }

    /**
     * Format the number in scientific notation.
     */
    public function scientific(): ?string
    {
        return $this->format(\NumberFormatter::SCIENTIFIC);
    }

    /**
     * Spell the number out in words.
     */
    public function spellout(): ?string
    {
        return $this->format(\NumberFormatter::SPELLOUT);
    }

    /**
     * Format the number as an ordinal, e.g. "3rd".
     */
    public function ordinal(): ?string
    {
        return $this->format(\NumberFormatter::ORDINAL);
    }

    /**
     * Format the number as a length of time.
     */
    public function duration(): ?string
    {
        return $this->format(\NumberFormatter::DURATION);
    }

    /**
     * Format a currency value.
     *
     * @param  string|null  $currency  The 3-letter ISO 4217 currency code indicating the currency to use.
     */
    public function currency(string $currency = null): ?string
    {
        $formatted = $this
            ->formatter(\NumberFormatter::CURRENCY)
            ?->formatCurrency($this->num, $currency ?? $this->currency);

        return $formatted ?: null;
    }
}