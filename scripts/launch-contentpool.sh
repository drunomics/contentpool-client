#!/bin/bash

PHAPP_VERSION=0.6.7

set -e
set -x
cd `dirname $0`/../..

if ! command -v phapp > /dev/null; then
  echo Installing phapp...
  curl -L https://github.com/drunomics/phapp-cli/releases/download/$PHAPP_VERSION/phapp.phar > phapp
  chmod +x phapp
  sudo mv phapp /usr/local/bin/phapp
else
  echo Phapp version `phapp --version` found.
fi

[ ! -d ../contentpool ] || (echo "Old install profile is still existing, please remove ../contentpool." && exit 1)

git clone https://github.com/drunomics/contentpool.git --branch=${LAUNCH_CONTENTPOOL_GIT_BRANCH:-8.x-1.x} contentpool

./contentpool/scripts/create-project.sh
./contentpool/scripts/run-server.sh
./contentpool/scripts/init-project.sh

echo "Contentpool server project installed."
echo "SUCCESS."
