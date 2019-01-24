#!/usr/bin/env bash
set -e
cd `dirname $0`/../../satellite-project/
source dotenv/loader.sh

set -x

# Verify coding style.
PHPCS=$(readlink -f vendor/bin/phpcs)
( cd ./web/modules/drunomics/contentpool-client && $PHPCS --colors --report-width=130 )

# Start chrome container.
docker-compose -f devsetup-docker/service-chrome.yml up -d

# Launch tests inside a docker container, so name resolution works thanks to
# docker host aliases and the PHP environment is controlled by the container.
docker-compose exec web ./web/modules/drunomics/contentpool-client/tests/behat/run.sh $@

# Stop chrome container.
docker-compose -f devsetup-docker/service-chrome.yml stop
