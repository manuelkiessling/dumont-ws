<?php

declare(strict_types=1);

namespace App\Todo\Infrastructure\Storage;

use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoItemStoreInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * Keeps the todo list's items in one JSON file, readable by hand.
 */
final readonly class JsonFileTodoItemStore implements TodoItemStoreInterface
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public function __construct(
        private string $todoItemsFile
    ) {
    }

    public function load(): array
    {
        if (!is_file($this->todoItemsFile)) {
            return [];
        }

        $rows = json_decode(
            (string) file_get_contents($this->todoItemsFile),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($rows) || !array_is_list($rows)) {
            throw new RuntimeException("The file {$this->todoItemsFile} holds no list of todo items.");
        }

        return array_map($this->item(...), $rows);
    }

    public function store(array $items): void
    {
        $rows = array_map(
            fn (TodoItem $item): array => [
                'id'      => $item->id,
                'text'    => $item->text,
                'addedAt' => $item->addedAt->format(self::DATE_FORMAT),
                'doneAt'  => $item->doneAt?->format(self::DATE_FORMAT),
            ],
            $items
        );

        $directory = dirname($this->todoItemsFile);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the directory {$directory}.");
        }

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($this->todoItemsFile, $json . "\n", LOCK_EX);
    }

    private function item(mixed $row): TodoItem
    {
        $id      = is_array($row) ? $row['id']      ?? null : null;
        $text    = is_array($row) ? $row['text']    ?? null : null;
        $addedAt = is_array($row) ? $row['addedAt'] ?? null : null;
        $doneAt  = is_array($row) ? $row['doneAt']  ?? null : null;

        if (!is_string($id) || !is_string($text) || !is_string($addedAt) || !(is_string($doneAt) || $doneAt === null)) {
            throw new RuntimeException("The file {$this->todoItemsFile} holds an item that is not one of the todo list.");
        }

        return new TodoItem(
            $id,
            $text,
            $this->date($addedAt),
            $doneAt === null ? null : $this->date($doneAt)
        );
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the todo list: {$value}");
    }
}
