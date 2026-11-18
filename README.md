# dumont-ws

A todo list in the terminal.

## Running it

You need Docker.

```bash
docker compose run --rm app                          # opens the todo list (bin/console app:todo)
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
