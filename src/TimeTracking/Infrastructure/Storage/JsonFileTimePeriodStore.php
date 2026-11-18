<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Storage;

use App\TimeTracking\Domain\Model\TimePeriod;
use App\TimeTracking\Domain\Service\TimePeriodStoreInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Keeps the periods the timers ran in one JSON file, readable by hand.
 */
final readonly class JsonFileTimePeriodStore implements TimePeriodStoreInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:sP';

    public function __construct(
        private string $timePeriodsFile
    ) {
    }

    public function load(): array
    {
        if (!is_file($this->timePeriodsFile)) {
            return [];
        }

        $rows = json_decode(
            (string) file_get_contents($this->timePeriodsFile),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException("The file {$this->timePeriodsFile} holds no list of time periods.");
        }

        return array_map($this->period(...), $rows);
    }

    public function store(array $periods): void
    {
        $rows = array_map(
            fn (TimePeriod $period): array => [
                'itemId'    => $period->itemId,
                'startedAt' => $period->startedAt->format(self::DATE_FORMAT),
                'stoppedAt' => $period->stoppedAt?->format(self::DATE_FORMAT),
            ],
            $periods
        );

        $directory = dirname($this->timePeriodsFile);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the directory {$directory}.");
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($this->timePeriodsFile, $json . "\n", LOCK_EX);
    }

    private function period(mixed $row): TimePeriod
    {
        $itemId    = is_array($row) ? $row['itemId']    ?? null : null;
        $startedAt = is_array($row) ? $row['startedAt'] ?? null : null;
        $stoppedAt = is_array($row) ? $row['stoppedAt'] ?? null : null;

        if (!is_string($itemId) || !is_string($startedAt) || !(is_string($stoppedAt) || $stoppedAt === null)) {
            throw new RuntimeException("The file {$this->timePeriodsFile} holds a period that is not one of time tracking.");
        }

        return new TimePeriod(
            $itemId,
            $this->date($startedAt),
            $stoppedAt === null ? null : $this->date($stoppedAt)
        );
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of time tracking: {$value}");
    }
}
