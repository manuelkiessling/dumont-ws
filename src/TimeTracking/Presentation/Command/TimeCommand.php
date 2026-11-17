<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Command;

use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Opens the time tracking in the terminal and keeps it open until the person quits. Timers go on running.
 */
#[AsCommand(name: 'app:time', description: 'Opens the time tracking')]
final readonly class TimeCommand
{
    public function __construct(
        private TimeTrackingScreen $screen
    ) {
    }

    public function __invoke(): int
    {
        $tui = TimeTrackingScreen::createTui();
        $this->screen->mount($tui);
        $tui->run();

        return Command::SUCCESS;
    }
}
