<?php

namespace Codewiser\Intl\Intl\Traits;

trait ConfigureTransliterator
{
    /**
     * Set default Rule for Transliterator.
     *
     * @see https://www.php.net/manual/en/transliterator.listids.php
     */
    public function useTransliterator(string $transliterator): static
    {
        $this->transliterator = $transliterator;

        return $this;
    }
}