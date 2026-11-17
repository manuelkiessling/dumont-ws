<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Service;

use App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Dto\ThingDto as DomainThingDto;
use App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Facade\Dto\ThingDto;

final readonly class CopyingService
{
    // The copy is the same wherever it stands: reported
    public function one(DomainThingDto $thing): ThingDto
    {
        return new ThingDto($thing->id, $thing->label);
    }

    // The vertical reads into the facade's DTO: not reported
    public function read(string $id, string $label): ThingDto
    {
        return new ThingDto($id, $label);
    }
}
