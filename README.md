# dumont-ws

A sample Todo application.

## Running it

Everything runs in Docker; nothing but Docker is needed on the host.

```bash
docker compose run --rm app composer install       # installs dependencies
docker compose up                                  # serves http://localhost:8000
docker compose run --rm app vendor/bin/phpunit     # runs the tests
docker compose run --rm app bin/console debug:router
```

The list is kept in `var/data/todo_items.json` (the tests use `var/test/todo_items.json`). Delete the file to start
over.
