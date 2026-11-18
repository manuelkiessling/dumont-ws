<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service;

final class Things
{
    /** @var list<string> */
    private array $names = [];

    /** The names of the things. @return list<string> */
    public function names(): array
    {
        return $this->names;
    }

    /** @param list<string> $names @return list<string> */
    public function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    /**
     * Keeps the names, as {@see Things::names()} returns them.
     *
     * @param list<string> $names
     */
    public function keep(array $names): void
    {
        /** @var list<string> $copy */
        $copy        = $names;
        $this->names = $copy;
    }

    /**
     * A tag named in prose, as the `@return` tag, is no tag.
     */
    public function count(): int
    {
        return count($this->names);
    }

    public function __construct(
        /** The things' owner. @var non-empty-string */
        private string $owner = 'me'
    ) {
    }

    public function owner(): string
    {
        return $this->owner;
    }
}
