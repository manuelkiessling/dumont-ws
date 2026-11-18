<?php

declare(strict_types=1);

namespace App\Todo\Facade\Dto;

/**
 * An item of the todo list, as other verticals see it.
 */
final readonly class TodoItemDto
{
    public function __construct(
        public string $id,
        public string $text,
        public bool   $done
    ) {
    }
}
