<?php

declare(strict_types=1);

namespace App\Tests\Unit\Todo;

use App\Common\Domain\Service\DateAndTimeService;
use App\Todo\Domain\Exception\InvalidItemTextException;
use App\Todo\Domain\Exception\ItemNotOnTheListException;
use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoListDomainService;
use App\Todo\Facade\SymfonyEvent\ItemDeletedSymfonyEvent;
use App\Todo\Facade\SymfonyEvent\ItemMarkedDoneSymfonyEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TodoListDomainServiceTest extends TestCase
{
    private TodoListDomainService $todoList;

    /** @var list<object> */
    private array $announced = [];

    protected function setUp(): void
    {
        $events = new EventDispatcher();
        $events->addListener(ItemMarkedDoneSymfonyEvent::class, function (object $event): void {
            $this->announced[] = $event;
        });
        $events->addListener(ItemDeletedSymfonyEvent::class, function (object $event): void {
            $this->announced[] = $event;
        });

        $this->todoList = new TodoListDomainService(new InMemoryTodoItemStore(), $events);
    }

    public function testAnAddedItemIsOpenAndTrimmed(): void
    {
        $before = DateAndTimeService::getDateTimeImmutable();
        $item   = $this->todoList->addItem('  Buy milk  ');
        $after  = DateAndTimeService::getDateTimeImmutable();

        self::assertSame('Buy milk', $item->text);
        self::assertFalse($item->isDone());
        self::assertGreaterThanOrEqual($before, $item->addedAt);
        self::assertLessThanOrEqual($after, $item->addedAt);
        self::assertEquals([$item], $this->todoList->items());
    }

    public function testItemsKeepTheOrderTheyWereAddedIn(): void
    {
        $this->todoList->addItem('First');
        $this->todoList->addItem('Second');

        self::assertSame(
            ['First', 'Second'],
            array_map(static fn (TodoItem $item): string => $item->text, $this->todoList->items())
        );
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

        $this->todoList->markItemDone($item->id);

        $done = $this->todoList->items()[0];
        self::assertTrue($done->isDone());
        self::assertNotNull($done->doneAt);
        self::assertGreaterThanOrEqual($item->addedAt, $done->doneAt);
    }

    public function testMarkingADoneItemDoneAgainChangesNothing(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->markItemDone($item->id);
        $firstDoneAt = $this->todoList->items()[0]->doneAt;

        $this->todoList->markItemDone($item->id);

        self::assertEquals($firstDoneAt, $this->todoList->items()[0]->doneAt);
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

    public function testMarkingAnItemDoneAnnouncesItOnce(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->todoList->markItemDone($item->id);
        $this->todoList->markItemDone($item->id);

        self::assertEquals([new ItemMarkedDoneSymfonyEvent($item->id)], $this->announced);
    }

    public function testDeletingAnItemAnnouncesIt(): void
    {
        $item = $this->todoList->addItem('Buy milk');

        $this->todoList->deleteItem($item->id);

        self::assertEquals([new ItemDeletedSymfonyEvent($item->id)], $this->announced);
    }
}
