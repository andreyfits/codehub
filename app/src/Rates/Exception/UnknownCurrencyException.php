<?php

declare(strict_types=1);

namespace App\Rates\Exception;

final class UnknownCurrencyException extends \InvalidArgumentException
{
    public function __construct(public readonly string $currency)
    {
        parent::__construct(sprintf('Unknown currency or coin code "%s".', $currency));
    }
}
