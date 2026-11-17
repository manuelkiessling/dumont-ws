<?php

declare(strict_types=1);

namespace App\Tests\Unit\Todo;

use App\Todo\Domain\Service\TodoListDomainService;
use App\Todo\Presentation\Tui\TodoListScreen;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

/**
 * Drives the screen as a person would, key by key, on a virtual terminal, and reads what the screen shows.
 */
final class TodoListScreenTest extends TestCase
{
    private const string ENTER  = "\r";
    private const string TAB    = "\t";
    private const string DOWN   = "\x1b[B";
    private const string ESCAPE = "\x1b";
    private const string CTRL_C = "\x03";

    private VirtualTerminal $terminal;
    private Tui $tui;

    protected function setUp(): void
    {
        $todoList = new TodoListDomainService(new InMemoryTodoItemStore(), new RecordingItemTimers());

        $this->terminal = new VirtualTerminal(100, 24);
        $this->tui      = TodoListScreen::createTui($this->terminal);
        new TodoListScreen($todoList)->mount($this->tui);
        $this->tui->start();
    }

    protected function tearDown(): void
    {
        $this->tui->stop();
    }

    public function testAnEmptyListSaysSo(): void
    {
        self::assertStringContainsString('Nothing to do.', $this->screen());
    }

    public function testATypedItemIsAddedToTheListAndTheInputIsCleared(): void
    {
        $this->press('Buy milk', self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Buy milk', $screen);
        self::assertStringNotContainsString('New item: Buy milk', $screen);
        self::assertStringNotContainsString('Nothing to do.', $screen);
    }

    public function testARefusedItemShowsTheReasonAndKeepsTheTextAsTyped(): void
    {
        $this->press(str_repeat('x', 201), self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('An item is at most 200 characters long.', $screen);
        self::assertStringContainsString('Nothing to do.', $screen);
        self::assertStringContainsString('New item: xxx', $screen);
    }

    public function testTheReasonIsGoneOnceACommandIsCarriedOut(): void
    {
        $this->press(self::ENTER);
        self::assertStringContainsString('Please write what there is to do.', $this->screen());

        $this->press('Buy milk', self::ENTER);

        self::assertStringNotContainsString('Please write what there is to do.', $this->screen());
    }

    public function testTheSelectedItemIsMarkedDoneAndStaysSelected(): void
    {
        $this->press('Buy milk', self::ENTER, 'Call mum', self::ENTER);

        $this->press(self::TAB, self::DOWN, self::ENTER);

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Buy milk', $screen);
        self::assertStringContainsString('[x] Call mum  (done ', $screen);

        $this->press('d');

        $screen = $this->screen();
        self::assertStringContainsString('[ ] Buy milk', $screen);
        self::assertStringNotContainsString('Call mum', $screen);
    }

    public function testDeletingTheLastItemLeavesAnEmptyList(): void
    {
        $this->press('Buy milk', self::ENTER, self::TAB, 'd');

        self::assertStringContainsString('Nothing to do.', $this->screen());
    }

    public function testEscapeQuits(): void
    {
        $this->press(self::ESCAPE);

        self::assertFalse($this->tui->isRunning());
    }

    public function testCtrlCQuits(): void
    {
        $this->press('Buy milk', self::ENTER, self::TAB, self::CTRL_C);

        self::assertFalse($this->tui->isRunning());
    }

    private function press(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->terminal->simulateInput($key);
        }
    }

    /**
     * What a person sees: the terminal's output, written as updates and cursor moves, played back onto a screen.
     */
    private function screen(): string
    {
        $this->tui->processRender();

        $screen = new ScreenBuffer($this->terminal->getColumns(), $this->terminal->getRows());
        $screen->write($this->terminal->getOutput());

        return $screen->getScreen();
    }
}
