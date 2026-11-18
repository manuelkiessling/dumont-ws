<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Storage;

use App\TimeTracking\Domain\Model\TimeRecord;
use App\TimeTracking\Domain\Service\TimeRecordStoreInterface;
use DateTimeImmutable;
use RuntimeException;

final readonly class JsonFileTimeRecordStore implements TimeRecordStoreInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:sP';

    public function __construct(
        private string $timeRecordsFile
    ) {
    }

    public function load(): array
    {
        if (!is_file($this->timeRecordsFile)) {
            return [];
        }

        $rows = json_decode((string) file_get_contents($this->timeRecordsFile), true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            fn (array $row): TimeRecord => new TimeRecord(
                $row['itemId'],
                $row['itemText'],
                array_map(
                    fn (array $period): array => [
                        'startedAt' => $this->date($period['startedAt']),
                        'stoppedAt' => $this->date($period['stoppedAt']),
                    ],
                    $row['periods']
                ),
                $row['runningSince'] === null ? null : $this->date($row['runningSince'])
            ),
            $rows
        );
    }

    public function store(array $records): void
    {
        $rows = array_map(
            fn (TimeRecord $record): array => [
                'itemId'       => $record->itemId,
                'itemText'     => $record->itemText,
                'periods'      => array_map(
                    fn (array $period): array => [
                        'startedAt' => $period['startedAt']->format(self::DATE_FORMAT),
                        'stoppedAt' => $period['stoppedAt']->format(self::DATE_FORMAT),
                    ],
                    $record->periods
                ),
                'runningSince' => $record->runningSince?->format(self::DATE_FORMAT),
            ],
            $records
        );

        $directory = dirname($this->timeRecordsFile);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the directory {$directory}.");
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($this->timeRecordsFile, $json . "\n", LOCK_EX);
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the time tracking: {$value}");
    }
}
