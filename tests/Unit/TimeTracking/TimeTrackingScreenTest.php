<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Tests\Unit\Todo\InMemoryTodoItemStore;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use App\Todo\Domain\Service\TodoListDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class TimeTrackingScreenTest extends TestCase
{
    private const string ENTER  = "\r";
    private const string DOWN   = "\x1b[B";
    private const string ESCAPE = "\x1b";
    private const string CTRL_C = "\x03";

    private MockClock $clock;
    private TodoListDomainService $todoList;
    private TimeTrackingDomainService $timeTracking;
    private VirtualTerminal $terminal;
    private Tui $tui;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17 09:00:00');
        Clock::set($this->clock);

        $items              = new InMemoryTodoItemStore();
        $this->timeTracking = new TimeTrackingDomainService(new InMemoryTimeEntryStore(), $items);
        $this->todoList     = new TodoListDomainService($items, $this->timeTracking);
    }

    protected function tearDown(): void
    {
        if (isset($this->tui)) {
            $this->tui->stop();
        }
    }

    public function testAnEmptyListSaysSo(): void
    {
        $this->open();

        self::assertStringContainsString('Nothing to track', $this->screen());
    }

    public function testItemsAreShownWithTheirTime(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->open();

        self::assertStringContainsString('[ ] Buy milk  00:00:00', $this->screen());
    }

    public function testTheTimeCountsUpLiveWhileATimerRunsAndShowsWhenItStarted(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->open();

        $this->press(self::ENTER);
        $screen = $this->screen();
        self::assertStringContainsString('▶ Buy milk  00:00:00  (running since 09:00:00)', $screen);
        self::assertStringContainsString('started 2026-11-17 09:00:00', $screen);

        $this->clock->sleep(3725);
        $this->tui->tick();

        self::assertStringContainsString('▶ Buy milk  01:02:05', $this->screen());
    }

    public function testEnterStopsARunningTimerAndTheTimeStaysAsItWas(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->open();

        $this->press(self::ENTER);
        $this->clock->sleep(7);
        $this->press(self::ENTER);
        $this->clock->sleep(100);
        $this->tui->tick();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Buy milk  00:00:07', $screen);
        self::assertStringNotContainsString('▶', $screen);
    }

    public function testStartingATimerOnAnotherItemStopsTheFirst(): void
    {
        $this->todoList->addItem('First');
        $this->todoList->addItem('Second');
        $this->open();

        $this->press(self::ENTER);
        $this->clock->sleep(10);
        $this->press(self::DOWN, self::ENTER);
        $this->clock->sleep(5);
        $this->tui->tick();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] First  00:00:10', $screen);
        self::assertStringContainsString('▶ Second  00:00:05', $screen);
    }

    public function testATimerCannotBeStartedOnADoneItemAndTheScreenSaysWhy(): void
    {
        $id = $this->todoList->addItem('Buy milk')->id;
        $this->todoList->markItemDone($id);
        $this->open();

        $this->press(self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('This item is done, so no timer can be started on it.', $screen);
        self::assertStringNotContainsString('▶', $screen);
    }

    public function testChangesMadeOnTheTodoListAppearWithoutReopening(): void
    {
        $this->open();
        self::assertStringContainsString('Nothing to track', $this->screen());

        $id = $this->todoList->addItem('Buy milk')->id;
        $this->tui->tick();
        self::assertStringContainsString('[ ] Buy milk', $this->screen());

        $this->press(self::ENTER);
        $this->clock->sleep(9);
        $this->todoList->markItemDone($id);
        $this->tui->tick();
        $screen = $this->screen();
        self::assertStringContainsString('[x] Buy milk  00:00:09  (done)', $screen);
        self::assertStringNotContainsString('▶', $screen);

        $this->todoList->deleteItem($id);
        $this->tui->tick();
        self::assertStringContainsString('Buy milk  00:00:09  (deleted)', $this->screen());
    }

    public function testQuittingDoesNotStopARunningTimer(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->open();
        $this->press(self::ENTER, self::ESCAPE);

        self::assertFalse($this->tui->isRunning());
        $this->clock->sleep(30);
        self::assertSame(30, $this->timeTracking->trackedItems()[0]->seconds);
        self::assertTrue($this->timeTracking->trackedItems()[0]->isRunning());
    }

    public function testCtrlCQuits(): void
    {
        $this->open();
        $this->press(self::CTRL_C);

        self::assertFalse($this->tui->isRunning());
    }

    private function open(): void
    {
        $this->terminal = new VirtualTerminal(120, 24);
        $this->tui      = TimeTrackingScreen::createTui($this->terminal);
        new TimeTrackingScreen($this->timeTracking)->mount($this->tui);
        $this->tui->start();
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
