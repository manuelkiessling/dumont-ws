<?php

declare(strict_types=1);

namespace App\Alpha\Facade;

use App\Alpha\Facade\Enum\ThingKind;

interface AlphaFacadeInterface
{
    /**
     * @return list<string>
     */
    public function thingsAbout(string $workspaceId, string $subjectKind, string $subjectId): array;

    public function thingsOf(string $workspaceId, ThingKind $kind): int;

    public function kind(): string;

    public function thingKind(string $thingId): ?ThingKind;

    public function nameOfKind(ThingKind $kind): string;
}
