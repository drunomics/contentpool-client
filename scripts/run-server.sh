#!/usr/bin/env bash

set -ex
cd `dirname $0`/..

# Run a web server via docker compose.
cd ../satellite-project/
ln -sf ../contentpool-client/scripts/devsetup-docker .

echo "Adding compose environment variables..."

cat - > .docker.defaults.env <<END
  # Be sure to sure the directory including the vcs checkout is shared as
  # docker volumes. This allows composer to link the install profile to the
  # "contentpool" directory and the link will work in the container.
  COMPOSE_CODE_DIR=../..
  WEB_DIRECTORY=satellite-project/web
  WEB_WORKING_DIR=/srv/default/vcs/satellite-project
END

echo "Running server..."
source dotenv/loader.sh
docker-compose up -d --build
