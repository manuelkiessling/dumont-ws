<?php

declare(strict_types=1);

namespace App\Todo\Domain\Exception;

final class ItemNotOnTheListException extends TodoRefusedException
{
    public static function withId(string $itemId): self
    {
        return new self('This item is no longer on your list.');
    }
}
