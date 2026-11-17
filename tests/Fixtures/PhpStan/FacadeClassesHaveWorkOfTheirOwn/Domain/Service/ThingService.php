<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service;

use App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade\ThingsInterface;

// The class that answers implements the facade interface itself: nothing to report
final readonly class ThingService implements ThingsInterface
{
    public function nameOf(string $thingId): ?string
    {
        return $thingId === '' ? null : 'a thing';
    }

    public function rename(string $thingId, string $name): void
    {
    }

    public function renameAs(string $name, string $thingId): void
    {
    }
}
