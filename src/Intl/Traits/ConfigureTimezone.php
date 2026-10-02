<?php

namespace Codewiser\Intl\Intl\Traits;

trait ConfigureTimezone
{
    /**
     * Set default Timezone for DateFormatter.
     */
    public function useTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }
}