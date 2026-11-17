<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Domain\Service;

use App\Tests\Fixtures\PhpStan\DomainReachesOutThroughInterfaces\Infrastructure\AdapterInterface;

final class GoodDomainService
{
    public function __construct(
        private SiblingService $sibling,
        private AdapterInterface $adapter,
        private ?SiblingService $other = null,
        private string $name = ''
    ) {
    }

    public function sibling(): SiblingService
    {
        return $this->other ?? $this->sibling;
    }

    public function adapter(): AdapterInterface
    {
        return $this->adapter;
    }

    public function name(): string
    {
        return $this->name;
    }
}
