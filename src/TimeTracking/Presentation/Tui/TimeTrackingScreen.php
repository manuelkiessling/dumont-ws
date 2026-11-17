<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;

final class TimeTrackingScreen
{
    private const array KEYBINDINGS = [
        'quit' => ['ctrl+c'],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private TextWidget $running;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;

    public function __construct(
        private readonly TimeTrackingDomainService $timeTracking
    ) {
    }

    public static function createTui(TerminalInterface $terminal = new Terminal()): Tui
    {
        return new Tui(self::styleSheet(), $terminal, new Keybindings(self::KEYBINDINGS));
    }

    private static function styleSheet(): StyleSheet
    {
        $border     = Border::all(1, BorderPattern::ROUNDED, 'gray');
        $focusedBox = new Style(border: Border::all(1, BorderPattern::ROUNDED, 'cyan'));

        return new StyleSheet([
            ':root'                            => new Style(padding: Padding::xy(2, 1), gap: 1),
            '.title'                           => new Style(bold: true, color: 'cyan'),
            SelectListWidget::class            => new Style(Padding::xy(2, 1), $border),
            SelectListWidget::class . ':focus' => $focusedBox,
            '.empty-list'                      => new Style(Padding::xy(2, 1), $border, color: 'gray'),
            '.running'                         => new Style(bold: true, color: 'green'),
            '.notice'                          => new Style(bold: true, color: 'yellow'),
            '.hidden'                          => new Style(hidden: true),
            '.help'                            => new Style(color: 'gray'),
        ]);
    }

    public function mount(Tui $tui): void
    {
        $this->tui = $tui;

        $this->notice = new TextWidget('');
        $this->notice->setStyleClasses(['notice', 'hidden']);
        $this->running = new TextWidget('');
        $this->running->setStyleClasses(['running', 'hidden']);

        $this->list = new SelectListWidget([], 10);
        $this->list->onSelect($this->toggleTimer(...));
        $this->list->onCancel(fn () => $this->tui->stop());

        $this->emptyList = new TextWidget('Nothing to track. Add items on the todo list.');
        $this->emptyList->addStyleClass('empty-list');
        $this->itemsArea = new ContainerWidget();

        $tui->add(new TextWidget('Time tracking')->addStyleClass('title'));
        $tui->add($this->running);
        $tui->add($this->notice);
        $tui->add($this->itemsArea);
        $tui->add(new TextWidget('Enter  start / stop timer    ↑ ↓  select    Esc  quit (timers keep running)')->addStyleClass('help'));

        $keybindings = new Keybindings(self::KEYBINDINGS);
        $tui->addListener(function (InputEvent $event) use ($keybindings): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });

        // Ticks arrive several times a second: that keeps the running time counting up and shows changes made on the todo list.
        $tui->onTick(function (TickEvent $event): void {
            $this->showItems();
        });

        $this->showItems();
        $tui->setFocus($this->list);
    }

    private function toggleTimer(SelectEvent $event): void
    {
        $itemId = $event->getValue();

        try {
            $running = array_filter(
                $this->timeTracking->trackedItems(),
                static fn (TrackedItem $item): bool => $item->id === $itemId && $item->isRunning()
            );
            if ($running !== []) {
                $this->timeTracking->stopTimerOf($itemId);
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
        $items = $this->timeTracking->trackedItems();

        $selected      = $this->list->getSelectedItem();
        $selectedIndex = 0;
        if ($selected !== null) {
            foreach ($items as $index => $item) {
                if ($item->id === $selected['value']) {
                    $selectedIndex = $index;
                }
            }
        }

        $this->list->setItems(array_map($this->listItem(...), $items));
        $this->list->setSelectedIndex(max(0, min($selectedIndex, count($items) - 1)));

        $current = array_values(array_filter($items, static fn (TrackedItem $item): bool => $item->isRunning()))[0] ?? null;
        if ($current === null) {
            $this->running->setText('');
            $this->running->addStyleClass('hidden');
        } else {
            $text = StringUtils::stripControlBytes($current->text);
            $this->running->setText("Timer running on \"{$text}\", started {$current->runningSince?->format('Y-m-d H:i:s')} UTC");
            $this->running->removeStyleClass('hidden');
        }

        $shown = $items === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
            if ($shown === $this->list) {
                $this->tui->setFocus($this->list);
            }
        }
    }

    private function listItem(TrackedItem $item): array
    {
        $text = StringUtils::stripControlBytes($item->text);
        $time = self::formatSeconds($item->seconds);

        $label = match (true) {
            $item->isRunning() => "▶ {$text}  {$time}  (running since {$item->runningSince?->format('H:i:s')})",
            $item->isDeleted   => "  {$text}  {$time}  (deleted)",
            $item->isDone      => "[x] {$text}  {$time}  (done)",
            default            => "[ ] {$text}  {$time}",
        };

        return ['value' => $item->id, 'label' => $label];
    }

    public static function formatSeconds(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds, 60) % 60, $seconds % 60);
    }
}
