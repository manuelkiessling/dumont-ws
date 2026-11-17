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

        $rows = json_decode((string) file_get_contents($this->todoItemsFile), true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            fn (array $row): TodoItem => new TodoItem(
                $row['id'],
                $row['text'],
                $this->date($row['addedAt']),
                $row['doneAt'] === null ? null : $this->date($row['doneAt'])
            ),
            $rows
        );
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
        // Replaced in one step, so that another terminal never reads half of the file
        $temp = $this->todoItemsFile . '.' . getmypid() . '.tmp';
        file_put_contents($temp, $json . "\n");
        rename($temp, $this->todoItemsFile);
    }

    private function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value)
            ?: throw new RuntimeException("Not a date of the todo list: {$value}");
    }
}
