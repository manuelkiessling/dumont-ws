<?php

declare(strict_types=1);

namespace App\Tests\Support;

final class Helper
{
    public function of(string $kind): string
    {
        return $kind;
    }
}
