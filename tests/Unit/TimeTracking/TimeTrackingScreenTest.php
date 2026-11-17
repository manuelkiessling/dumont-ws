<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use PHPUnit\Framework\TestCase;
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

    private TimeTrackingWorld $world;
    private TimeTrackingScreen $screen;
    private VirtualTerminal $terminal;
    private Tui $tui;

    protected function setUp(): void
    {
        $this->world = new TimeTrackingWorld();
        $this->open();
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testAnEmptyListSaysSo(): void
    {
        self::assertStringContainsString('Nothing to do.', $this->screenText());
    }

    public function testItemsShowWithNoTimeUntilATimerRuns(): void
    {
        $this->world->todoList->addItem('Buy milk');
        $this->world->passSeconds(3600);
        $this->screen->refresh();

        self::assertStringContainsString('[ ] Buy milk  00:00:00', $this->screenText());
    }

    public function testATimerStartsCountsUpLiveAndShowsWhenItWasStarted(): void
    {
        $this->world->todoList->addItem('Buy milk');
        $this->screen->refresh();

        $this->press(self::ENTER);
        self::assertStringContainsString(
            '[>] Buy milk  00:00:00  (running since 2026-11-17 09:00:00 UTC)',
            $this->screenText()
        );

        $this->world->passSeconds(3725);
        $this->screen->refresh();

        self::assertStringContainsString(
            '[>] Buy milk  01:02:05  (running since 2026-11-17 09:00:00 UTC)',
            $this->screenText()
        );
    }

    public function testEnterOnARunningTimerStopsItAndTheTimeStaysPut(): void
    {
        $this->world->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER);
        $this->world->passSeconds(90);
        $this->screen->refresh();

        $this->press(self::ENTER);
        $this->world->passSeconds(500);
        $this->screen->refresh();

        $screen = $this->screenText();
        self::assertStringContainsString('[ ] Buy milk  00:01:30', $screen);
        self::assertStringNotContainsString('running since', $screen);
    }

    public function testStartingATimerOnAnotherItemStopsTheFirst(): void
    {
        $this->world->todoList->addItem('Buy milk');
        $this->world->todoList->addItem('Call mum');
        $this->screen->refresh();
        $this->press(self::ENTER);
        $this->world->passSeconds(30);

        $this->press(self::DOWN, self::ENTER);
        $this->world->passSeconds(5);
        $this->screen->refresh();

        $screen = $this->screenText();
        self::assertStringContainsString('[ ] Buy milk  00:00:30', $screen);
        self::assertStringContainsString('[>] Call mum  00:00:05', $screen);
    }

    public function testATimerOnADoneItemIsRefusedWithTheReason(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->world->todoList->markItemDone($milk->id);
        $this->screen->refresh();

        $this->press(self::ENTER);

        $screen = $this->screenText();
        self::assertStringContainsString('This item is done, so no timer can be started on it.', $screen);
        self::assertStringContainsString('[x] Buy milk  00:00:00  (done)', $screen);
    }

    public function testChangesOnTheTodoListShowWithoutReopeningTheScreen(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER);
        $this->world->passSeconds(20);

        $this->world->todoList->addItem('Call mum');
        $this->world->todoList->markItemDone($milk->id);
        $this->screen->refresh();

        $screen = $this->screenText();
        self::assertStringContainsString('[x] Buy milk  00:00:20  (done)', $screen);
        self::assertStringContainsString('[ ] Call mum  00:00:00', $screen);
    }

    public function testADeletedItemKeepsItsTimeOnTheScreen(): void
    {
        $milk = $this->world->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER);
        $this->world->passSeconds(75);

        $this->world->todoList->deleteItem($milk->id);
        $this->screen->refresh();

        $screen = $this->screenText();
        self::assertStringContainsString('Deleted items', $screen);
        self::assertStringContainsString('00:01:15  Buy milk  (deleted)', $screen);
        self::assertStringContainsString('Nothing to do.', $screen);
    }

    public function testAReopenedScreenShowsTheTimerThatKeptRunning(): void
    {
        $this->world->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER);
        $this->tui->stop();

        $this->world->passSeconds(60);
        $this->open();

        self::assertStringContainsString(
            '[>] Buy milk  00:01:00  (running since 2026-11-17 09:00:00 UTC)',
            $this->screenText()
        );
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

    public function testEscapeQuitsWhileTheListIsEmpty(): void
    {
        self::assertStringContainsString('Nothing to do.', $this->screenText());

        $this->press(self::ESCAPE);

        self::assertFalse($this->tui->isRunning());
    }

    private function open(): void
    {
        $this->terminal = new VirtualTerminal(120, 24);
        $this->tui      = TimeTrackingScreen::createTui($this->terminal);
        $this->screen   = new TimeTrackingScreen($this->world->timeTracking);
        $this->screen->mount($this->tui);
        $this->tui->start();
    }

    private function press(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->terminal->simulateInput($key);
        }
    }

    private function screenText(): string
    {
        $this->tui->processRender();

        $screen = new ScreenBuffer($this->terminal->getColumns(), $this->terminal->getRows());
        $screen->write($this->terminal->getOutput());

        return $screen->getScreen();
    }
}
