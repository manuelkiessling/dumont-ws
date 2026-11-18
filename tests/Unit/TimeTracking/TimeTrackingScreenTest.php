<?php

declare(strict_types=1);

namespace App\Tests\Unit\TimeTracking;

use App\Tests\Unit\Todo\InMemoryTodoItemStore;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\TimeTracking\Presentation\Tui\TimeTrackingScreen;
use App\Todo\Domain\Service\TodoListDomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class TimeTrackingScreenTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string ENTER  = "\r";
    private const string DOWN   = "\x1b[B";
    private const string ESCAPE = "\x1b";
    private const string CTRL_C = "\x03";

    private TodoListDomainService $todoList;
    private VirtualTerminal $terminal;
    private TimeTrackingScreen $screen;
    private Tui $tui;

    protected function setUp(): void
    {
        self::mockTime('2026-11-18 10:00:00');

        $todoItems      = new InMemoryTodoItemStore();
        $timeTracking   = new TimeTrackingDomainService(new InMemoryTimeRecordStore(), $todoItems);
        $this->todoList = new TodoListDomainService($todoItems, $timeTracking);

        $this->terminal = new VirtualTerminal(100, 24);
        $this->tui      = TimeTrackingScreen::createTui($this->terminal);
        $this->screen   = new TimeTrackingScreen($timeTracking);
        $this->screen->mount($this->tui);
        $this->tui->start();
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testWithoutItemsTheScreenSaysSo(): void
    {
        $screen = $this->screen();

        self::assertStringContainsString('Nothing to track', $screen);
        self::assertStringContainsString('No timer is running.', $screen);
    }

    public function testItemsAreShownWithTheirTime(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->screen->refresh();

        self::assertStringContainsString('[ ] 00:00:00  Buy milk', $this->screen());
    }

    public function testEnterStartsTheTimerWhichCountsUpLiveAndShowsWhenItStarted(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->screen->refresh();

        $this->press(self::ENTER);
        self::assertStringContainsString('Timer running since 2026-11-18 10:00:00 UTC', $this->screen());

        self::mockTime('2026-11-18 11:02:03');
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] 01:02:03  Buy milk  ▶ running', $screen);
        self::assertStringContainsString('Timer running since 2026-11-18 10:00:00 UTC', $screen);
    }

    public function testSStopsTheTimerAndTheTimeStaysAsItWas(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER);
        self::mockTime('2026-11-18 10:00:09');
        $this->press('s');
        self::mockTime('2026-11-18 10:10:00');
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] 00:00:09  Buy milk', $screen);
        self::assertStringNotContainsString('▶', $screen);
        self::assertStringContainsString('No timer is running.', $screen);
    }

    public function testStartingATimerOnAnotherItemMovesTheTimer(): void
    {
        $this->todoList->addItem('First');
        $this->todoList->addItem('Second');
        $this->screen->refresh();
        $this->press(self::ENTER);
        self::mockTime('2026-11-18 10:00:05');

        $this->press(self::DOWN, self::ENTER);
        self::mockTime('2026-11-18 10:00:07');
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[ ] 00:00:05  First', $screen);
        self::assertStringNotContainsString('First  ▶', $screen);
        self::assertStringContainsString('[ ] 00:00:02  Second  ▶ running', $screen);
    }

    public function testATimerCannotBeStartedOnADoneItemAndTheScreenSaysWhy(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->todoList->markItemDone($item->id);
        $this->screen->refresh();

        $this->press(self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('This item is done, so no timer can be started on it.', $screen);
        self::assertStringContainsString('No timer is running.', $screen);
    }

    public function testChangesOnTheTodoListAppearWithoutReopeningTheScreen(): void
    {
        $item = $this->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER);
        self::assertStringContainsString('Buy milk', $this->screen());

        $this->todoList->markItemDone($item->id);
        $this->todoList->addItem('Call mum');
        $this->screen->refresh();

        $screen = $this->screen();
        self::assertStringContainsString('[x] 00:00:00  Buy milk', $screen);
        self::assertStringContainsString('[ ] 00:00:00  Call mum', $screen);
        self::assertStringContainsString('No timer is running.', $screen);

        $this->todoList->deleteItem($item->id);
        $this->screen->refresh();

        self::assertStringNotContainsString('Buy milk', $this->screen());
    }

    public function testASelectionSurvivesRefreshes(): void
    {
        $this->todoList->addItem('First');
        $this->todoList->addItem('Second');
        $this->screen->refresh();
        $this->press(self::DOWN);
        $this->screen->refresh();

        $this->press(self::ENTER);

        self::assertStringContainsString('Second  ▶ running', $this->screen());
    }

    public function testQuittingLeavesARunningTimerRunning(): void
    {
        $this->todoList->addItem('Buy milk');
        $this->screen->refresh();
        $this->press(self::ENTER, self::ESCAPE);

        self::assertFalse($this->tui->isRunning());

        $reopened = TimeTrackingScreen::createTui($this->terminal = new VirtualTerminal(100, 24));
        $this->tui->stop();
        $this->tui = $reopened;
        self::mockTime('2026-11-18 10:00:30');
        $this->mountAgain();

        self::assertStringContainsString('[ ] 00:00:30  Buy milk  ▶ running', $this->screen());
    }

    public function testCtrlCQuits(): void
    {
        $this->press(self::CTRL_C);

        self::assertFalse($this->tui->isRunning());
    }

    public function testDurationsAreShownInHoursMinutesAndSeconds(): void
    {
        self::assertSame('00:00:00', TimeTrackingScreen::formatDuration(0));
        self::assertSame('01:01:01', TimeTrackingScreen::formatDuration(3661));
        self::assertSame('100:00:59', TimeTrackingScreen::formatDuration(360059));
    }

    private function mountAgain(): void
    {
        $this->screen->mount($this->tui);
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
