<?php

declare(strict_types=1);

namespace App\Alpha\Domain\ValueObject;

final readonly class Day
{
    public static function today(): string
    {
        return date('Y-m-d');
    }
}
