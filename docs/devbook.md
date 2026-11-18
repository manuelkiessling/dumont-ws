# Devbook

How to work on the application: running it, checking a change, and writing its tests.


## Running commands

PHP is not installed on the host; everything runs in the `app` container through Docker Compose. The first command
after cloning builds the image and installs the dependencies.

```bash
docker compose run --rm app                          # opens the todo list (bin/console app:todo)
docker compose run --rm app bin/console app:time     # opens the time tracking
docker compose run --rm app bin/console <command>    # any console command
docker compose run --rm app composer test            # all tests: architecture, unit and integration
docker compose run --rm app composer quality         # fixes the code style, then checks it and runs PHPStan
docker compose run --rm app composer quality:check   # checks the code style without fixing it, and runs PHPStan
docker compose run --rm app vendor/bin/pest tests/Unit/Todo/TodoListScreenTest.php   # one test file
```

The application writes its data to `var/data/` and its logs to `var/log/<environment>.log`; nothing is logged to the
terminal, where it would draw over a screen. Delete `var/data/` to start over.


## Definition of done

1. `composer quality` passes. It rewrites files (PHP CS Fixer); look at `git diff` afterwards.
2. `composer test` passes.
3. New behavior is covered by tests on the right level (see [Tests](#tests)).
4. The application works when run, not only its tests: open the screens you changed and use them.
5. The README says how a person uses what changed, and a book says what changed in how the application is built.

**Never make a check pass by weakening it**: no `@phpstan-ignore`, no baseline, no new `ignoreErrors` or excludes in
`phpstan.dist.neon`, `.php-cs-fixer.dist.php` or the architecture tests, and no skipped, deleted or loosened tests. The
one change to the checks' configuration that belongs to ordinary work is declaring a vertical's dependencies in
`phpstan.dist.neon` (see the [archbook](archbook.md#verticals-working-together)). If a rule seems to be in the way,
stop and ask.

While working, run the tests of what you change (`vendor/bin/pest <file>`), then both composer commands at the end.
PHPStan's messages name the rule and usually the fix; the rule's documentation is the class comment of the rule in
`tools/PhpStan/Rules`.


## Tests

Tests run with Pest, which runs the PHPUnit test classes too. Test classes end in `Test`.

| Level | Folder | What it tests |
|---|---|---|
| Architecture | `tests/Architecture` | The boundaries between verticals and layers (Pest's `arch()`) |
| Unit | `tests/Unit/<Vertical>` | A domain service with in-memory ports; a screen on a virtual terminal |
| Integration | `tests/Integration/<Vertical>` | What the container wires: stores on real files, listeners of facade events |
| Tools | `tests/Unit/Tools` | The project's PHPStan rules and PHP CS Fixer fixers, on the fixtures in `tests/Fixtures` |

- **Ports in unit tests:** an in-memory implementation in the test folder, as `tests/Unit/Todo/InMemoryTodoItemStore`.
- **Integration tests** extend `KernelTestCase`, take services from `self::getContainer()`, and delete their vertical's
  file under `var/test/` in `setUp()`.
- **Tests use only what a vertical offers others:** a test of one vertical that needs another one uses that vertical's
  facade, or a stand-in for its facade interface, never its internals.

### Time in tests

The application asks `DateAndTimeService` for the time, and `DateAndTimeService` asks Symfony's `Clock`. A test freezes
the time for everything at once, the services from the container included, and moves it on:

```php
$clock = new MockClock('2026-11-17 09:00:00');
Clock::set($clock);

$item = $this->todoList->addItem('Buy milk');
$clock->sleep(90);
```

After every test the clock is the real one again (`tests/Support/RestoresTheClockExtension`, registered in
`phpunit.dist.xml`), so a test needs not restore it. Expected times are made the same way the application makes them:
`DateAndTimeService::getDateTimeImmutable('2026-11-17 09:01:30')`, never `new DateTimeImmutable(…)`.

### Testing a screen

See the [TUI book](tuibook.md#testing-a-screen).


## Commit messages

A concrete title, then what the diff cannot explain: why this approach, what was considered and rejected, constraints
and deliberate omissions. No file-by-file list of changes.
