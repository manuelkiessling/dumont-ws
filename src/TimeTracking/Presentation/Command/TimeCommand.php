<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Command;

use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use Revolt\EventLoop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Opens the time tracking screen and keeps it open until the person quits.
 */
#[AsCommand(name: 'app:time', description: 'Opens the time tracking')]
final readonly class TimeCommand
{
    private const int REFRESH_SECONDS = 1;

    public function __construct(
        private TimeTrackingScreen $screen
    ) {
    }

    public function __invoke(): int
    {
        $tui = TimeTrackingScreen::createTui();
        $this->screen->mount($tui);

        $refresh = EventLoop::repeat(self::REFRESH_SECONDS, $this->screen->refresh(...));
        try {
            $tui->run();
        } finally {
            EventLoop::cancel($refresh);
        }

        return Command::SUCCESS;
    }
}
