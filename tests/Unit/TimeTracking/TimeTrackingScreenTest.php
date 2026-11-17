<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

/**
 * Drives the time tracking screen key by key on a virtual terminal and reads what it shows.
 */
final class TimeTrackingScreenTest extends TimeTrackingTestCase
{
    private const string ENTER  = "\r";
    private const string DOWN   = "\x1b[B";
    private const string ESCAPE = "\x1b";
    private const string CTRL_C = "\x03";

    private VirtualTerminal $terminal;
    private Tui $tui;
    private TimeTrackingScreen $screen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->terminal = new VirtualTerminal(120, 24);
        $this->tui      = TimeTrackingScreen::createTui($this->terminal);
        $this->screen   = new TimeTrackingScreen($this->timeTracking);
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    private function open(): void
    {
        $this->screen->mount($this->tui);
        $this->tui->start();
    }

    public function testAnEmptyListSaysSo(): void
    {
        $this->open();

        self::assertStringContainsString('Nothing to track.', $this->show());
    }

    public function testItemsShowTheirTimeToTheSecond(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->timeTracking->startTimer($item->id);
        $this->clock->sleep(3725);
        $this->timeTracking->stopTimer($item->id);
        $this->todoList->addItem('Call mum');
        $this->open();

        $screen = $this->show();
        self::assertStringContainsString('[ ] Buy milk  01:02:05', $screen);
        self::assertStringContainsString('[ ] Call mum  00:00:00', $screen);
        self::assertStringNotContainsString('running since', $screen);
    }

    public function testEnterStartsTheTimerWhichCountsUpLiveAndShowsWhenItStarted(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->open();
        $this->clock->sleep(3);

        $this->press(self::ENTER);
        self::assertStringContainsString('[ ] Buy milk  00:00:00  ▶ running since 2026-11-17 09:00:03 UTC', $this->show());

        $this->clock->sleep(2);
        $this->screen->refresh();
        self::assertStringContainsString('Buy milk  00:00:02  ▶ running since 2026-11-17 09:00:03 UTC', $this->show());
    }

    public function testEnterStopsARunningTimer(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->open();

        $this->press(self::ENTER);
        $this->clock->sleep(4);
        $this->press(self::ENTER);
        $this->clock->sleep(60);
        $this->screen->refresh();

        $screen = $this->show();
        self::assertStringContainsString('Buy milk  00:00:04', $screen);
        self::assertStringNotContainsString('running since', $screen);
    }

    public function testStartingATimerOnAnotherItemStopsTheFirst(): void
    {
        $this->todoList->addItem('First');
        $this->todoList->addItem('Second');
        $this->open();

        $this->press(self::ENTER);
        $this->clock->sleep(5);
        $this->press(self::DOWN, self::ENTER);
        $this->clock->sleep(2);
        $this->screen->refresh();

        $screen = $this->show();
        self::assertStringContainsString('[ ] First  00:00:05  ', $screen);
        self::assertStringContainsString('Second  00:00:02  ▶ running', $screen);
        self::assertSame(1, substr_count($screen, 'running since'));
    }

    public function testATimerOnADoneItemIsRefusedWithTheReason(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->markItemDone($item->id);
        $this->open();

        $this->press(self::ENTER);

        $screen = $this->show();
        self::assertStringContainsString('This item is done, so no timer can be started on it.', $screen);
        self::assertStringNotContainsString('running since', $screen);
    }

    public function testChangesOnTheTodoListShowWithoutReopening(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->open();
        $this->press(self::ENTER);
        self::assertStringContainsString('running since', $this->show());

        $this->clock->sleep(9);
        $this->todoList->addItem('Call mum');
        $this->todoList->markItemDone($item->id);
        $this->screen->refresh();

        $screen = $this->show();
        self::assertStringContainsString('[x] Buy milk  00:00:09', $screen);
        self::assertStringContainsString('[ ] Call mum', $screen);
        self::assertStringNotContainsString('running since', $screen);

        $this->todoList->deleteItem($item->id);
        $this->screen->refresh();
        self::assertStringNotContainsString('Buy milk', $this->show());
    }

    public function testQuittingLeavesTheTimerRunning(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->open();
        $this->press(self::ENTER);

        $this->press(self::ESCAPE);

        self::assertFalse($this->tui->isRunning());
        $this->clock->sleep(30);
        self::assertTrue($this->isRunning($item->id));
        self::assertSame(30, $this->spent($item->id));
    }

    public function testCtrlCQuits(): void
    {
        $this->open();

        $this->press(self::CTRL_C);

        self::assertFalse($this->tui->isRunning());
    }

    private function press(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->terminal->simulateInput($key);
        }
    }

    private function show(): string
    {
        $this->tui->processRender();

        $screen = new ScreenBuffer($this->terminal->getColumns(), $this->terminal->getRows());
        $screen->write($this->terminal->getOutput());

        return $screen->getScreen();
    }
}
