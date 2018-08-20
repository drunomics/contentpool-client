#!/bin/bash

set -ex
cd `dirname $0`/..

source ./scripts/util/per-branch-env.sh
./scripts/util/install-phapp.sh

[ ! -d ../satellite-project ] || (echo "Old project is still existing, please remove ../satellite-project." && exit 1)

phapp create --template=drunomics/drupal-project satellite-project ../satellite-project --no-interaction

MODULE_DIR=`basename $PWD`
source scripts/util/get-branch.sh

cd ../satellite-project

echo "Adding module..."
composer config repositories.self vcs ../$MODULE_DIR
composer require drunomics/contentpool-client:"dev-$GIT_CURRENT_BRANCH"

# For some reason this is not picked up automatically, for now do it manually.
composer require relaxedws/couchdb:dev-master#648d6ef relaxedws/replicator:dev-master#3b04a9f

echo Project created.

echo "Adding custom environment variables..."
cat - >> .defaults.env <<END
  INSTALL_PROFILE=standard
END

echo "Setting up project..."
phapp setup localdev

if [[ ! -z "$PRE_BUILD_COMMANDS" ]]; then
  echo "Executing pre-build commands for branch $GIT_BRANCH"
  eval "$PRE_BUILD_COMMANDS"
fi

# Run build on the host so we can leverage build caches.
phapp build

echo "Installed project with the following vendors:"
composer show
