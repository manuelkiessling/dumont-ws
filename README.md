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
