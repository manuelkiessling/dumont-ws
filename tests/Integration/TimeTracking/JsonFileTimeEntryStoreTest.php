<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Service\TimeEntryStoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The store the application is wired with keeps its entries in a file, and reads them back as they were.
 */
final class JsonFileTimeEntryStoreTest extends KernelTestCase
{
    private TimeEntryStoreInterface $store;

    protected function setUp(): void
    {
        $file = self::getContainer()->getParameter('app.time_entries_file');
        self::assertIsString($file);
        if (is_file($file)) {
            unlink($file);
        }

        $store = self::getContainer()->get(TimeEntryStoreInterface::class);
        self::assertInstanceOf(TimeEntryStoreInterface::class, $store);
        $this->store = $store;
    }

    public function testWithoutAFileThereAreNoEntries(): void
    {
        self::assertSame([], $this->store->load());
    }

    public function testStoredEntriesAreReadBackAsTheyWere(): void
    {
        $entries = [
            new TimeEntry(
                '0195f0a2-5c3b-7d4e-8f00-00000000000a',
                '0195f0a2-5c3b-7d4e-8f00-000000000001',
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00.123456'),
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:30:00')
            ),
            new TimeEntry(
                '0195f0a2-5c3b-7d4e-8f00-00000000000b',
                '0195f0a2-5c3b-7d4e-8f00-000000000002',
                DateAndTimeService::getDateTimeImmutable('2026-11-17 09:30:00')
            ),
        ];

        $this->store->store($entries);

        self::assertEquals($entries, $this->store->load());
    }
}
