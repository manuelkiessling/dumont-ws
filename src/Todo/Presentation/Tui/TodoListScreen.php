<?php

declare(strict_types=1);

namespace App\Todo\Presentation\Tui;

use App\Common\Presentation\Tui\ScreenStyleSheet;
use App\Todo\Domain\Exception\TodoRefusedException;
use App\Todo\Domain\Model\TodoItem;
use App\Todo\Domain\Service\TodoListDomainService;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * The todo list's screen: a line to add an item, a notice for what was refused, and the list of items.
 */
final class TodoListScreen
{
    /**
     * On top of each widget's own keys: Enter adds an item or marks the selected one done, Escape quits.
     */
    private const array KEYBINDINGS = [
        'quit'           => ['ctrl+c'],
        'focus_next'     => [Key::TAB],
        'focus_previous' => ['shift+tab'],
        'delete_item'    => ['d', Key::DELETE],
    ];

    private Tui $tui;
    private TextWidget $notice;
    private InputWidget $input;
    private SelectListWidget $list;
    private TextWidget $emptyList;
    private ContainerWidget $itemsArea;

    public function __construct(
        private readonly TodoListDomainService $todoList
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
        $this->input = new InputWidget();
        $this->input->setPrompt('New item: ');
        $this->input->onSubmit($this->addItem(...));
        $this->input->onCancel(fn () => $this->tui->stop());

        $this->list = new SelectListWidget([], 10);
        $this->list->onSelect($this->markItemDone(...));
        $this->list->onCancel(fn () => $this->tui->stop());
        $this->list->onInput($this->handleListKey(...));

        $this->emptyList = new TextWidget('Nothing to do.');
        $this->emptyList->addStyleClass('empty-list');
        $this->itemsArea = new ContainerWidget();

        $tui->add(new TextWidget('Todo')->addStyleClass('title'));
        $tui->add($this->input);
        $tui->add($this->notice);
        $tui->add($this->itemsArea);
        $tui->add(new TextWidget('Enter  add / mark done    d  delete    Tab  switch    Esc  quit')->addStyleClass('help'));

        $keybindings = new Keybindings(self::KEYBINDINGS);
        $tui->addListener(function (InputEvent $event) use ($keybindings): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });

        $this->showItems();
        $tui->setFocus($this->input);
    }

    private function addItem(SubmitEvent $event): void
    {
        $this->carryOut(function () use ($event): void {
            $this->todoList->addItem($event->getValue());
            $this->input->setValue('');
        });
    }

    private function markItemDone(SelectEvent $event): void
    {
        $this->carryOut(fn () => $this->todoList->markItemDone($event->getValue()));
    }

    private function handleListKey(string $data): bool
    {
        if (!$this->list->getKeybindings()->matches($data, 'delete_item')) {
            return false;
        }

        $selected = $this->list->getSelectedItem();
        if ($selected !== null) {
            $this->carryOut(fn () => $this->todoList->deleteItem($selected['value']));
        }

        return true;
    }

    /**
     * Carries out one command and shows the list as it is afterwards, or the reason the command was refused.
     */
    private function carryOut(callable $command): void
    {
        try {
            $command();
            $this->notice->setText('');
            $this->notice->addStyleClass('hidden');
        } catch (TodoRefusedException $refused) {
            $this->notice->setText($refused->getMessage());
            $this->notice->removeStyleClass('hidden');
        }

        $this->showItems();
    }

    private function showItems(): void
    {
        $items         = $this->todoList->items();
        $selectedIndex = $this->selectedIndex();

        $this->list->setItems(array_map($this->listItem(...), $items));
        $this->list->setSelectedIndex(min($selectedIndex, count($items) - 1));

        // The list stays in the widget tree while it has items, as taking it out takes its focus away
        $shown = $items === [] ? $this->emptyList : $this->list;
        if ($this->itemsArea->all() !== [$shown]) {
            $this->tui->setFocus($this->input);
            $this->itemsArea->clear();
            $this->itemsArea->add($shown);
        }
    }

    private function selectedIndex(): int
    {
        $selected = $this->list->getSelectedItem();

        return $selected === null ? 0 : max(0, $this->indexOfItemId($selected['value']));
    }

    private function indexOfItemId(string $itemId): int
    {
        foreach ($this->todoList->items() as $index => $item) {
            if ($item->id === $itemId) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * @return array{value: string, label: string}
     */
    private function listItem(TodoItem $item): array
    {
        $text = StringUtils::stripControlBytes($item->text);

        return [
            'value' => $item->id,
            'label' => $item->isDone()
                ? "[x] {$text}  (done {$item->doneAt?->format('Y-m-d H:i')})"
                : "[ ] {$text}",
        ];
    }
}
