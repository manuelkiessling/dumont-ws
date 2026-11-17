<?php

declare(strict_types=1);

namespace App\TimeTracking\Infrastructure\Storage;

use App\TimeTracking\Domain\Model\TimePeriod;
use App\TimeTracking\Domain\Service\TimePeriodStoreInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Keeps the time periods in one JSON file, readable by hand. The file is replaced in one step, so that another
 * terminal never reads half of it.
 */
final readonly class JsonFileTimePeriodStore implements TimePeriodStoreInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(
        private string $timePeriodsFile
    ) {
    }

    public function load(): array
    {
        if (!is_file($this->timePeriodsFile)) {
            return [];
        }

        $rows = json_decode((string) file_get_contents($this->timePeriodsFile), true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            fn (array $row): TimePeriod => new TimePeriod(
                $row['itemId'],
                $this->date($row['startedAt']),
                $row['stoppedAt'] === null ? null : $this->date($row['stoppedAt'])
            ),
            $rows
        );
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

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $temp = $this->timePeriodsFile . '.' . getmypid() . '.tmp';
        file_put_contents($temp, $json . "\n");
        rename($temp, $this->timePeriodsFile);
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the time tracking: {$value}");
    }
}
