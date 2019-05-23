#!/usr/bin/env bash
set -e
cd `dirname $0`/../../satellite-project/

set -x

# Verify coding style.
PHPCS=$(readlink -f vendor/bin/phpcs)
( cd ./web/modules/drunomics/contentpool-client && $PHPCS --colors --report-width=130 )

# Start chrome container.
docker-compose -f docker-compose.yml -f devsetup-docker/service-chrome.yml up -d chrome

# Launch tests inside a docker container, so name resolution works thanks to
# docker host aliases and the PHP environment is controlled by the container.
docker-compose exec cli ./web/modules/drunomics/contentpool-client/tests/behat/run.sh $@

# Stop chrome container.
docker-compose -f docker-compose.yml -f devsetup-docker/service-chrome.yml rm -sf chrome
