<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service;

use App\Alpha\Domain\AlphaPortInterface;
use App\Alpha\Infrastructure\AlphaFileStore;

final class AlphaDomainService
{
    public function __construct(
        private AlphaPortInterface $port,
        private AlphaFileStore $store
    ) {
    }

    public function isAdapter(object $service): bool
    {
        return $service instanceof \App\Alpha\Infrastructure\AlphaAdapter;
    }

    public function store(): AlphaFileStore
    {
        return $this->store;
    }

    public function port(): AlphaPortInterface
    {
        return $this->port;
    }
}
