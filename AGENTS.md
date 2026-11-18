# AGENTS.md

Instructions for AI coding agents working on this application. What matters to humans and agents alike lives in the
[README](README.md) and the books in [`docs/`](docs/); this file does not repeat it. The rules in the books are binding,
and most of them are enforced by `composer quality` and `composer test`.


## Read the relevant book before you start

| When the task involves… | Read |
|---|---|
| Anything | [README](README.md): what the application does, and the commands |
| PHP code: verticals and their layers, models, domain services, ports, refusals, stores, facades, facade events, dependencies between verticals, a new vertical, coding conventions | [archbook](docs/archbook.md) |
| Running commands, the definition of done, choosing and running checks, writing tests, the clock in tests, commit messages | [devbook](docs/devbook.md) |
| A terminal screen: its command, keys, refusals, styles, refreshing, testing it | [TUI book](docs/tuibook.md) |

Before introducing a pattern, look at how the application already does it and follow that. The `Todo` vertical is the
worked example of a vertical, `TodoListScreen` of a screen, and their tests of tests on every level.


## Ground your work in the code

- Verify facts about the codebase by reading the code or running commands. Where a book and the code disagree, the
  code is the truth: point out the mismatch and fix the book.
- If your change alters something a book describes (a convention, a command, a layout), update the book in the same
  change.


## Running commands

- PHP is not installed on the host. Run everything through `docker compose run --rm app …`, as the devbook says.
- Do not run commands that wait for a person, such as a screen without input piped in, in a way that never ends.


## Definition of done

The devbook's [Definition of done](docs/devbook.md#definition-of-done) holds: `composer quality` and `composer test`
pass, new behavior is tested on the right level, and the application works when run.

Report failures as they are. Never make a check pass by weakening it: no `@phpstan-ignore` comments, no baseline, no
new ignores or excludes in the checks' configuration, and no skipped, deleted or loosened tests. If a rule seems to be
in the way, stop and ask.
