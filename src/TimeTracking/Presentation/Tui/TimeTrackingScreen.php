<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\Common\Presentation\Tui\ScreenStyleSheet;
use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
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
 * The time tracking's screen: the items of the todo list with the time spent on each, and a timer to start or stop on
 * the selected one. It reads everything again on refresh(), so that a running time counts up and changes made on the
 * todo list show up.
 */
final class TimeTrackingScreen
{
    /**
     * On top of each widget's own keys: Enter starts the selected item's timer, or stops it, Escape quits.
     */
    private const array KEYBINDINGS = [
        'quit' => ['ctrl+c'],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;

    /** @var array<string, TrackedItem> the items as last shown, by ID */
    private array $shown = [];

    public function __construct(
        private readonly TimeTrackingDomainService $timeTracking
    ) {
    }

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
        $this->list->onCancel(fn () => $this->tui->stop());

        $this->emptyList = new TextWidget('Nothing to track: the todo list is empty.');
        $this->emptyList->addStyleClass('empty-list');
        $this->itemsArea = new ContainerWidget();

        $tui->add(new TextWidget('Time tracking')->addStyleClass('title'));
        $tui->add($this->notice);
        $tui->add($this->itemsArea);
        $tui->add(new TextWidget('Enter  start / stop timer    ↑ ↓  select    Esc  quit')->addStyleClass('help'));

        $keybindings = new Keybindings(self::KEYBINDINGS);
        $tui->addListener(function (InputEvent $event) use ($keybindings): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });

        $this->showItems();
        $tui->setFocus($this->list);
    }

    /**
     * Reads the items and their times again and draws the screen; called every second while the screen is open.
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
            if (($this->shown[$itemId] ?? null)?->isRunning() === true) {
                $this->timeTracking->stopTimer($itemId);
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
        $items         = $this->timeTracking->trackedItems();
        $selectedIndex = $this->selectedIndex($items);

        $this->shown = [];
        foreach ($items as $item) {
            $this->shown[$item->itemId] = $item;
        }

        $this->list->setItems(array_map($this->listItem(...), $items));
        $this->list->setSelectedIndex(max(0, min($selectedIndex, count($items) - 1)));

        $shown = $items === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
            $this->tui->setFocus($shown === $this->list ? $this->list : null);
        }
    }

    /**
     * @param list<TrackedItem> $items
     */
    private function selectedIndex(array $items): int
    {
        $selected = $this->list->getSelectedItem();
        if ($selected === null) {
            return 0;
        }

        foreach ($items as $index => $item) {
            if ($item->itemId === $selected['value']) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @return array{value: string, label: string}
     */
    private function listItem(TrackedItem $item): array
    {
        $text = StringUtils::stripControlBytes($item->text);
        $time = self::duration($item->secondsSpent);

        return [
            'value' => $item->itemId,
            'label' => match (true) {
                $item->runningSince !== null => "[>] {$text}  {$time}  (running since {$item->runningSince->format('Y-m-d H:i:s')} UTC)",
                $item->isDone                => "[x] {$text}  {$time}",
                default                      => "[ ] {$text}  {$time}",
            },
        ];
    }

    private static function duration(int $seconds): string
    {
        return sprintf(
            '%d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }
}
