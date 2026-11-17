<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoAssociativeArraysAcrossBoundaries\Good;

interface InterfaceReturnSimpleList
{
    /** @return list<int> */
    public function ids(): array;
}
