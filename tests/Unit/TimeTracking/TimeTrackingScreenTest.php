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

    private FakeTodoItems $todoItems;
    private MockClock $clock;
    private TimeTrackingScreen $screen;
    private VirtualTerminal $terminal;
    private Tui $tui;

    protected function setUp(): void
    {
        $this->todoItems = new FakeTodoItems();
        $this->todoItems->add('0195f0a2-5c3b-7d4e-8f00-000000000001', 'Buy milk');
        $this->todoItems->add('0195f0a2-5c3b-7d4e-8f00-000000000002', 'Water plants', true);

        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $this->screen = new TimeTrackingScreen(
            new TimeTrackingDomainService(new InMemoryTimeEntryStore(), $this->todoItems),
            $this->todoItems
        );

        $this->terminal = new VirtualTerminal(100, 24);
        $this->tui      = TimeTrackingScreen::createTui($this->terminal);
        $this->screen->mount($this->tui);
        $this->tui->start();
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testTheItemsOfTheTodoListAreShownWithTheirTime(): void
    {
        $screen = $this->screen();

        self::assertStringContainsString('0h 00m 00s   Buy milk', $screen);
        self::assertStringContainsString('0h 00m 00s   Water plants   done', $screen);
    }

    public function testEnterStartsTheTimerAndEnterAgainStopsIt(): void
    {
        $this->clock->sleep(3);
        $this->press(self::ENTER);
        $this->clock->sleep(65 * 60 + 7);
        $this->screen->refresh();

        self::assertStringContainsString('▶  1h 05m 07s   Buy milk   running since 09:00:03', $this->screen());

        $this->press(self::ENTER);
        $this->clock->sleep(60 * 60);
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('1h 05m 07s   Buy milk', $screen);
        self::assertStringNotContainsString('running', $screen);
    }

    public function testADoneItemsTimerIsRefusedWithTheReason(): void
    {
        $this->press(self::DOWN, self::ENTER);

        self::assertStringContainsString('This item is done; its time can no longer be tracked.', $this->screen());
    }

    public function testARefreshShowsTheTodoListAsItIsNow(): void
    {
        $this->todoItems->add('0195f0a2-5c3b-7d4e-8f00-000000000003', 'Call mum');
        $this->todoItems->remove('0195f0a2-5c3b-7d4e-8f00-000000000001');

        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('Call mum', $screen);
        self::assertStringNotContainsString('Buy milk', $screen);
    }

    public function testAnEmptyTodoListSaysSo(): void
    {
        $this->todoItems->remove('0195f0a2-5c3b-7d4e-8f00-000000000001');
        $this->todoItems->remove('0195f0a2-5c3b-7d4e-8f00-000000000002');

        $this->screen->refresh();

        self::assertStringContainsString('Nothing on the todo list.', $this->screen());
    }

    public function testEscapeQuits(): void
    {
        $this->press(self::ESCAPE);

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
