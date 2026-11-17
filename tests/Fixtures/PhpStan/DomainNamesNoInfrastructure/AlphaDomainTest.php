<?php

declare(strict_types=1);

namespace App\Tests\Alpha\Domain;

use App\Alpha\Infrastructure\AlphaFileStore;

final class AlphaDomainTest
{
    public function store(AlphaFileStore $store): AlphaFileStore
    {
        return $store;
    }
}
