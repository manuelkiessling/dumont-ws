<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoAssociativeArraysAcrossBoundaries\Bad;

final class PublicReturnAssociative
{
    /** @return array<string, int> */
    public function getMap(): array
    {
        return ['a' => 1, 'b' => 2];
    }
}
