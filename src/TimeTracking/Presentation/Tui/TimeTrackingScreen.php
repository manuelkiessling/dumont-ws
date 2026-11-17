<?php

declare(strict_types=1);

namespace App\TimeTracking\Presentation\Tui;

use App\Common\Domain\Service\DateAndTimeService;
use App\TimeTracking\Domain\Exception\TimeTrackingRefusedException;
use App\TimeTracking\Domain\Model\TrackedItem;
use App\TimeTracking\Domain\Service\TimeTrackingDomainService;
use DateTimeImmutable;
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

/**
 * The time tracking's screen: the items of the todo list with the time spent on each, a notice for what was refused,
 * and the items that were deleted but have time. It counts up while a timer runs and shows the changes made on the todo
 * list meanwhile, as it reads everything again every fraction of a second.
 */
final class TimeTrackingScreen
{
    private const float REFRESH_SECONDS = 0.25;
    private const array KEYBINDINGS     = [
        'quit' => ['ctrl+c', Key::ESCAPE],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;
    private TextWidget $deleted;

    /** @var list<array{value: string, label: string}>|null what the list shows now, null before it showed anything */
    private ?array $shownItems   = null;
    private string $shownDeleted = '';

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
            '.deleted'                         => new Style()->withColor('gray'),
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
        $this->list->onSelect($this->toggleTimer(...));
        $this->list->onCancel(fn () => $this->tui->stop());

        $this->emptyList = new TextWidget('Nothing to do.');
        $this->emptyList->addStyleClass('empty-list');
        $this->itemsArea = new ContainerWidget();
        $this->deleted   = new TextWidget('');
        $this->deleted->setStyleClasses(['deleted', 'hidden']);

        $tui->add(new TextWidget('Time tracking')->addStyleClass('title'));
        $tui->add($this->notice);
        $tui->add($this->itemsArea);
        $tui->add($this->deleted);
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
    }

    /**
     * Shows the items and their times as they are now. Runs every fraction of a second, and after each command.
     */
    public function refresh(): void
    {
        $now     = DateAndTimeService::getDateTimeImmutable();
        $tracked = $this->timeTracking->trackedItems();

        $onTheList = array_values(array_filter($tracked, static fn (TrackedItem $item): bool => !$item->isDeleted));
        $deleted   = array_values(array_filter($tracked, static fn (TrackedItem $item): bool => $item->isDeleted));

        $listItems = array_map(
            static fn (TrackedItem $item): array => [
                'value' => $item->itemId,
                'label' => self::label($item, $now),
            ],
            $onTheList
        );
        if ($listItems !== $this->shownItems) {
            $this->showItems($onTheList, $listItems);
        }

        $deletedText = implode("\n", array_map(
            static fn (TrackedItem $item): string => self::formatSeconds($item->secondsAt($now))
                . '  ' . StringUtils::stripControlBytes($item->text) . '  (deleted)',
            $deleted
        ));
        if ($deletedText !== $this->shownDeleted) {
            $this->shownDeleted = $deletedText;
            $this->tui->requestRender();
            $this->deleted->setText($deletedText === '' ? '' : "Deleted items\n" . $deletedText);
            if ($deletedText === '') {
                $this->deleted->addStyleClass('hidden');
            } else {
                $this->deleted->removeStyleClass('hidden');
            }
        }
    }

    /**
     * @param list<TrackedItem>                         $onTheList
     * @param list<array{value: string, label: string}> $listItems
     */
    private function showItems(array $onTheList, array $listItems): void
    {
        $selectedIndex = $this->selectedIndex($onTheList);

        $this->shownItems = $listItems;
        $this->list->setItems($listItems);
        $this->tui->requestRender();
        $this->list->setSelectedIndex($selectedIndex);

        $shown = $listItems === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
            $this->tui->setFocus($listItems === [] ? null : $this->list);
        }
    }

    /**
     * @param list<TrackedItem> $onTheList
     */
    private function selectedIndex(array $onTheList): int
    {
        $selected = $this->list->getSelectedItem();
        if ($selected === null) {
            return 0;
        }

        foreach ($onTheList as $index => $item) {
            if ($item->itemId === $selected['value']) {
                return $index;
            }
        }

        return 0;
    }

    private function toggleTimer(SelectEvent $event): void
    {
        $itemId = $event->getValue();

        try {
            $running = false;
            foreach ($this->timeTracking->trackedItems() as $item) {
                if ($item->itemId === $itemId) {
                    $running = $item->isRunning();
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

    private static function label(TrackedItem $item, DateTimeImmutable $now): string
    {
        $text = StringUtils::stripControlBytes($item->text);
        $time = self::formatSeconds($item->secondsAt($now));

        $runningSince = $item->runningSince();
        if ($runningSince !== null) {
            return "[>] {$text}  {$time}  (running since {$runningSince->format('Y-m-d H:i:s')} UTC)";
        }

        return $item->isDone ? "[x] {$text}  {$time}  (done)" : "[ ] {$text}  {$time}";
    }

    /**
     * Hours, minutes and seconds: 01:02:03.
     */
    private static function formatSeconds(int $seconds): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds, 60) % 60,
            $seconds             % 60
        );
    }
}
