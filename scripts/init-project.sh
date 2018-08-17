#!/usr/bin/env bash
cd `dirname $0`/../
set -e
set -x

cd ../satellite-project
source dotenv/loader.sh

# Run build on the host so we can leverage build caches.
phapp build
# Then install the project in the container.
docker-compose exec web phapp install --no-build

# Install the module.
docker-compose exec web drush en contentpool_client -y

# Run auto-setup with the default contentpool pass,
# see drunomics/contentpool:scripts/init-project.sh
docker-compose exec web drush cc drush
docker-compose exec web drush cps http://replicator:changeme@contentpool-project.localdev.space/relaxed
