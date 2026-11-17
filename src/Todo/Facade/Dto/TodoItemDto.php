<?php

declare(strict_types=1);

namespace App\Todo\Facade\Dto;

final readonly class TodoItemDto
{
    public function __construct(
        public string $itemId,
        public string $text,
        public bool   $isDone
    ) {
    }
}
