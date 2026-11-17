<?php

declare(strict_types=1);

namespace App\Alpha\Infrastructure;

use App\Alpha\Domain\AlphaPortInterface;

final class AlphaAdapter implements AlphaPortInterface
{
    public function __construct(
        private AlphaFileStore $store
    ) {
    }

    public function store(): AlphaFileStore
    {
        return $this->store;
    }
}
