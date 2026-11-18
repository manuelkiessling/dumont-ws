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

The time tracking shows the items of the todo list with the time spent on each. It is a screen of its own: open it
with `docker compose run --rm app bin/console app:time`, in a second terminal next to the todo list if you like. It
shows what you change on the todo list without being reopened.

| Key | What it does |
|---|---|
| ↑ ↓ | select an item |
| Enter | starts the timer on the selected item, or stops it if it runs |
| Esc, Ctrl+C | quit |

- Only one timer runs at a time: starting a timer on an item stops the one that runs on another item.
- An item's time is the sum of the periods its timer ran, shown as hours, minutes and seconds. While a timer runs, its
  time counts up and the screen shows when it was started (in UTC).
- Timers keep running when you quit the screen, or when no screen is open.
- Marking an item done or deleting it on the todo list stops its timer. No timer can be started on a done item.
- The time spent on an item is kept, also after the item is deleted, in `var/data/time_entries.json`.

## The prompt

The time tracking on this branch was built by a Claude Sonnet agent, in a copy of `v1-guided` that held nothing but
the branch's files (no other branches, and no history but the branch's last commit), from this prompt and nothing
else:

```text
You are working in the repository at /private/tmp/claude-501/-Users-manuel-git-dumont-masterclass-2026/c2aaa202-d55f-4a7e-81d7-50d47f86d902/scratchpad/dumont-ws (branch `v1-guided`). It is a small terminal application: a todo list. Read its README to see how to run it and its tests. PHP is not installed on this machine, so run everything the way the README describes.

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

The result passes every gate: `composer quality` finds nothing to fix and no PHPStan error, and `composer test` passes
all 98 tests. Its only change to the gates' configuration is the line in `phpstan.dist.neon` that declares
`TimeTracking -> Todo`.

The agent's transcript shows that it never ran into a gate:

- **It read the books before anything else.** After the README, its second step read `AGENTS.md` and all three books
  in `docs/`; then the todo list's code, `phpstan.dist.neon`, three of the rules in `tools/PhpStan/Rules` (the two
  rules for Symfony events, and the one for DTOs beside a facade's), and the todo list's tests. Only then did it write
  its first file.
- **Its first runs of the gates passed.** The first `composer test` passed all tests, and the first `composer quality`
  had nothing to report: PHP CS Fixer laid out 6 files anew, as `composer quality` does by itself, and PHPStan found no
  error. Nothing had to be changed in course.
- **It built what the books describe:** a facade for the todo list (`TodoListFacadeInterface` with `TodoItemDto`), two
  facade events that the todo list announces (`ItemMarkedDoneSymfonyEvent`, `ItemDeletedSymfonyEvent`) and a listener
  in time tracking that hears them, so that the todo list knows nothing of timers; a stylesheet both screens share in
  `Common/Presentation/Tui`; a refresh of the time tracking screen through Revolt's `EventLoop::repeat()` in its
  command, and no internal API of the TUI component; tests with a stand-in for the todo list's facade, and the clock
  frozen with `MockClock`.
- **It kept the books current:** the archbook describes the TimeTracking vertical, the devbook the new command, and the
  TUI book names `TimeTrackingScreen` as the example of a screen that refreshes.

Where it departs from the books, neither departure enforced by a check:

- `revolt/event-loop`, which the new command uses directly, is not declared in `composer.json`, as the TUI book asks.
  The agent said so itself: it did not want to change `composer.lock` without being able to check the update.
- The integration test `tests/Integration/TimeTracking/TimeTrackingWiringTest.php` uses the todo list's
  `TodoListDomainService` to mark an item done, to see the timer stop through the real wiring. The devbook wants tests
  to use another vertical only through its facade; the todo list's facade, though, offers no command to call.
