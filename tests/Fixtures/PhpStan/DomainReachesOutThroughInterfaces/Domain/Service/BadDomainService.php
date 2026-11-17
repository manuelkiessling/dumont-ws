<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Domain\Service;

use App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\ConcreteAdapter;

final class BadDomainService
{
    public function __construct(
        private SiblingService $sibling,
        private ConcreteAdapter $adapter
    ) {
    }

    public function sibling(): SiblingService
    {
        return $this->sibling;
    }

    public function adapter(): ConcreteAdapter
    {
        return $this->adapter;
    }
}
