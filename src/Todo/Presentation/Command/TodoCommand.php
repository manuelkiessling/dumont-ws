<?php

declare(strict_types=1);

namespace App\Todo\Presentation\Command;

use App\Todo\Presentation\Tui\TodoListScreen;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Opens the todo list in the terminal and keeps it open until the person quits.
 */
#[AsCommand(name: 'app:todo', description: 'Opens the todo list')]
final readonly class TodoCommand
{
    public function __construct(
        private TodoListScreen $screen
    ) {
    }

    public function __invoke(): int
    {
        $tui = TodoListScreen::createTui();
        $this->screen->mount($tui);
        $tui->run();

        return Command::SUCCESS;
    }
}
