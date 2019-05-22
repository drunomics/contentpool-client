#!/usr/bin/env bash

set -ex
cd `dirname $0`/..

# Run a web server via docker compose.
cd ../satellite-project/

# Make the php service join the traefik network.
curl https://github.com/drunomics/devsetup-docker/commit/482cf0a0f37590bc7d7c09b3aa71213c51f0084e.patch | patch -p1 -R

echo "Running server..."
docker-compose up -d --build
echo "Waiting for mysql to come up..." && docker-compose exec cli /bin/bash -c "while ! echo exit | nc mariadb 3306; do sleep 1; done" >/dev/null
