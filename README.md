# dumont-ws

A todo list in the terminal.

## Running it

You need Docker.

```bash
docker compose run --rm app                        # opens the todo list
docker compose run --rm app bin/console app:time   # opens the time tracking
docker compose run --rm app vendor/bin/phpunit     # runs the tests
```

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

Open the time tracking with `bin/console app:time` (see above). It is a screen of its own, so you can keep the todo list open in one terminal and the time tracking in another. It shows the items of your todo list, each with the time spent on it as hours:minutes:seconds. Changes you make on the todo list show up by themselves.

| Key | What it does |
|---|---|
| Enter | starts the timer on the selected item, or stops it if it runs |
| ↑ ↓ | select an item |
| Esc, Ctrl+C | quit |

- Only one timer runs at a time: starting a timer on an item stops the timer on another item.
- A running timer counts up on the screen and shows when it was started.
- Timers keep running when you quit the screen, and also while it is closed. Open it again to see them.
- A timer cannot be started on an item that is done. Marking an item done, or deleting it, stops its timer.
- An item's time is the sum of the periods its timer ran. It is kept after the item is deleted.

The periods are kept in `var/data/time_periods.json`. Delete the file to start over.

## The prompt

The time tracking on this branch was built by a Claude Sonnet agent that was given this prompt and nothing else:

```text
You are working in the repository at /Users/manuel/git/dumont-masterclass-2026/dumont-ws (branch `main`). It is a small terminal application: a todo list. Read its README to see how to run it and its tests. PHP is not installed on this machine, so run everything the way the README describes.

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

## Under the guided regime

The branch `v1-guided` is `main` with luminor-meridian's quality and correctness gates: PHP CS Fixer, PHPStan at
level 10 with the ergebnis rules and the project's own rules, and architecture tests. Put on top of `v1-guided`, this
implementation fails all three gates, while its behaviour tests pass: 1 failed architecture test (4 offending files),
19 PHPStan errors, 8 files that violate the code style, and 95 of 96 tests passing.

The only conflict in code was in `TodoListDomainService`: `v1-guided`'s way of marking an item done, plus this
branch's one added line that stops the timer. All gates ran in check-only mode, so that nothing was fixed away.

### 1. Architecture test: time tracking uses the todo list's internals

`TimeTracking must not use Todo internals` fails: *Expecting 'App\TimeTracking' not to use 'App\Todo\Domain'*. Pest
reports the first offence; these are all of them:

| File | Uses the todo list's internal |
|---|---|
| `src/TimeTracking/Domain/Service/TimeTrackingDomainService.php` | `Todo\Domain\Service\TodoListDomainService`, `Todo\Domain\Model\TodoItem` |
| `src/TimeTracking/Domain/Model/TrackedItem.php` | `Todo\Domain\Model\TodoItem` |
| `src/TimeTracking/Infrastructure/Adapter/StopsTimersOfTodoItems.php` | implements `Todo\Domain\Service\ItemTimersInterface` |
| `tests/Unit/TimeTracking/TimeTrackingTestCase.php` | `Todo\Domain\Service\TodoListDomainService` |

The guided rules allow a vertical to reach another only through that vertical's facade, and the todo list has none.

### 2. PHPStan: 19 errors

- **The time periods' JSON store, 6 errors** (`JsonFileTimePeriodStore`, lines 33–39): it trusts what it decodes. The
  fields are `mixed` where strings are expected, and the result is no `list`.
- **Named arguments, 10 errors** (`TimeTrackingScreen`, lines 58–68): `new Style(padding: …, gap: …, bold: …,
  color: …, hidden: …, border: …)`. The ergebnis rules forbid named arguments in calls.
- **The tests' base class, 3 errors** (`TimeTrackingTestCase`): `setUp()`, `spent()` and `isRunning()` are not
  `final`, though their class is abstract.

### 3. Code style: 8 files

All of it is layout that `composer quality` fixes by itself: arguments of a call with four or more one below the
other, aligned constructor parameters and constants, and a class name imported instead of written fully qualified in a
PHPDoc. The files: `JsonFileTimePeriodStore`, `TimeTrackingDomainService`, `TimeTrackingScreen`,
`TodoListDomainService`, and four test files.

### What the gates did not catch

None of the project's own PHPStan rules reported anything. These choices pass every gate:

- **The direction of dependencies:** `TimeTracking` is not listed in the configuration of the rule for the direction
  between verticals, so it may depend on any vertical. Only the architecture test sees the breach.
- **The todo list knows timers:** its domain service calls `ItemTimersInterface`, a port of its own that time tracking
  implements. No rule forbids a vertical to declare such a port.
- **Stopping a timer is decided twice:** in `StopsTimer` and in `TimeTrackingDomainService`. No gate looks for that.
