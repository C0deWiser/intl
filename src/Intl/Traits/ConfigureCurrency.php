<?php

namespace Codewiser\Intl\Intl\Traits;

trait ConfigureCurrency
{
    /**
     * Set default Currency for NumberFormatter.
     *
     * @param  string  $currency  The 3-letter ISO 4217 currency code indicating the currency to use.
     */
    public function useCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }
}