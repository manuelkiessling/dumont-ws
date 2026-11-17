<?php

declare(strict_types=1);

namespace App\Tests\Integration\Todo;

use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoItemStoreInterface;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The store the application is wired with keeps its items in a file, and reads them back as they were.
 */
final class JsonFileTodoItemStoreTest extends KernelTestCase
{
    private TodoItemStoreInterface $store;

    protected function setUp(): void
    {
        $file = self::getContainer()->getParameter('app.todo_items_file');
        self::assertIsString($file);
        if (is_file($file)) {
            unlink($file);
        }

        $store = self::getContainer()->get(TodoItemStoreInterface::class);
        self::assertInstanceOf(TodoItemStoreInterface::class, $store);
        $this->store = $store;
    }

    public function testWithoutAFileTheListIsEmpty(): void
    {
        self::assertSame([], $this->store->load());
    }

    public function testStoredItemsAreReadBackAsTheyWere(): void
    {
        $items = [
            new TodoItem('0195f0a2-5c3b-7d4e-8f00-123456789abc', 'Buy milk', new DateTimeImmutable('2026-11-17 09:00:00.123456')),
            new TodoItem(
                '0195f0a2-5c3b-7d4e-8f00-123456789abd',
                'Grüße an Oma',
                new DateTimeImmutable('2026-11-17 09:01:00'),
                new DateTimeImmutable('2026-11-17 09:02:00.5')
            ),
        ];

        $this->store->store($items);

        self::assertEquals($items, $this->store->load());
    }
}
