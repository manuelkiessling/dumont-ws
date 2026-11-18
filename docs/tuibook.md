# TUI Book

How the application's screens are built and tested. A screen is the Presentation layer of a vertical; it is drawn
with the [Symfony TUI component](https://symfony.com/doc/current/tui.html) (`symfony/tui`, experimental). The
`TodoListScreen` is the worked example.


## A screen and its command

- **The screen**, `src/<Vertical>/Presentation/Tui/<Name>Screen.php`, is a service that takes its vertical's domain
  service (or another vertical's facade) in its constructor. It has:
  - `public static function createTui(TerminalInterface $terminal = new Terminal()): Tui`, which makes the `Tui` with
    the screen's stylesheet and keybindings, on the real terminal unless a test gives a virtual one;
  - `public function mount(Tui $tui): void`, which adds the widgets, wires their events to private methods, and
    shows the first state.
- **The command**, `src/<Vertical>/Presentation/Command/<Name>Command.php`, is an invokable `#[AsCommand(name:
  'app:<name>')]` class that makes the Tui, mounts the screen and runs it until the person quits:

```php
$tui = TodoListScreen::createTui();
$this->screen->mount($tui);
$tui->run();
```

Every screen is a process of its own; two screens open in two terminals share nothing but the files of the verticals'
stores. A screen that must show what another process changed reads it again (see [Refreshing](#refreshing)).


## What a screen does

- **Every key is one call of the domain service**, and afterwards the screen shows the state as the domain service
  has it. The screen decides no business rule.
- **A refusal** is caught as the vertical's `<Vertical>RefusedException` and its message is shown on the notice line
  (a `TextWidget` with the classes `notice` and `hidden`; the `hidden` class is removed while there is a message).
  What the person typed stays where it was.
- **Text from the data** (an item's text) passes `StringUtils::stripControlBytes()` before it becomes a label.
- **A list** is a `SelectListWidget` whose items are `['value' => <id>, 'label' => <text>]`. After the list is set
  anew, the screen selects the item that was selected before.
- **Focus:** a widget taken out of the tree loses its focus. A part that alternates between a list and an empty
  message is a `ContainerWidget` whose child is swapped only when it changes from one to the other.


## Keys

- The screen's own keys are a constant of actions and key IDs, given to the Tui as its `Keybindings`, which every
  widget merges with its own: `'quit' => ['ctrl+c']`, `'focus_next' => [Key::TAB]`, `'delete_item' => ['d',
  Key::DELETE]`.
- Quitting is a listener on `InputEvent` that stops the Tui for the `quit` action, plus `onCancel()` (Escape) of the
  focusable widgets.
- A key that a widget does not know is handled in the widget's `onInput()`, which matches it against
  `$widget->getKeybindings()` and returns `true` when it handled it.
- The help line at the bottom names every key of the screen.


## Styles

- A `StyleSheet` of selectors and `Style`s: widget classes (`InputWidget::class`, with `:focus`), and CSS-like
  classes (`.title`, `.notice`, `.hidden`, `.empty-list`, `.help`). Build a `Style` with its fluent methods
  (`new Style()->withBold()->withColor('cyan')`), never with named arguments.
- The focused box has a cyan border, every other a gray one, so that a person sees where Tab moved.
- A look that several screens share is one stylesheet in `src/Common/Presentation/Tui`, used by each screen's
  `createTui()`.


## Refreshing

The Tui draws anew after every key. A screen that must also show what changes without a key, such as a time that
counts up or what another terminal changed, offers a public `refresh()` that reads the state again and calls
`$this->tui->requestRender()`. Its command calls it on a timer of Revolt's event loop, on which the Tui runs, and
cancels the timer when the Tui stops:

```php
$refresh = EventLoop::repeat(1, $this->screen->refresh(...));
try {
    $tui->run();
} finally {
    EventLoop::cancel($refresh);
}
```

A vertical that uses Revolt directly declares `revolt/event-loop` in `composer.json`. Use only the TUI component's
public API: what it marks `@internal` (such as `Tui::scheduleInterval()`) may change without notice.


## Testing a screen

A screen test drives the screen key by key on a `VirtualTerminal` and reads what a person would see, played back by a
`ScreenBuffer`. See `tests/Unit/Todo/TodoListScreenTest.php`:

```php
$this->terminal = new VirtualTerminal(100, 24);
$this->tui      = TodoListScreen::createTui($this->terminal);
new TodoListScreen($todoList)->mount($this->tui);
$this->tui->start();                                   // and $this->tui->stop() in tearDown()

$this->terminal->simulateInput('Buy milk');
$this->terminal->simulateInput("\r");                  // Enter; "\t" Tab, "\x1b[B" Down, "\x1b" Escape, "\x03" Ctrl+C

$this->tui->processRender();
$screen = new ScreenBuffer($this->terminal->getColumns(), $this->terminal->getRows());
$screen->write($this->terminal->getOutput());
self::assertStringContainsString('[ ] Buy milk', $screen->getScreen());
```

- The screen's domain service gets in-memory ports, as in the domain service's tests.
- Times are frozen with `MockClock` (see the [devbook](devbook.md#time-in-tests)); a test of a refreshing screen moves
  the clock on and calls `refresh()` itself, as no event loop runs in a test.
- Quitting is tested with `$this->tui->isRunning()`.
