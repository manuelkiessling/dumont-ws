<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Domain\Service\TimeEntryStoreInterface;

final class InMemoryTimeEntryStore implements TimeEntryStoreInterface
{
    private array $entries = [];

    public function load(): array
    {
        return $this->entries;
    }

    public function store(array $entries): void
    {
        $this->entries = $entries;
    }
}
