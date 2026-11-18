# dumont-ws

A todo list in the terminal.

## Running it

You need Docker.

```bash
docker compose run --rm app                          # opens the todo list (bin/console app:todo)
docker compose run --rm app bin/console app:time     # opens the time tracking
docker compose run --rm app composer test            # runs all tests: architecture, unit and integration
docker compose run --rm app composer quality         # fixes the code style, checks it, runs PHPStan, checks dependencies
docker compose run --rm app composer quality:check   # the same, without fixing the code style
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

## Time tracking

`bin/console app:time` opens the time tracking: the items of your todo list with the time spent on each, as hours,
minutes and seconds (`0:01:05`). You can keep it open in a second terminal next to the todo list; it shows what you add,
finish or delete there within a second, without reopening it.

Select an item and press Enter to start its timer. A running timer is marked, shows when it was started (to the second,
in UTC) and counts up. Only one timer runs at a time: starting one stops the other. An item's time is the sum of all the
periods its timer ran.

| Key | What it does |
|---|---|
| Enter | starts the timer of the selected item (refused with a reason if the item is done) |
| s | stops the running timer |
| ↑ ↓ | select an item |
| Esc, Ctrl+C | quit |

Timers keep running when you quit: they are only stopped by `s`, by starting another, or by marking the item done or
deleting it on the todo list. The time spent stays in `var/data/time_periods.json`, also after the item is deleted.
Delete the file to start over.

## The prompt

The time tracking on this branch was built by a Claude Sonnet agent, in a copy of `v1-guided` that held nothing but
the branch's files (no other branches, and no history but the branch's last commit), from this prompt and nothing
else:

```text
You are working in the repository at /private/tmp/claude-501/-Users-manuel-git-dumont-masterclass-2026/c2aaa202-d55f-4a7e-81d7-50d47f86d902/scratchpad/guided/dumont-ws (branch `v1-guided`). It is a small terminal application: a todo list. Read its README to see how to run it and its tests. PHP is not installed on this machine, so run everything the way the README describes.

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

Never look into other branches, tags, or the git history and commits of this repository, and never look at anything outside this repository's folder: work only from the files of the branch as it is checked out.

Do not commit and do not push anything; leave all changes uncommitted in the working copy. When you are done, report what you built, which files you added or changed, how you verified it, and anything you are unsure about.
```

## How the gates and the books shaped it

The result passes every gate: `composer quality` finds nothing to fix, no PHPStan error and no dependency issue, and
`composer test` passes all 125 tests. Its only changes to the gates' configuration are the line in `phpstan.dist.neon`
that declares `TimeTracking -> Todo`, and the new data file with its test path in `config/services.yaml`, which the
configuration test checks.

The agent worked in 24 steps. The books, not the gates, did most of the steering:

- **It read the books before anything else.** After the README, its second step read `AGENTS.md` and all three books
  in `docs/`; then the todo list's code, its tests and the architecture tests, and four of the rules in
  `tools/PhpStan/Rules` (the two for Symfony events, the one for facade classes that need work of their own, and the
  one for DTOs beside a facade's). Only then did it write its first file.
- **It declared its dependencies before a check asked.** The archbook says every package the code uses is declared in
  `composer.json`, also one that comes along anyway, and the TUI book says so for `revolt/event-loop`. The agent
  checked which versions were installed and declared `psr/event-dispatcher`, `revolt/event-loop` and
  `symfony/event-dispatcher`; the dependency check never had to report them.
- **It built what the books describe:**
  - a facade for the todo list (`TodoListFacadeInterface` with `TodoItemDto`)
  - two facade events that the todo list announces (`ItemMarkedDoneSymfonyEvent`, `ItemDeletedSymfonyEvent`), and a
    listener in time tracking that hears them, so the todo list knows nothing of timers
  - a refresh of the time tracking screen through Revolt's `EventLoop::repeat()` in its command, using only the TUI
    component's public API
  - unit tests with a stand-in for the todo list's facade, and the clock frozen with `MockClock`
  - an integration test that wires the real services of both verticals, as the devbook allows

Where the gates stopped it, it corrected course in one step each:

- **PHPStan, level 10:** its integration test cast a container parameter, typed `mixed`, to a string. PHPStan reported
  `cast.string` and `argument.type`; the agent replaced the cast with `assertIsString()`.
- **The architecture tests:** its first full test run failed one test: the archbook had no section
  `## The TimeTracking vertical`. The agent wrote it and also described the
  Todo vertical's new facade and events in the archbook, and the new command in the devbook.

Besides that, PHP itself refused one of its edits with a syntax error, which it fixed in the next step.

Compared with the earlier run on `v1-guided` before its last 15 guardrails (kept as the tag `v2-guided-attempt-2`),
both of that run's departures from the books are gone:

- `revolt/event-loop` is declared.
- The integration test that uses the todo list's domain service is within the rules: the devbook now allows integration
  tests to wire the real services of several verticals.

Two things the agent noted itself, which no check covers:

- Two processes that write `var/data/time_periods.json` in the same instant could lose one of the writes. It wrote this
  down in the archbook.
- The time of a deleted item is kept in the file, but no screen shows it, because the time tracking screen lists the
  todo list's items.
