<?php

declare(strict_types=1);

namespace App\Tests\Integration\Common;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Domain\Service\TodoListDomainService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * The application as the container wires it lives on the clock a test sets.
 */
final class MockClockTest extends KernelTestCase
{
    public function testTheApplicationFollowsTheMockClock(): void
    {
        $clock = new MockClock('2001-02-03 04:05:06');
        Clock::set($clock);

        foreach (['app.todo_items_file', 'app.time_entries_file'] as $parameter) {
            $file = self::getContainer()->getParameter($parameter);
            self::assertIsString($file);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $todoList     = self::getContainer()->get(TodoListDomainService::class);
        $timeTracking = self::getContainer()->get(TimeTrackingDomainService::class);
        self::assertInstanceOf(TodoListDomainService::class, $todoList);
        self::assertInstanceOf(TimeTrackingDomainService::class, $timeTracking);

        $item = $todoList->addItem('Buy milk');
        $timeTracking->startTimer($item->id);
        $clock->sleep(42);

        self::assertEquals(DateAndTimeService::getDateTimeImmutable('2001-02-03 04:05:06'), $item->addedAt);
        self::assertSame(42, $timeTracking->trackedSeconds($item->id));
    }
}
