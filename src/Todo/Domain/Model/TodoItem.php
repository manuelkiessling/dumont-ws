<?php

declare(strict_types=1);

namespace App\Todo\Domain\Model;

use DateTimeImmutable;

final readonly class TodoItem
{
    public function __construct(
        public string             $id,
        public string             $text,
        public DateTimeImmutable  $addedAt,
        public ?DateTimeImmutable $doneAt = null
    ) {
    }

    public function isDone(): bool
    {
        return $this->doneAt !== null;
    }

    public function markedDoneAt(DateTimeImmutable $doneAt): self
    {
        return new self($this->id, $this->text, $this->addedAt, $doneAt);
    }
}
