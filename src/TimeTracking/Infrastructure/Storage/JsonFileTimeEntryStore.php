<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Storage;

use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Service\TimeEntryStoreInterface;
use DateTimeImmutable;
use RuntimeException;

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

        $rows = json_decode((string) file_get_contents($this->timeEntriesFile), true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            fn (array $row): TimeEntry => new TimeEntry(
                $row['itemId'],
                $row['itemText'],
                $this->date($row['startedAt']),
                $row['stoppedAt'] === null ? null : $this->date($row['stoppedAt'])
            ),
            $rows
        );
    }

    public function store(array $entries): void
    {
        $rows = array_map(
            fn (TimeEntry $entry): array => [
                'itemId'    => $entry->itemId,
                'itemText'  => $entry->itemText,
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

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the time tracking: {$value}");
    }
}
