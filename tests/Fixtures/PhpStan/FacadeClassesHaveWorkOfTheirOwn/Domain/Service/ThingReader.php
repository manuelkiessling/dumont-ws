<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Domain\Service;

final readonly class ThingReader
{
    public function nameOf(string $thingId): ?string
    {
        return $thingId === '' ? null : 'a thing';
    }
}
