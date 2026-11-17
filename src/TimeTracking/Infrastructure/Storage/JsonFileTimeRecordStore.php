<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Storage;

use App\TimeTracking\Domain\Model\ItemTime;
use App\TimeTracking\Domain\Model\Period;
use App\TimeTracking\Domain\Service\TimeRecordStoreInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Keeps the time spent on items in one JSON file, readable by hand. Dates are kept to the second.
 */
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

        $rows = json_decode(
            (string) file_get_contents($this->timeRecordsFile),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException("The file {$this->timeRecordsFile} holds no list of times.");
        }

        return array_map($this->time(...), $rows);
    }

    public function store(array $times): void
    {
        $rows = array_map(
            fn (ItemTime $time): array => [
                'id'      => $time->itemId,
                'text'    => $time->text,
                'periods' => array_map(
                    fn (Period $period): array => [
                        'startedAt' => $period->startedAt->format(self::DATE_FORMAT),
                        'stoppedAt' => $period->stoppedAt?->format(self::DATE_FORMAT),
                    ],
                    $time->periods
                ),
            ],
            $times
        );

        $directory = dirname($this->timeRecordsFile);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the directory {$directory}.");
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($this->timeRecordsFile, $json . "\n", LOCK_EX);
    }

    private function time(mixed $row): ItemTime
    {
        $id      = is_array($row) ? $row['id']      ?? null : null;
        $text    = is_array($row) ? $row['text']    ?? null : null;
        $periods = is_array($row) ? $row['periods'] ?? null : null;

        if (!is_string($id) || !is_string($text) || !is_array($periods) || !array_is_list($periods)) {
            throw new RuntimeException("The file {$this->timeRecordsFile} holds a time that is not one of an item.");
        }

        return new ItemTime($id, $text, array_map($this->period(...), $periods));
    }

    private function period(mixed $row): Period
    {
        $startedAt = is_array($row) ? $row['startedAt'] ?? null : null;
        $stoppedAt = is_array($row) ? $row['stoppedAt'] ?? null : null;

        if (!is_string($startedAt) || !(is_string($stoppedAt) || $stoppedAt === null)) {
            throw new RuntimeException("The file {$this->timeRecordsFile} holds a period that is none.");
        }

        return new Period($this->date($startedAt), $stoppedAt === null ? null : $this->date($stoppedAt));
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the time tracking: {$value}");
    }
}
