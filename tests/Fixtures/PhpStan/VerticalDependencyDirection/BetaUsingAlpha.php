<?php

declare(strict_types=1);

namespace App\Beta\Domain;

final class BetaUsingAlpha
{
    public function alpha(): string
    {
        return \App\Alpha\Domain\AlphaDomainService::class;
    }
}
