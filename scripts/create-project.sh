#!/bin/bash

PHAPP_VERSION=0.6.7

set -e
set -x
cd `dirname $0`/..

if ! command -v phapp > /dev/null; then
  echo Installing phapp...
  curl -L https://github.com/drunomics/phapp-cli/releases/download/$PHAPP_VERSION/phapp.phar > phapp
  chmod +x phapp
  sudo mv phapp /usr/local/bin/phapp
else
  echo Phapp version `phapp --version` found.
fi

[ ! -d ../satellite-project ] || (echo "Old project is still existing, please remove ../satellite-project." && exit 1)

phapp create --template=drunomics/drupal-project satellite-project ../satellite-project --no-interaction

GIT_COMMIT=$(git rev-parse HEAD)
MODULE_DIR=`basename $PWD`
cd ../satellite-project

echo "Adding module..."
composer config repositories.self path ../$MODULE_DIR
# Ensure it picks up the local repository.
# Travis does not want to use a symlink, so help by creatint it first.
ln -sf ../$MODULE_DIR web/modules/contrib/contentpool-client
composer require drunomics/contentpool-client:"dev-8.x-1.x#$GIT_COMMIT"

echo Project created.

echo "Adding custom environment variables..."
cat - >> .defaults.env <<END
  INSTALL_PROFILE=standard
END

echo "Setting up project..."
phapp setup localdev

echo "Installed project with the following vendors:"
composer show
