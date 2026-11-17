<?php

declare(strict_types=1);

namespace App\Todo\Domain\Exception;

final class InvalidItemTextException extends TodoRefusedException
{
    public static function empty(): self
    {
        return new self('Please write what there is to do.');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self("An item is at most {$maxLength} characters long.");
    }

    public static function notOneLine(): self
    {
        return new self('An item is one line of text.');
    }
}
