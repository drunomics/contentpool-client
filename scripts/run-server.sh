#!/usr/bin/env bash

set -ex
cd `dirname $0`/..

# Run a web server via docker compose.
cd ../satellite-project/

echo "Running server..."
docker-compose up -d --build

# Allow access the contentpool via the traefik network.
docker network connect --alias satellite-project_cli_1 traefik satellite-project_cli_1
docker network connect --alias satellite-project_php_1 traefik satellite-project_php_1

echo "Waiting for mysql to come up..." && docker-compose exec cli /bin/bash -c "while ! echo exit | nc mariadb 3306; do sleep 1; done" >/dev/null
