<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade;

use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingService;

// It stands behind no interface of the facade, so it mirrors no contract: not reported
final readonly class NoContract
{
    public function __construct(
        private ThingService $things
    ) {
    }

    public function nameOf(string $thingId): ?string
    {
        return $this->things->nameOf($thingId);
    }
}
