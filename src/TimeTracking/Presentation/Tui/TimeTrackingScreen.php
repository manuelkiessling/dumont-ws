<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\Common\Presentation\Tui\ScreenStyleSheet;
use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use App\Todo\Facade\Dto\TodoItemDto;
use App\Todo\Facade\TodoItemsInterface;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * The time tracking screen: the items of the todo list with the time spent on each, and the one timer that runs.
 */
final class TimeTrackingScreen
{
    /**
     * On top of the list's own keys: Enter starts or stops the selected item's timer
     */
    private const array KEYBINDINGS = [
        'quit' => ['ctrl+c', 'escape'],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;

    public function __construct(
        private readonly TimeTrackingDomainService $timeTracking,
        private readonly TodoItemsInterface        $todoItems
    ) {
    }

    /**
     * The Tui with the screen's styles and keybindings, on the real terminal unless another one is given.
     */
    public static function createTui(TerminalInterface $terminal = new Terminal()): Tui
    {
        return new Tui(ScreenStyleSheet::create(), $terminal, new Keybindings(self::KEYBINDINGS));
    }

    public function mount(Tui $tui): void
    {
        $this->tui = $tui;

        $this->notice = new TextWidget('');
        $this->notice->setStyleClasses(['notice', 'hidden']);

        $this->list = new SelectListWidget([], 10);
        $this->list->onSelect($this->toggleTimer(...));

        $this->emptyList = new TextWidget('Nothing on the todo list.');
        $this->emptyList->addStyleClass('empty-list');
        $this->itemsArea = new ContainerWidget();

        $tui->add(new TextWidget('Time')->addStyleClass('title'));
        $tui->add($this->notice);
        $tui->add($this->itemsArea);
        $tui->add(new TextWidget('Enter  start / stop    Esc  quit')->addStyleClass('help'));

        $keybindings = new Keybindings(self::KEYBINDINGS);
        $tui->addListener(function (InputEvent $event) use ($keybindings): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });

        $this->showItems();
    }

    /**
     * Shows the items and their times as they are now, also where the todo list was changed elsewhere.
     */
    public function refresh(): void
    {
        $this->showItems();
        $this->tui->requestRender();
    }

    private function toggleTimer(SelectEvent $event): void
    {
        $itemId = $event->getValue();

        try {
            if ($this->timeTracking->runningEntry()?->itemId === $itemId) {
                $this->timeTracking->stopTimer();
            } else {
                $this->timeTracking->startTimer($itemId);
            }
            $this->notice->setText('');
            $this->notice->addStyleClass('hidden');
        } catch (TimeTrackingRefusedException $refused) {
            $this->notice->setText($refused->getMessage());
            $this->notice->removeStyleClass('hidden');
        }

        $this->showItems();
    }

    private function showItems(): void
    {
        $items      = $this->todoItems->items();
        $selectedId = $this->list->getSelectedItem()['value'] ?? null;

        $this->list->setItems(array_map($this->listItem(...), $items));
        foreach ($items as $index => $item) {
            if ($item->itemId === $selectedId) {
                $this->list->setSelectedIndex($index);
            }
        }

        $shown = $items === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
            $this->tui->setFocus($items === [] ? null : $this->list);
        }
    }

    /**
     * @return array{value: string, label: string}
     */
    private function listItem(TodoItemDto $item): array
    {
        $running = $this->timeTracking->runningEntry();
        $text    = StringUtils::stripControlBytes($item->text);
        $time    = $this->duration($this->timeTracking->trackedSeconds($item->itemId));

        $label = match (true) {
            $running?->itemId === $item->itemId => "▶ {$time}   {$text}   running since {$running->startedAt->format('H:i:s')}",
            $item->isDone                       => "  {$time}   {$text}   done",
            default                             => "  {$time}   {$text}",
        };

        return ['value' => $item->itemId, 'label' => $label];
    }

    private function duration(int $seconds): string
    {
        return sprintf('%2dh %02dm %02ds', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
