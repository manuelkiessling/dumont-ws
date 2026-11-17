<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Entity;

final class Thing
{
    private string $kind = 'one';

    private int|string|null $holderKind = null;

    public function getKind(): string
    {
        return $this->kind;
    }

    public function holder(): int|string|null
    {
        return $this->holderKind;
    }
}
