<?php

declare(strict_types=1);

namespace App\Todo\Domain\Service;


interface TodoItemStoreInterface
{
    public function load(): array;

    public function store(array $items): void;
}
