<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Command;

use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:time', description: 'Opens the time tracking')]
final readonly class TimeTrackingCommand
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
