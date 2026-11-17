<?php

declare(strict_types=1);

namespace App\Gamma\Facade;

use App\Alpha\Domain\AlphaDomainService;

final class GammaDto
{
    public function origin(): string
    {
        return AlphaDomainService::class;
    }
}
