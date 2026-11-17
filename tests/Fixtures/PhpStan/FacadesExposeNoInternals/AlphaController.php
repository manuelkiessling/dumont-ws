<?php

declare(strict_types=1);

namespace App\Alpha\Presentation\Controller;

use App\Alpha\Domain\Model\Thing;

final class AlphaController
{
    public function thing(): Thing
    {
        return new Thing();
    }
}
