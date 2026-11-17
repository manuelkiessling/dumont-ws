<?php

declare(strict_types=1);

namespace App\Delta\Domain;

use App\Alpha\Domain\AlphaDomainService;
use App\Gamma\Facade\GammaDto;

final class DeltaService
{
    public function gamma(AlphaDomainService $alpha): GammaDto
    {
        return $alpha->gamma(new GammaDto());
    }
}
