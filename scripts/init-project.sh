#!/usr/bin/env bash
cd `dirname $0`/../
set -ex

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
docker-compose exec web drush contentpool-client:setup http://replicator:changeme@contentpool-project.localdev.space/relaxed

# Add some subscription filters for testing.
docker-compose exec -T web drush config:set replication.replication_settings.contentpool parameters.filter \
 --input-format=yaml - <<END
field_channel:
  # Food
  - e4da9222-c270-43b7-abb9-2f83b1ad8716
field_tags:
  # Quantum
  - 92af4c88-0b17-41be-b6d8-306766ae3377
  # Cuba
  - 02c8cbd9-15b7-4231-b9ef-46c1ef37b233
END

# Add admin password for testing purposes.
docker-compose exec web drush upwd dru_admin changeme

# Add dev deps
docker-compose exec web composer require woohoolabs/yang
docker-compose exec web composer require php-http/guzzle6-adapter