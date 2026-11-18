<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\Common\Presentation\Tui\TerminalText;
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
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * The time tracking screen: the items of the todo list with the time spent on each, and the keys to start and stop
 * the timer. It reads the state again on every refresh, to count up a running timer and to show what the todo list
 * screen in another terminal changed.
 */
final class TimeTrackingScreen
{
    private const array KEYBINDINGS = [
        'quit'           => ['ctrl+c'],
        'focus_next'     => [Key::TAB],
        'focus_previous' => ['shift+tab'],
        'stop_timer'     => ['s'],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private SelectListWidget $list;
    private TextWidget $emptyList;

    public function __construct(
        private readonly TimeTrackingDomainService $timeTracking
    ) {
    }

    /**
     * The Tui with the screen's styles and keybindings, on the real terminal unless another one is given.
     */
    public static function createTui(TerminalInterface $terminal = new Terminal()): Tui
    {
        return new Tui(self::styleSheet(), $terminal, new Keybindings(self::KEYBINDINGS));
    }

    private static function styleSheet(): StyleSheet
    {
        $border     = Border::all(1, BorderPattern::ROUNDED, 'gray');
        $focusedBox = new Style()->withBorder(Border::all(1, BorderPattern::ROUNDED, 'cyan'));

        return new StyleSheet([
            ':root'                            => new Style()->withPadding(Padding::xy(2, 1))->withGap(1),
            '.title'                           => new Style()->withBold()->withColor('cyan'),
            SelectListWidget::class            => new Style(Padding::xy(2, 1), $border),
            SelectListWidget::class . ':focus' => $focusedBox,
            '.empty-list'                      => new Style(Padding::xy(2, 1), $border)->withColor('gray'),
            '.notice'                          => new Style()->withBold()->withColor('yellow'),
            '.hidden'                          => new Style()->withHidden(),
            '.help'                            => new Style()->withColor('gray'),
        ]);
    }

    public function mount(Tui $tui): void
    {
        $this->tui = $tui;

        $this->notice = new TextWidget('');
        $this->notice->setStyleClasses(['notice', 'hidden']);

        $this->list = new SelectListWidget([], 10);
        $this->list->onSelect($this->startTimer(...));
        $this->list->onCancel(fn () => $this->tui->stop());
        $this->list->onInput($this->handleListKey(...));

        $this->emptyList = new TextWidget('Nothing to track: add items on the todo list.');
        $this->emptyList->addStyleClass('empty-list');

        $tui->add(new TextWidget('Time tracking')->addStyleClass('title'));
        $tui->add($this->notice);
        $tui->add($this->emptyList);
        $tui->add($this->list);
        $tui->add(new TextWidget('Enter  start timer    s  stop timer    ↑ ↓  select    Esc  quit')->addStyleClass('help'));

        $keybindings = new Keybindings(self::KEYBINDINGS);
        $tui->addListener(function (InputEvent $event) use ($keybindings): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });

        $this->showItems();
        $this->tui->setFocus($this->list);
    }

    /**
     * Reads the items and times again and draws them: called every second, as no key is needed for a time to count up
     * or for the todo list to change.
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

        $this->carryOut($this->timeTracking->stopRunningTimer(...));

        return true;
    }

    /**
     * Carries out one command and shows the items as they are afterwards, or the reason the command was refused.
     */
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
        $items         = $this->timeTracking->trackedItems();
        $selectedId    = $this->list->getSelectedItem()['value'] ?? null;
        $selectedIndex = 0;
        foreach ($items as $index => $item) {
            if ($item->itemId === $selectedId) {
                $selectedIndex = $index;
            }
        }

        $this->list->setItems(array_map($this->listItem(...), $items));
        $this->list->setSelectedIndex(min($selectedIndex, count($items) - 1));

        // The list stays in the widget tree, hidden while there is nothing to show, so that it keeps the focus
        if ($items === []) {
            $this->list->addStyleClass('hidden');
            $this->emptyList->removeStyleClass('hidden');
        } else {
            $this->list->removeStyleClass('hidden');
            $this->emptyList->addStyleClass('hidden');
        }
    }

    /**
     * @return array{value: string, label: string}
     */
    private function listItem(TrackedItem $item): array
    {
        $text    = TerminalText::withoutControls($item->text);
        $time    = self::duration($item->seconds);
        $marker  = $item->done ? '[x]' : '[ ]';
        $running = $item->runningSince === null
            ? ''
            : "  ▶ running, started {$item->runningSince->format('Y-m-d H:i:s')} UTC";

        return [
            'value' => $item->itemId,
            'label' => "{$marker} {$text}  {$time}{$running}",
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
