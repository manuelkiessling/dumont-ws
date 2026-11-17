<?php

declare(strict_types=1);

namespace App\Alpha\Facade;

use App\Alpha\Domain\Model\Thing;
use App\Alpha\Domain\Service\ThingService;
use App\Alpha\Facade\Dto\ThingDto;
use App\Common\Domain\Model\CommonRecord;
use App\Common\Domain\Enum\Timezone;

interface AlphaFacadeInterface
{
    public function thing(string $id): ?ThingDto;

    public function timezone(): Timezone;

    public function entity(string $id): Thing;

    public function store(Thing $thing): void;

    /**
     * @return list<Thing>
     */
    public function entities(): array;

    public function record(): CommonRecord;

    public function service(): ThingService;
}
