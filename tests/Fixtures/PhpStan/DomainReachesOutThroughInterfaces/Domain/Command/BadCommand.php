<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Domain\Command;

use App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\ConcreteAdapter;

final class BadCommand
{
    public function __construct(
        private ConcreteAdapter $adapter
    ) {
    }

    public function adapter(): ConcreteAdapter
    {
        return $this->adapter;
    }
}
