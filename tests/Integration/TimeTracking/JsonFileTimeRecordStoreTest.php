<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Model\ItemTime;
use App\TimeTracking\Domain\Model\Period;
use App\TimeTracking\Domain\Service\TimeRecordStoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The store the application is wired with keeps the time spent in a file, and reads it back as it was.
 */
final class JsonFileTimeRecordStoreTest extends KernelTestCase
{
    private TimeRecordStoreInterface $store;

    protected function setUp(): void
    {
        $file = self::getContainer()->getParameter('app.time_records_file');
        self::assertIsString($file);
        if (is_file($file)) {
            unlink($file);
        }

        $store = self::getContainer()->get(TimeRecordStoreInterface::class);
        self::assertInstanceOf(TimeRecordStoreInterface::class, $store);
        $this->store = $store;
    }

    public function testWithoutAFileNoTimeIsKept(): void
    {
        self::assertSame([], $this->store->load());
    }

    public function testStoredTimesAreReadBackAsTheyWere(): void
    {
        $times = [
            new ItemTime('0195f0a2-5c3b-7d4e-8f00-123456789abc', 'Grüße an Oma', [
                new Period(
                    DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'),
                    DateAndTimeService::getDateTimeImmutable('2026-11-17 09:01:05')
                ),
                new Period(DateAndTimeService::getDateTimeImmutable('2026-11-17 10:00:00')),
            ]),
            new ItemTime('0195f0a2-5c3b-7d4e-8f00-123456789abd', 'Buy milk'),
        ];

        $this->store->store($times);

        self::assertEquals($times, $this->store->load());
    }
}
