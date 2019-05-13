#!/usr/bin/env bash

set -ex
cd `dirname $0`/..

# Run a web server via docker compose.
cd ../satellite-project/

echo "Running server..."
docker-compose up -d --build
echo "Waiting for mysql to come up..." && docker-compose exec cli /bin/bash -c "while ! echo exit | nc mariadb 3306; do sleep 1; done" >/dev/null
