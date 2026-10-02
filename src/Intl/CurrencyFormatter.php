<?php

namespace Codewiser\Intl\Intl;

use Codewiser\Intl\Intl\Traits\ConfigureCurrency;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use Psr\Log\LoggerAwareTrait;
use ResourceBundle;

/**
 * Names and symbols for a currency, written in the current locale.
 *
 * A formatted amount says "$1,234.56", which is no help to someone who does not
 * know which currency that is. These read the ISO 4217 data ICU ships, so there
 * is nothing to translate and no list to keep current.
 */
class CurrencyFormatter
{
    use Macroable, LoggerAwareTrait, ConfigureCurrency;

    /**
     * Index of the full name, e.g. "российский рубль", in an ICU currency entry.
     */
    protected const NAME = 1;

    /**
     * Index of the symbol, e.g. "₽", in an ICU currency entry.
     */
    protected const SYMBOL = 0;

    /**
     * @param  string  $currency  The 3-letter ISO 4217 code, e.g. "RUB".
     */
    public function __construct(
        protected Translator $translator,
        protected string $currency,
    ) {
        //
    }

    /**
     * The currency name, e.g. "Russian Ruble".
     */
    public function name(?string $locale = null): ?string
    {
        return $this->entry($locale)?->get(static::NAME);
    }

    /**
     * The currency symbol, e.g. "$".
     */
    public function symbol(?string $locale = null): ?string
    {
        return $this->entry($locale)?->get(static::SYMBOL);
    }

    /**
     * Looks the currency up in the ICU currency bundle.
     *
     * The codes are keyed uppercase, and unlike formatCurrency() the bundle does
     * not fold them: "usd" is simply absent. A code ICU does not know is
     * missing too, which is reported as null rather than as the code itself.
     */
    protected function entry(?string $locale): ?ResourceBundle
    {
        $code = strtoupper($this->currency ?: '');

        if ($code === '') {
            return null;
        }

        // A ResourceBundle is not an array: it is reached through get(), which
        // answers null for anything it does not hold.
        $currencies = (new ResourceBundle($locale ?? $this->translator->getLocale(), 'ICUDATA-curr'))
            ->get('Currencies');

        return $currencies?->get($code);
    }
}