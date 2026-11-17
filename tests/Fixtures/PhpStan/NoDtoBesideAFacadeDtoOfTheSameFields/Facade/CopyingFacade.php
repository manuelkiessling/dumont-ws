<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Facade;

use App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Dto\ThingDto as DomainThingDto;
use App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Dto\WideThingDto;
use App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Domain\Entity\Thing;
use App\Tests\Fixtures\PhpStan\NoDtoBesideAFacadeDtoOfTheSameFields\Facade\Dto\ThingDto;

final readonly class CopyingFacade
{
    // A copy, field by field, of a DTO of the same fields: reported
    public function one(DomainThingDto $thing): ThingDto
    {
        return new ThingDto($thing->id, $thing->label);
    }

    /**
     * The same copy in a loop: reported
     *
     * @param list<DomainThingDto> $things
     *
     * @return list<ThingDto>
     */
    public function all(array $things): array
    {
        $converted = [];
        foreach ($things as $thing) {
            $converted[] = new ThingDto($thing->id, $thing->label);
        }

        return $converted;
    }

    // The facade's DTO leaves a field out, so the two are not the same: not reported
    public function narrowed(WideThingDto $thing): ThingDto
    {
        return new ThingDto($thing->id, $thing->label);
    }

    // An entity is converted: not reported
    public function ofEntity(Thing $thing): ThingDto
    {
        return new ThingDto($thing->id, $thing->label);
    }

    // A field is worked on: not reported
    public function trimmed(DomainThingDto $thing): ThingDto
    {
        return new ThingDto($thing->id, trim($thing->label));
    }

    // The fields come from two things: not reported
    public function mixed(DomainThingDto $thing, DomainThingDto $other): ThingDto
    {
        return new ThingDto($thing->id, $other->label);
    }

    // The fields change places: not reported
    public function swapped(DomainThingDto $thing): ThingDto
    {
        return new ThingDto($thing->label, $thing->id);
    }
}
