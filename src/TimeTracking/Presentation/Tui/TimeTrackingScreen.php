<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
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
    private const float REFRESH_SECONDS = 0.5;

    private const array KEYBINDINGS = [
        'quit'       => ['ctrl+c'],
        'stop_timer' => ['s'],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private TextWidget $running;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;

    /** @var list<array{value: string, label: string}> */
    private array $shownItems = [];

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
            '.running'                         => new Style(color: 'green'),
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
        $this->running->setStyleClasses(['running']);

        $this->list = new SelectListWidget([], 10);
        $this->list->onSelect($this->startTimer(...));
        $this->list->onCancel(fn () => $this->tui->stop());
        $this->list->onInput($this->handleListKey(...));

        $this->emptyList = new TextWidget('Nothing to track. Add items on the todo list.');
        $this->emptyList->addStyleClass('empty-list');
        $this->itemsArea = new ContainerWidget();

        $tui->add(new TextWidget('Time tracking')->addStyleClass('title'));
        $tui->add($this->running);
        $tui->add($this->notice);
        $tui->add($this->itemsArea);
        $tui->add(new TextWidget('Enter  start timer    s  stop timer    ↑ ↓  select    Esc  quit')->addStyleClass('help'));

        $keybindings = new Keybindings(self::KEYBINDINGS);
        $tui->addListener(function (InputEvent $event) use ($keybindings): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });

        $tui->scheduleInterval($this->refresh(...), self::REFRESH_SECONDS);

        $this->showItems();
        $tui->setFocus($this->list);
    }

    /**
     * Shows the current state: changes made on the todo list or by another time tracking, and the running time.
     */
    public function refresh(): void
    {
        $this->showItems();
        $this->tui->requestRender();
    }

    private function startTimer(SelectEvent $event): void
    {
        $this->carryOut(fn () => $this->timeTracking->startTimer($event->getValue()));
    }

    private function handleListKey(string $data): bool
    {
        if (!$this->list->getKeybindings()->matches($data, 'stop_timer')) {
            return false;
        }

        $this->carryOut(fn () => $this->timeTracking->stopTimer());

        return true;
    }

    private function carryOut(callable $command): void
    {
        try {
            $command();
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
        $items         = $this->timeTracking->items();
        $selectedId    = $this->list->getSelectedItem()['value'] ?? null;
        $selectedIndex = 0;
        $runningText   = 'No timer is running.';

        foreach ($items as $index => $tracked) {
            if ($tracked->item->id === $selectedId) {
                $selectedIndex = $index;
            }
            if ($tracked->runningSince !== null) {
                $runningText = 'Timer running since ' . $tracked->runningSince->format('Y-m-d H:i:s') . ' UTC';
            }
        }

        $this->running->setText($runningText);

        $listItems = array_map($this->listItem(...), $items);
        if ($listItems !== $this->shownItems) {
            $this->shownItems = $listItems;
            $this->list->setItems($listItems);
        }
        if ($items !== []) {
            $this->list->setSelectedIndex($selectedIndex);
        }

        $shown = $items === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
            $this->tui->setFocus($this->list);
        }
    }

    private function listItem(TrackedItem $tracked): array
    {
        $text   = StringUtils::stripControlBytes($tracked->item->text);
        $mark   = $tracked->item->isDone() ? '[x]' : '[ ]';
        $time   = self::formatDuration($tracked->secondsSpent);
        $status = $tracked->isRunning() ? '  ▶ running' : '';

        return [
            'value' => $tracked->item->id,
            'label' => "{$mark} {$time}  {$text}{$status}",
        ];
    }

    public static function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
