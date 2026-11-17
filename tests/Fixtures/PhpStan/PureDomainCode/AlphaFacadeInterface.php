<?php

declare(strict_types=1);

namespace App\Alpha\Facade;

use App\Alpha\Facade\Dto\ThingDto;

interface AlphaFacadeInterface
{
    public function thing(string $id): ThingDto;
}
