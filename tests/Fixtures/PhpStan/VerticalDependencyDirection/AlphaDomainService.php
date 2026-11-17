<?php

declare(strict_types=1);

namespace App\Alpha\Domain;

use App\Beta\Facade\BetaFacadeInterface;
use App\Common\Domain\Service\CommonClock;
use App\Gamma\Facade\GammaDto;

final class AlphaDomainService
{
    public function __construct(
        private BetaFacadeInterface $beta
    ) {
    }

    public function clock(): CommonClock
    {
        return $this->beta->clock();
    }

    public function gamma(GammaDto $dto): GammaDto
    {
        return $dto instanceof \App\Gamma\Facade\GammaDto ? new GammaDto() : $dto;
    }
}
