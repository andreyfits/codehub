<?php

declare(strict_types=1);

namespace App\Rates;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.rate_provider')]
interface RateProviderInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return array<string, float>
     */
    public function fetchRates(): array;
}
