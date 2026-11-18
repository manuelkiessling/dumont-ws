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
 * Drives the screen as a person would, key by key, on a virtual terminal, and reads what the screen shows.
 */
final class TimeTrackingScreenTest extends TestCase
{
    private const string ENTER  = "\r";
    private const string DOWN   = "\x1b[B";
    private const string ESCAPE = "\x1b";
    private const string CTRL_C = "\x03";

    private MockClock $clock;
    private FakeTodoListFacade $todoList;
    private VirtualTerminal $terminal;
    private TimeTrackingScreen $screen;
    private Tui $tui;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $this->todoList = new FakeTodoListFacade();
        $this->todoList->add('a', 'Write report');
        $this->todoList->add('b', 'Call mum');

        $this->terminal = new VirtualTerminal(120, 24);
        $this->tui      = TimeTrackingScreen::createTui($this->terminal);
        $this->screen   = new TimeTrackingScreen(
            new TimeTrackingDomainService(new InMemoryTimePeriodStore(), $this->todoList)
        );
        $this->screen->mount($this->tui);
        $this->tui->start();
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testItemsAreShownWithTheirTime(): void
    {
        $screen = $this->screen();

        self::assertStringContainsString('[ ] Write report  0:00:00', $screen);
        self::assertStringContainsString('[ ] Call mum  0:00:00', $screen);
    }

    public function testAStartedTimerCountsUpLiveAndShowsWhenItWasStarted(): void
    {
        $this->clock->sleep(60);
        $this->press(self::ENTER);

        self::assertStringContainsString(
            'Write report  0:00:00  ▶ running, started 2026-11-17 09:01:00',
            $this->screen()
        );

        $this->clock->sleep(3725);
        $this->screen->refresh();

        self::assertStringContainsString('Write report  1:02:05  ▶ running', $this->screen());
    }

    public function testStartingATimerOnAnotherItemStopsTheFirst(): void
    {
        $this->press(self::ENTER);
        $this->clock->sleep(10);
        $this->press(self::DOWN, self::ENTER);
        $this->clock->sleep(5);
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Write report  0:00:10', $screen);
        self::assertStringNotContainsString('Write report  0:00:10  ▶', $screen);
        self::assertStringContainsString('Call mum  0:00:05  ▶ running', $screen);
    }

    public function testStoppingKeepsTheTimeAndStopsCountingUp(): void
    {
        $this->press(self::ENTER);
        $this->clock->sleep(7);
        $this->press('s');
        $this->clock->sleep(100);
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('Write report  0:00:07', $screen);
        self::assertStringNotContainsString('running', $screen);
    }

    public function testATimerOnADoneItemIsRefusedWithTheReason(): void
    {
        $this->todoList->markDone('a');
        $this->screen->refresh();

        $this->press(self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('This item is done, so no timer can be started on it.', $screen);
        self::assertStringContainsString('[x] Write report  0:00:00', $screen);
        self::assertStringNotContainsString('running', $screen);
    }

    public function testChangesOfTheTodoListAppearOnRefresh(): void
    {
        $this->todoList->add('c', 'Water plants');
        $this->todoList->delete('b');
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('Water plants', $screen);
        self::assertStringNotContainsString('Call mum', $screen);
    }

    public function testAnEmptyTodoListSaysSoAndEscapeStillQuits(): void
    {
        $this->todoList->delete('a');
        $this->todoList->delete('b');
        $this->screen->refresh();

        self::assertStringContainsString('Nothing to track', $this->screen());

        $this->press(self::ESCAPE);

        self::assertFalse($this->tui->isRunning());
    }

    public function testEscapeQuits(): void
    {
        $this->press(self::ESCAPE);

        self::assertFalse($this->tui->isRunning());
    }

    public function testCtrlCQuits(): void
    {
        $this->press(self::CTRL_C);

        self::assertFalse($this->tui->isRunning());
    }

    private function press(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->terminal->simulateInput($key);
        }
    }

    private function screen(): string
    {
        $this->tui->processRender();

        $screen = new ScreenBuffer($this->terminal->getColumns(), $this->terminal->getRows());
        $screen->write($this->terminal->getOutput());

        return $screen->getScreen();
    }
}
