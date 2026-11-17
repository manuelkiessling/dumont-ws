<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade;

use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingReader;
use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service\ThingService;

// It composes the reader and the service, which is work of its own: not reported
final readonly class ComposingFacade implements ThingsInterface
{
    public function __construct(
        private ThingReader  $reader,
        private ThingService $things
    ) {
    }

    public function nameOf(string $thingId): ?string
    {
        return $this->reader->nameOf($thingId);
    }

    public function rename(string $thingId, string $name): void
    {
        $this->things->rename($thingId, $name);
    }
}
