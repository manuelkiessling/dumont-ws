# dumont-ws

A todo list in the terminal.

## Running it

You need Docker.

```bash
docker compose run --rm app                          # opens the todo list
docker compose run --rm app bin/console app:time     # opens the time tracking
docker compose run --rm app composer test            # runs all tests: architecture, unit and integration
docker compose run --rm app composer quality         # fixes the code style, then checks it and runs PHPStan
docker compose run --rm app composer quality:check   # checks the code style without fixing it, and runs PHPStan
```

A change is done when `composer quality` and `composer test` pass.

How the application is built and how to work on it is written down in [AGENTS.md](AGENTS.md) and the books in
[`docs/`](docs/): the [archbook](docs/archbook.md), the [devbook](docs/devbook.md) and the [TUI book](docs/tuibook.md).

## Using it

Type an item and press Enter to add it. Press Tab to move to the list.

| Key | What it does |
|---|---|
| Enter | adds the typed item, or marks the selected item done |
| ↑ ↓ | select an item |
| d or Delete | deletes the selected item |
| Tab, Shift+Tab | move between the input line and the list |
| Esc, Ctrl+C | quit |

The list is kept in `var/data/todo_items.json`. Delete the file to start over.

## Tracking time

Open the time tracking in a second terminal, next to the todo list; both can be open at the same time. It shows the items of your todo list with the time spent on each, as hours, minutes and seconds (`01:02:03`). It shows the changes you make on the todo list without being reopened.

| Key | What it does |
|---|---|
| Enter | starts the timer on the selected item, or stops it if it runs |
| ↑ ↓ | select an item |
| Esc, Ctrl+C | quit |

- Only one timer runs at a time: starting a timer on an item stops the one that runs on another item.
- An item's time is the sum of the periods its timer ran. While a timer runs, its time counts up, and the screen shows when it was started (in UTC).
- Timers keep running when you quit the time tracking or the todo list.
- Marking an item done or deleting it on the todo list stops its timer. No timer can be started on an item that is done.
- The time of a deleted item is kept, and shown under "Deleted items".

The time is kept in `var/data/time_records.json`. Delete the file to start over.

## The prompt

The time tracking on this branch was built by a Claude Sonnet agent, starting from `v1-guided`, that was given this
prompt and nothing else. It is the prompt of `v2-unguided`, but for the name of the branch:

```text
You are working in the repository at /Users/manuel/git/dumont-masterclass-2026/dumont-ws (branch `v1-guided`). It is a small terminal application: a todo list. Read its README to see how to run it and its tests. PHP is not installed on this machine, so run everything the way the README describes.

Your task: add time tracking to the application. Here is what users need to be able to do:

- See the items of their todo list together with the time spent on each item, on a screen of its own, separate from the todo list screen. The time tracking screen must be openable with its own command, and it must be possible to have the todo list and the time tracking open at the same time in two terminals.
- Start a timer on an item, and stop it again. Only one timer runs at a time: starting a timer on an item stops the timer that is running on another item.
- An item's time is the sum of all the periods its timer ran. Only the time between starting and stopping counts, not the time since the item was created.
- Timers keep running when no screen is open. Quitting either screen does not stop a running timer.
- When an item is marked done or deleted on the todo list, its running timer stops.
- A timer cannot be started on an item that is done. If the user tries, the screen tells them why.
- Times are tracked and shown to the second (hours, minutes and seconds). While a timer runs, the time shown counts up live, and the screen shows when the running timer was started, to the second.
- The time tracking screen shows changes made to the todo list (in the other terminal) without having to be reopened.
- Time spent on an item is kept, also after the item is deleted.
- The README explains how to open and use the time tracking, in the same style as it explains the todo list.

The feature must be covered by automated tests, and all tests must pass when you are done. Make sure the application actually works when run, not only the tests.

Do not commit and do not push anything; leave all changes uncommitted in the working copy. When you are done, report what you built, which files you added or changed, how you verified it, and anything you are unsure about.
```

## How the gates shaped it

The result passes every gate: `composer quality` finds nothing to fix and no PHPStan error, and `composer test` passes
all 98 tests. Its only change to the gates' configuration is the line in `phpstan.dist.neon` that declares
`TimeTracking -> Todo`.

The agent's transcript shows that the gates guided it more by being read than by failing:

- **It read the rules before it wrote any code.** After the README, which says that a change is done when
  `composer quality` and `composer test` pass, it read `phpstan.dist.neon` and the documentation comment of every rule
  under `tools/PhpStan/Rules`, and the fixtures of the rules for Symfony events. Only then did it write its first file.
- **So it designed around them, and no architecture rule ever failed.** Time tracking reads the todo list only through
  a facade the agent gave the todo list (`TodoListFacadeInterface`, with `TodoItemDto`), and the todo list announces
  `TodoItemDoneSymfonyEvent` and `TodoItemDeletedSymfonyEvent`, which a listener in time tracking hears, so that the
  todo list knows nothing of timers. It declared the direction `TimeTracking -> Todo`, though the rule would have let
  an unlisted vertical depend on any other. When it first ran the gates, all of its code was written, and the
  architecture tests and the project's own PHPStan rules passed.
- **The one wall was PHPStan's level 10.** Its first `composer quality` stopped at 4 errors in
  `src/TimeTracking/Domain/Model/ItemTime.php`, where stopping a running period replaced an element of the list by its
  index:

  ```text
  66  Offset int<0, max>|null might not exist on list<App\TimeTracking\Domain\Model\Period>.
  66  Possibly invalid array key type int<0, max>|null.   (twice)
  68  Parameter #3 $periods of class App\TimeTracking\Domain\Model\ItemTime constructor expects
      list<App\TimeTracking\Domain\Model\Period>, non-empty-array<''|int<0, max>, App\TimeTracking\Domain\Model\Period>
      given.
  ```

  It changed course in one step: the periods are mapped, each running one stopped, which keeps the list a list. The
  next `composer quality` passed.
- **Its other two failures were its own tests:** a screen test of the live count-up, and a domain test, each fixed
  before the next full run.

What the transcript does not show is whether `composer quality`, which fixes the code style before it checks it,
changed any of the agent's files: the agent kept only the last 40 lines of that run.

What the gates did not prevent: the screen refreshes through `Tui::scheduleInterval()`, which `symfony/tui` marks
`@internal`, and a test helper of time tracking (`tests/Unit/TimeTracking/TimeTrackingWorld.php`) builds the todo
list's `TodoListDomainService` itself, which the architecture tests do not see, as they look at the application's
code, not at its tests.

The books in `docs/` came to `v1-guided` after this was built, and are merged into this branch. They name both as
conventions (the [TUI book](docs/tuibook.md#refreshing): only the TUI component's public API; the
[devbook](docs/devbook.md#tests): tests use another vertical only through its facade). The agent's code is left as it
was built; no check enforces either convention.
