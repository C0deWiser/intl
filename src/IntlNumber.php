<?php

namespace Codewiser\Intl;

use NumberFormatter;

class IntlNumber
{
    public string $locale;
    public array $attributes = [];

    public function __construct(public null|float $amount, ?string $locale = null)
    {
        $this->locale = $locale ?? app()->getLocale();
    }

    public function style(array $styles): static
    {
        $this->attributes = $styles;

        return $this;
    }

    public function format($style): string
    {
        return $this->handle(
            new NumberFormatter($this->locale, $style)
        );
    }

    protected function handle(NumberFormatter $fmt, string $currency = null): string
    {
        if (is_null($this->amount)) {
            return '';
        }

        foreach ($this->attributes as $attribute => $value) {
            $fmt->setAttribute($attribute, $value);
        }

        $formatted = $currency
            ? $fmt->formatCurrency($this->amount, $currency)
            : $fmt->format($this->amount);

        if (intl_is_failure($fmt->getErrorCode())) {
            throw new \RuntimeException($fmt->getErrorMessage());
        }

        return $formatted;
    }

    public function decimal(array $styles = []): string
    {
        return $this
            ->style($styles)
            ->format(NumberFormatter::DECIMAL);
    }

    public function percent(array $styles = []): string
    {
        return $this
            ->style($styles)
            ->format(NumberFormatter::PERCENT);
    }

    public function scientific(array $styles = []): string
    {
        return $this
            ->style($styles)
            ->format(NumberFormatter::SCIENTIFIC);
    }

    public function spellout(array $styles = []): string
    {
        return $this
            ->style($styles)
            ->format(NumberFormatter::SPELLOUT);
    }

    /**
     * Format a currency value
     *
     * @link https://php.net/manual/en/numberformatter.formatcurrency.php
     *
     * @param  string  $currency  The 3-letter ISO 4217 currency code indicating the currency to use.
     *
     * @return string String representing the formatted currency value.
     */
    public function currency(string $currency, array $styles = []): string
    {
        return $this
            ->style($styles)
            ->handle(
                new NumberFormatter($this->locale, NumberFormatter::CURRENCY),
                $currency
            );
    }
}