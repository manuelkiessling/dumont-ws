<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Storage;

use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Service\TimeEntryStoreInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Keeps the periods the timers ran in one JSON file, readable by hand.
 */
final readonly class JsonFileTimeEntryStore implements TimeEntryStoreInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:sP';

    public function __construct(
        private string $timeEntriesFile
    ) {
    }

    public function load(): array
    {
        if (!is_file($this->timeEntriesFile)) {
            return [];
        }

        $rows = json_decode(
            (string) file_get_contents($this->timeEntriesFile),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException("The file {$this->timeEntriesFile} holds no list of time entries.");
        }

        return array_map($this->entry(...), $rows);
    }

    public function store(array $entries): void
    {
        $rows = array_map(
            fn (TimeEntry $entry): array => [
                'itemId'    => $entry->itemId,
                'startedAt' => $entry->startedAt->format(self::DATE_FORMAT),
                'stoppedAt' => $entry->stoppedAt?->format(self::DATE_FORMAT),
            ],
            $entries
        );

        $directory = dirname($this->timeEntriesFile);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the directory {$directory}.");
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($this->timeEntriesFile, $json . "\n", LOCK_EX);
    }

    private function entry(mixed $row): TimeEntry
    {
        $itemId    = is_array($row) ? $row['itemId']    ?? null : null;
        $startedAt = is_array($row) ? $row['startedAt'] ?? null : null;
        $stoppedAt = is_array($row) ? $row['stoppedAt'] ?? null : null;

        if (!is_string($itemId) || !is_string($startedAt) || !(is_string($stoppedAt) || $stoppedAt === null)) {
            throw new RuntimeException("The file {$this->timeEntriesFile} holds an entry that is not one of the time tracking.");
        }

        return new TimeEntry(
            $itemId,
            $this->date($startedAt),
            $stoppedAt === null ? null : $this->date($stoppedAt)
        );
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the time tracking: {$value}");
    }
}
