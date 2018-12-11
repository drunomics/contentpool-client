#!/bin/bash

set -ex
cd `dirname $0`/..

source scripts/util/per-branch-env.sh
./scripts/util/install-phapp.sh

[ ! -d ../satellite-project ] || (echo "Old project is still existing, please remove ../satellite-project." && exit 1)

composer create-project drunomics/drupal-project:* --no-install --no-interaction ../satellite-project

MODULE_DIR=`basename $PWD`
source scripts/util/get-branch.sh

cd ../satellite-project

echo "Adding module..."
composer config repositories.self vcs ../$MODULE_DIR
composer require drunomics/contentpool-client:"dev-$GIT_BRANCH"

echo Project created.

echo "Adding custom environment variables..."
cat - >> .defaults.env <<END
  INSTALL_PROFILE=standard
  CONTENTPOOL_BASE_URL=http://contentpool-project.localdev.space
END

echo "Setting up project..."
phapp setup localdev

if [[ ! -z "$PRE_BUILD_COMMANDS" ]]; then
  echo "Executing pre-build commands for branch $GIT_BRANCH"
  eval "$PRE_BUILD_COMMANDS"
fi

echo "Installed project with the following vendors:"
composer show
