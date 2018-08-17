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

MODULE_DIR=`basename $PWD`
GIT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

# Support detached HEADs.
# If a detached HEAD is found, we must give it a branch name. This is necessary
# as composer does not update metadata when dependencies are added in via Git
# commits, thus we need a branch.
if [[ $GIT_BRANCH = "HEAD" ]]; then
  GIT_BRANCH=tmp/$(date +%s)
  git checkout -b $GIT_BRANCH
fi

cd ../satellite-project

echo "Adding module..."
composer config repositories.self vcs ../$MODULE_DIR
composer require drunomics/contentpool-client:"dev-$GIT_BRANCH"

echo Project created.

echo "Adding custom environment variables..."
cat - >> .defaults.env <<END
  INSTALL_PROFILE=standard
END

echo "Setting up project..."
phapp setup localdev

echo "Installed project with the following vendors:"
composer show
