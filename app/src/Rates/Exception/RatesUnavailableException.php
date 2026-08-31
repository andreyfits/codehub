<?php

declare(strict_types=1);

namespace App\Rates\Exception;

final class RatesUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Rates are not available yet. Run "bin/console app:rates:fetch" first.');
    }
}
