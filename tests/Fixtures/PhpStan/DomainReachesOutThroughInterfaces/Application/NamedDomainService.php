<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Application;

use App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\ConcreteAdapter;

final class NamedDomainService
{
    public function __construct(private ConcreteAdapter $adapter)
    {
    }

    public function adapter(): ConcreteAdapter
    {
        return $this->adapter;
    }
}
