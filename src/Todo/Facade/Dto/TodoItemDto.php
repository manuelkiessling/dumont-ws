<?php

declare(strict_types=1);

namespace App\Todo\Facade\Dto;

/**
 * What other verticals know of an item of the todo list.
 */
final readonly class TodoItemDto
{
    public function __construct(
        public string $id,
        public string $text,
        public bool   $isDone
    ) {
    }
}
