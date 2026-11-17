<?php

declare(strict_types=1);

namespace App\Alpha\Presentation\Controller;

final class ThingController
{
    public function showAction(string $subjectKind): string
    {
        return $subjectKind;
    }
}
