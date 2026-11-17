# dumont-ws

A todo list in the terminal, with time tracking.

## Running it

You need Docker.

```bash
docker compose run --rm app                          # opens the todo list
docker compose run --rm app bin/console app:time     # opens the time tracking
docker compose run --rm app vendor/bin/phpunit       # runs the tests
```

Both can be open at the same time, in two terminals.

## The todo list

Type an item and press Enter to add it. Press Tab to move to the list.

| Key | What it does |
|---|---|
| Enter | adds the typed item, or marks the selected item done |
| ↑ ↓ | select an item |
| d or Delete | deletes the selected item |
| Tab, Shift+Tab | move between the input line and the list |
| Esc, Ctrl+C | quit |

## Time tracking

Shows the items of the todo list and the time spent on each. One timer runs at a time.

| Key | What it does |
|---|---|
| Enter | starts the timer of the selected item, or stops it if it runs |
| ↑ ↓ | select an item |
| Esc, Ctrl+C | quit |

Starting a timer stops the one that runs. Marking an item done or deleting it stops its timer.

The todo list is kept in `var/data/todo_items.json` and the times in `var/data/time_entries.json`. Delete the files to
start over.
