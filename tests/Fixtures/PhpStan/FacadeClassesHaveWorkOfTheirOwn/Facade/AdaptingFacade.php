<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade;

use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingService;

// One call is adapted, its parameters handed on in another order: not reported
final readonly class AdaptingFacade implements ThingsInterface
{
    public function __construct(
        private ThingService $things
    ) {
    }

    public function nameOf(string $thingId): ?string
    {
        return $this->things->nameOf($thingId);
    }

    public function rename(string $thingId, string $name): void
    {
        $this->things->renameAs($name, $thingId);
    }
}
