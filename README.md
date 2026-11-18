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

Open the time tracking in a second terminal, next to the todo list. It shows the items of the todo list with the time spent on each, as hours:minutes:seconds, and picks up changes made on the todo list by itself.

| Key | What it does |
|---|---|
| Enter | starts the timer on the selected item (this stops the timer on any other item) |
| s | stops the running timer |
| ↑ ↓ | select an item |
| Esc, Ctrl+C | quit |

Only one timer runs at a time. The time of an item is the sum of the periods its timer ran, and the screen counts it up live while the timer runs. It also shows when the running timer was started (in UTC).

A timer keeps running when you quit the time tracking, or the todo list. It stops when you mark its item done or delete it on the todo list, and no timer can be started on a done item.

The time is kept in `var/data/time_records.json`, also for deleted items. Delete the file to start over.

## The prompt

The time tracking on this branch was built by a Claude Sonnet agent, in a copy of `main` that held nothing but the
branch's files (no other branches, no history), from this prompt and nothing else:

```text
You are working in the repository at /private/tmp/claude-501/-Users-manuel-git-dumont-masterclass-2026/c2aaa202-d55f-4a7e-81d7-50d47f86d902/scratchpad/unguided/dumont-ws (branch `main`). It is a small terminal application: a todo list. Read its README to see how to run it and its tests. PHP is not installed on this machine, so run everything the way the README describes.

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
