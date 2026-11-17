<?php

declare(strict_types=1);

namespace App\Tests\Unit\Todo;

use App\Common\Domain\Service\DateAndTimeService;
use App\Todo\Domain\Exception\InvalidItemTextException;
use App\Todo\Domain\Exception\ItemNotOnTheListException;
use App\Todo\Domain\Service\TodoListDomainService;
use App\Todo\Facade\SymfonyEvent\TodoItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\TodoItemMarkedDoneSymfonyEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TodoListDomainServiceTest extends TestCase
{
    private MockClock $clock;
    private EventDispatcher $events;
    private TodoListDomainService $todoList;

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $this->events = new EventDispatcher();
        foreach ([TodoItemMarkedDoneSymfonyEvent::class, TodoItemDeletedSymfonyEvent::class] as $eventClass) {
            $this->events->addListener($eventClass, function (object $event): void {
                $this->dispatched[] = $event;
            });
        }

        $this->todoList = new TodoListDomainService(new InMemoryTodoItemStore(), $this->events);
    }

    public function testAnAddedItemIsOpenAndTrimmed(): void
    {
        $item = $this->todoList->addItem('  Buy milk  ');

        self::assertSame('Buy milk', $item->text);
        self::assertFalse($item->isDone());
        self::assertEquals(DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'), $item->addedAt);
        self::assertEquals([$item], $this->todoList->items());
    }

    public function testItemsKeepTheOrderTheyWereAddedIn(): void
    {
        $this->todoList->addItem('First');
        $this->todoList->addItem('Second');

        self::assertSame(['First', 'Second'], array_map(static fn ($item) => $item->text, $this->todoList->items()));
    }

    public function testAnEmptyTextIsRefused(): void
    {
        $this->expectException(InvalidItemTextException::class);

        $this->todoList->addItem('   ');
    }

    public function testATextOfMoreThanOneLineIsRefused(): void
    {
        $this->expectException(InvalidItemTextException::class);

        $this->todoList->addItem("First line\nsecond line");
    }

    public function testATextOfMaximumLengthIsAcceptedAndOneMoreCharacterIsRefused(): void
    {
        $this->todoList->addItem(str_repeat('ä', TodoListDomainService::MAX_TEXT_LENGTH));

        $this->expectException(InvalidItemTextException::class);

        $this->todoList->addItem(str_repeat('ä', TodoListDomainService::MAX_TEXT_LENGTH + 1));
    }

    public function testMarkingAnItemDoneRecordsWhen(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->clock->sleep(60);

        $this->todoList->markItemDone($item->id);

        $done = $this->todoList->items()[0];
        self::assertTrue($done->isDone());
        self::assertEquals(DateAndTimeService::getDateTimeImmutable('2026-11-17 09:01:00'), $done->doneAt);
    }

    public function testMarkingADoneItemDoneAgainChangesNothing(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->markItemDone($item->id);
        $this->clock->sleep(60);

        $this->todoList->markItemDone($item->id);

        self::assertEquals(DateAndTimeService::getDateTimeImmutable('2026-11-17 09:00:00'), $this->todoList->items()[0]->doneAt);
    }

    public function testADeletedItemIsGone(): void
    {
        $kept    = $this->todoList->addItem('Keep me');
        $deleted = $this->todoList->addItem('Delete me');

        $this->todoList->deleteItem($deleted->id);

        self::assertEquals([$kept], $this->todoList->items());
    }

    public function testAnItemNotOnTheListCanNeitherBeMarkedDoneNorDeleted(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->deleteItem($item->id);

        try {
            $this->todoList->markItemDone($item->id);
            self::fail('Marking a deleted item done must be refused.');
        } catch (ItemNotOnTheListException) {
        }

        $this->expectException(ItemNotOnTheListException::class);

        $this->todoList->deleteItem($item->id);
    }

    public function testMarkingDoneAndDeletingAreAnnounced(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->todoList->markItemDone($item->id);
        $this->todoList->markItemDone($item->id);
        $this->todoList->deleteItem($item->id);

        self::assertEquals(
            [new TodoItemMarkedDoneSymfonyEvent($item->id), new TodoItemDeletedSymfonyEvent($item->id)],
            $this->dispatched
        );
    }
}
