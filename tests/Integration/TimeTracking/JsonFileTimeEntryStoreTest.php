<?php

declare(strict_types=1);

namespace App\Tests\Integration\TimeTracking;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Model\TimeEntry;
use App\TimeTracking\Domain\Service\TimeEntryStoreInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
            new TimeEntry('0195f0a2-5c3b-7d4e-8f00-123456789abc', 'Grüße an Oma', DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'), DateAndTimeService::getDateTimeImmutable('2026-11-17 09:01:05')),
            new TimeEntry('0195f0a2-5c3b-7d4e-8f00-123456789abd', 'Buy milk', DateAndTimeService::getDateTimeImmutable('2026-11-17 09:02:00')),
        ];

        $this->store->store($entries);

        self::assertEquals($entries, $this->store->load());
    }
}
