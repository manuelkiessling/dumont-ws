<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

/**
 * Drives the screen as a person would, on a virtual terminal, and reads what it shows.
 */
final class TimeTrackingScreenTest extends TestCase
{
    private const string ENTER  = "\r";
    private const string DOWN   = "\x1b[B";
    private const string ESCAPE = "\x1b";
    private const string CTRL_C = "\x03";

    private MockClock $clock;
    private StandInTodoListFacade $todoList;
    private TimeTrackingScreen $timeScreen;
    private VirtualTerminal $terminal;
    private Tui $tui;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $this->todoList = new StandInTodoListFacade();
        $this->todoList->add('a', 'Buy milk');
        $this->todoList->add('b', 'Call mum');

        $this->terminal   = new VirtualTerminal(110, 24);
        $this->tui        = TimeTrackingScreen::createTui($this->terminal);
        $this->timeScreen = new TimeTrackingScreen(
            new TimeTrackingDomainService(new InMemoryTimeEntryStore(), $this->todoList)
        );
        $this->timeScreen->mount($this->tui);
        $this->tui->start();
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testTheItemsShowWithNoTimeSpent(): void
    {
        $screen = $this->screen();

        self::assertStringContainsString('Time tracking', $screen);
        self::assertStringContainsString('[ ] Buy milk  0:00:00', $screen);
        self::assertStringContainsString('[ ] Call mum  0:00:00', $screen);
    }

    public function testAStartedTimerCountsUpLiveAndShowsWhenItStarted(): void
    {
        $this->clock->sleep(4000);
        $this->terminal->simulateInput(self::ENTER);
        self::assertStringContainsString(
            '[>] Buy milk  0:00:00  (running since 2026-11-17 10:06:40 UTC)',
            $this->screen()
        );

        $this->clock->sleep(3725);
        $this->timeScreen->refresh();

        self::assertStringContainsString(
            '[>] Buy milk  1:02:05  (running since 2026-11-17 10:06:40 UTC)',
            $this->screen()
        );
    }

    public function testEnterOnARunningItemStopsItsTimer(): void
    {
        $this->terminal->simulateInput(self::ENTER);
        $this->clock->sleep(75);
        $this->terminal->simulateInput(self::ENTER);
        $this->clock->sleep(500);
        $this->timeScreen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Buy milk  0:01:15', $screen);
        self::assertStringNotContainsString('running since', $screen);
    }

    public function testStartingATimerOnAnotherItemStopsTheFirst(): void
    {
        $this->terminal->simulateInput(self::ENTER);
        $this->clock->sleep(10);
        $this->terminal->simulateInput(self::DOWN);
        $this->terminal->simulateInput(self::ENTER);
        $this->clock->sleep(5);
        $this->timeScreen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Buy milk  0:00:10', $screen);
        self::assertStringContainsString('[>] Call mum  0:00:05', $screen);
    }

    public function testATimerOnADoneItemIsRefusedWithTheReason(): void
    {
        $this->todoList->markDone('a');
        $this->timeScreen->refresh();

        $this->terminal->simulateInput(self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('This item is done, so no timer can be started on it.', $screen);
        self::assertStringContainsString('[x] Buy milk  0:00:00', $screen);
    }

    public function testChangesOfTheTodoListShowWithoutReopening(): void
    {
        $this->todoList->add('c', 'Water plants');
        $this->todoList->remove('b');
        $this->timeScreen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('Water plants', $screen);
        self::assertStringNotContainsString('Call mum', $screen);
    }

    public function testAnEmptyTodoListSaysSo(): void
    {
        $this->todoList->remove('a');
        $this->todoList->remove('b');
        $this->timeScreen->refresh();

        self::assertStringContainsString('Nothing to track', $this->screen());
    }

    public function testEscapeAndCtrlCQuit(): void
    {
        $this->terminal->simulateInput(self::ESCAPE);
        self::assertFalse($this->tui->isRunning());
    }

    public function testCtrlCQuits(): void
    {
        $this->terminal->simulateInput(self::CTRL_C);
        self::assertFalse($this->tui->isRunning());
    }

    private function screen(): string
    {
        $this->tui->processRender();

        $screen = new ScreenBuffer($this->terminal->getColumns(), $this->terminal->getRows());
        $screen->write($this->terminal->getOutput());

        return $screen->getScreen();
    }
}
