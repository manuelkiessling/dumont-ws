<?php

declare(strict_types=1);

namespace App\Beta\Facade;

use App\Common\Domain\Service\CommonClock;

interface BetaFacadeInterface
{
    public function clock(): CommonClock;
}
