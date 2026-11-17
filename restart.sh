#!/usr/bin/env bash

docker compose down --rmi local --volumes --remove-orphans   # remove the project's containers and its image
rm -rf vendor var                                            # remove installed dependencies and app data
docker compose build --no-cache --pull                       # rebuild from a freshly pulled php:8.5-cli
docker compose run --rm app                                  # reinstalls dependencies, then opens the todo list
