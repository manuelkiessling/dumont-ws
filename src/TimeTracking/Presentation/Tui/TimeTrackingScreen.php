<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
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

/**
 * The time tracking's screen: the todo list's items with the time spent on each, a notice for what was refused. The
 * running timer counts up, and changes made on the todo list show up by themselves.
 */
final class TimeTrackingScreen
{
    private const float REFRESH_SECONDS = 0.25;

    private const array KEYBINDINGS = [
        'quit' => ['ctrl+c'],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;
    private string $shownSignature = '';

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

        $this->list = new SelectListWidget([], 10);
        $this->list->onSelect($this->toggleTimer(...));
        $this->list->onCancel(fn () => $this->tui->stop());

        $this->emptyList = new TextWidget('Nothing to track. Add items on the todo list.');
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

        $tui->scheduleInterval($this->refresh(...), self::REFRESH_SECONDS);

        $this->refresh();
        $tui->setFocus($this->list);
    }

    /**
     * Shows the items and times as they are now: the running timer counts up, the todo list's changes appear.
     */
    public function refresh(): void
    {
        $tracked   = $this->timeTracking->trackedItems();
        $rows      = array_map($this->listItem(...), $tracked);
        $signature = serialize($rows);
        if ($signature === $this->shownSignature) {
            return;
        }
        $this->shownSignature = $signature;

        $selectedId    = $this->list->getSelectedItem()['value'] ?? null;
        $selectedIndex = 0;
        foreach ($tracked as $index => $row) {
            if ($row->item->id === $selectedId) {
                $selectedIndex = $index;
            }
        }

        $this->list->setItems($rows);
        $this->list->setSelectedIndex(min($selectedIndex, count($rows) - 1));

        $shown = $rows === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
            $this->tui->setFocus($this->list);
        }
        $this->tui->requestRender();
    }

    private function toggleTimer(SelectEvent $event): void
    {
        $itemId = $event->getValue();

        try {
            $running = false;
            foreach ($this->timeTracking->trackedItems() as $row) {
                if ($row->item->id === $itemId && $row->isRunning()) {
                    $running = true;
                }
            }

            if ($running) {
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

        $this->refresh();
    }

    /**
     * @return array{value: string, label: string}
     */
    private function listItem(TrackedItem $row): array
    {
        $text  = StringUtils::stripControlBytes($row->item->text);
        $mark  = $row->item->isDone() ? '[x]' : '[ ]';
        $label = sprintf('%s %s  %s', $mark, $text, self::duration($row->spentSeconds));
        if ($row->runningSince !== null) {
            $label .= '  ▶ running since ' . $row->runningSince->format('Y-m-d H:i:s') . ' UTC';
        }

        return ['value' => $row->item->id, 'label' => $label];
    }

    private static function duration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
