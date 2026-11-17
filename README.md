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

Open the time tracking in a second terminal, next to the todo list:

```bash
docker compose run --rm app bin/console app:time
```

It shows the items of your todo list with the time spent on each, as hours:minutes:seconds. Changes you make on the todo list show up here without reopening it.

| Key | What it does |
|---|---|
| Enter | starts the timer on the selected item, or stops it if it is running |
| ↑ ↓ | select an item |
| Esc, Ctrl+C | quit |

- Only one timer runs at a time: starting a timer on an item stops the timer on any other item.
- A running timer counts up live, and the screen shows when it was started (UTC, to the second).
- An item's time is the sum of all the periods its timer ran.
- Timers keep running when you quit the time tracking, or when no screen is open.
- Marking an item done or deleting it on the todo list stops its timer. A timer cannot be started on a done item.
- The time spent on a deleted item is kept, and the item stays on the time tracking marked `(deleted)`.

The timers are kept in `var/data/time_entries.json`. Delete the file to start over.

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

The branch `v1-guided` is `main` (with its annotations and comments) plus luminor-meridian's quality and correctness
gates: PHP CS Fixer, PHPStan at level 10 with the ergebnis rules and the project's own rules, and architecture tests.
Put on top of `v1-guided`, this implementation fails all three gates, while its behaviour tests pass: 1 failed
architecture test, 50 PHPStan errors, 7 files that violate the code style, and 90 of 91 tests passing.

Three files had conflicts: `TodoListDomainService` and its test kept `v1-guided`'s code plus this branch's additions
(the call that stops the timer, the import of `TimerStopperInterface`); the README took this branch's version. All
gates ran in check-only mode, so that nothing was fixed away.

### 1. Architecture test: time tracking uses the todo list's internals

`TimeTracking must not use Todo internals` fails: *Expecting 'App\TimeTracking' not to use 'App\Todo\Domain'*. The
offending class is `src/TimeTracking/Domain/Service/TimeTrackingDomainService.php`, which names three of the todo
list's internals:

- `Todo\Domain\Service\TodoItemStoreInterface`: it reads the todo list's items from the todo list's store itself,
  past the todo list's domain service.
- `Todo\Domain\Service\TimerStopperInterface`: it implements a port that the todo list's domain declares, so that the
  todo list can stop a timer.
- `Todo\Domain\Model\TodoItem`.

Two tests do the same, `tests/Unit/TimeTracking/TimeTrackingDomainServiceTest.php` and `TimeTrackingScreenTest.php`,
which build the todo list's `TodoListDomainService`; the architecture test looks at the application's code only, so
it does not report them. The guided rules allow a vertical to reach another only through that vertical's facade, and
the todo list has none.

### 2. PHPStan: 50 errors

| Errors | Identifier | What |
|---|---|---|
| 1 | `noAssociativeArraysAcrossBoundaries.return` | the project's rule: `TimeTrackingDomainService::trackedItems()` returns an array that is no `list` |
| 4 | `missingType.iterableValue` | arrays without the type of their values: `trackedItems()`, `withTimersStopped()`, `TimeTrackingScreen::listItem()`, the in-memory test store's property |
| 14 | `argument.type` | mostly the consequence of the above; also the time entries' JSON store, and the lists `withTimersStopped()` returns |
| 13 | `property.nonObject`, `method.nonObject`, `encapsedStringPart.nonString` | the consequence of the above: the screen and the tests use a tracked item that PHPStan only knows as `mixed` |
| 3 | `return.type` | the JSON store and the in-memory store return no `list`, a test helper returns `mixed` |
| 12 | `ergebnis.noNamedArgument` | `new Style(padding: …, gap: …, bold: …, color: …, hidden: …, border: …)` in `TimeTrackingScreen` |
| 3 | `ergebnis.noIsset` | `isset()` in `TimeTrackingDomainService` (twice) and in `TimeTrackingScreenTest` |

Most of the errors go back to one line. The agent did annotate `trackedItems()`, but inside a one-line comment, after
a sentence:

```php
/** The items of the todo list, followed by deleted items that time was spent on. @return list<TrackedItem> */
public function trackedItems(): array
```

PHPDoc reads a tag only at the start of a line, so PHPStan never sees `@return list<TrackedItem>`: to PHPStan the
method returns an `array` of nothing known. The project's own rule reports that, and wherever the screen and the tests
use one of these items, PHPStan reports a property or a method on `mixed`.

The same happens once more: `withTimersStopped()` has `/** @param list<TimeEntry> $entries @return list<TimeEntry> */`,
two tags on one line, so its `@return` is read as part of the description of `$entries`. Its result is an `array` of
nothing known, and what `startTimer()` and `stopTimerOf()` store is no longer known to be a list.

The rest are the patterns of the first unguided attempt: the time entries' JSON store trusts what it decodes
(`JsonFileTimeEntryStore`, lines 29–36), and the screen's styles use named arguments.

### 3. Code style: 7 files

All of it is layout that `composer quality` fixes by itself, such as the arguments of a call with four or more one
below the other, and aligned parameters. The files: `JsonFileTimeEntryStore`, `TimeEntry`,
`TimeTrackingDomainService`, `TimeTrackingScreen`, `TodoListDomainService`, `JsonFileTimeEntryStoreTest` and
`TodoListScreenTest`.

### Compared with the first unguided attempt

The first attempt (tag `v2-unguided-attempt-1`) was built on `main` as it was before its annotations and comments were
removed. It yielded 19 PHPStan errors and nothing from the project's own rules: it wrote `@return list<…>` the way the
code it found did. This attempt, on a `main` without a single annotation to copy, wrote its annotations itself, two of
them where PHPDoc does not read them, and yields 50 errors, one of them from the project's own rule. It also reaches
deeper into the todo list: the first attempt asked the todo list's domain service for the items, this one reads the
todo list's store.
