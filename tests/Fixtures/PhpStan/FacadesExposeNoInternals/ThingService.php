<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service;

use App\Alpha\Domain\Model\Thing;

final class ThingService
{
    public function thing(): Thing
    {
        return new Thing();
    }
}
