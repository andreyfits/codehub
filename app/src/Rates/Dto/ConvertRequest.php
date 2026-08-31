<?php

declare(strict_types=1);

namespace App\Rates\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ConvertRequest
{
    #[Assert\NotBlank]
    public string $from = '';

    #[Assert\NotBlank]
    public string $to = '';

    #[Assert\Positive]
    public float $amount = 0.0;
}
