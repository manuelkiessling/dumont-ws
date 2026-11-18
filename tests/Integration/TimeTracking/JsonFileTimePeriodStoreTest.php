<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Model\TimePeriod;
use App\TimeTracking\Domain\Service\TimePeriodStoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The store the application is wired with keeps its periods in a file, and reads them back as they were.
 */
final class JsonFileTimePeriodStoreTest extends KernelTestCase
{
    private TimePeriodStoreInterface $store;

    protected function setUp(): void
    {
        $file = self::getContainer()->getParameter('app.time_periods_file');
        self::assertIsString($file);
        if (is_file($file)) {
            unlink($file);
        }

        $store = self::getContainer()->get(TimePeriodStoreInterface::class);
        self::assertInstanceOf(TimePeriodStoreInterface::class, $store);
        $this->store = $store;
    }

    public function testWithoutAFileThereAreNoPeriods(): void
    {
        self::assertSame([], $this->store->load());
    }

    public function testStoredPeriodsAreReadBackAsTheyWere(): void
    {
        $periods = [
            new TimePeriod(
                '0195f0a2-5c3b-7d4e-8f00-123456789abc',
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'),
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:01:05')
            ),
            new TimePeriod(
                '0195f0a2-5c3b-7d4e-8f00-123456789abd',
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:02:00')
            ),
        ];

        $this->store->store($periods);

        self::assertEquals($periods, $this->store->load());
    }
}
