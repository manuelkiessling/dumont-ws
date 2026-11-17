<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade;

use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingService;

// One method does more than hand on: not reported
final readonly class WorkingFacade implements ThingsInterface
{
    public function __construct(
        private ThingService $things
    ) {
    }

    public function nameOf(string $thingId): ?string
    {
        $name = $this->things->nameOf($thingId);

        return $name === null ? null : trim($name);
    }

    public function rename(string $thingId, string $name): void
    {
        $this->things->rename($thingId, $name);
    }
}
