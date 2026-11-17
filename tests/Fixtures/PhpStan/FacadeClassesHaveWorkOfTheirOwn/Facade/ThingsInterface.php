<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\FacadeClassesHaveWorkOfTheirOwn\Facade;

interface ThingsInterface
{
    public function nameOf(string $thingId): ?string;

    public function rename(string $thingId, string $name): void;
}
