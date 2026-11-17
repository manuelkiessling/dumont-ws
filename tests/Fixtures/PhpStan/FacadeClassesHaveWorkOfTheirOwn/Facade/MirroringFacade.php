<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade;

use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingService;

// Every method hands its parameters on to the one service: reported
final readonly class MirroringFacade implements ThingsInterface
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
        $this->things->rename($thingId, $name);
    }
}
