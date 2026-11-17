#!/bin/sh
set -e

# Install the dependencies on first use, so that any command works right after cloning
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --quiet
fi

exec "$@"
