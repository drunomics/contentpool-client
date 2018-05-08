#!/usr/bin/env bash

set -e
set -x
cd `dirname $0`/..

# Run a web service via docker composer

[ -d ../satellite-project/devsetup-docker ] || \
  git clone https://github.com/drunomics/devsetup-docker.git --branch=1.x ../satellite-project/devsetup-docker

cd ../satellite-project/

echo "Adding compose environment variables..."

cat - > .docker.defaults.env <<END
  COMPOSE_PROJECT_NAME=satellite-project
  COMPOSE_FILE=devsetup-docker/docker-compose.yml:devsetup-docker/service-chrome.yml

  # Be sure to sure the directory including the vcs checkout is shared as
  # docker volumes. This allows composer to link the install profile to the
  # "contentpool" directory and the link will work in the container.
  COMPOSE_CODE_DIR=../..
  WEB_DIRECTORY=satellite-project/web
  WEB_WORKING_DIR=/srv/default/vcs/satellite-project
END

echo "Running server..."
source dotenv/loader.sh
docker-compose up -d
