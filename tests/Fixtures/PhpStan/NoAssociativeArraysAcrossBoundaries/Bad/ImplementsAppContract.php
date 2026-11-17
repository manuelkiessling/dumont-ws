<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoAssociativeArraysAcrossBoundaries\Bad;

interface AppContractWithAssociativeParam
{
    /** @param array<string, int> $map */
    public function setMap(array $map): void;
}

final class ImplementsAppContract implements AppContractWithAssociativeParam
{
    /** @param array<string, int> $map */
    public function setMap(array $map): void
    {
        // noop
    }
}
