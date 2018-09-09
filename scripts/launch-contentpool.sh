#!/bin/bash

set -ex
cd `dirname $0`/..
source ./scripts/util/per-branch-env.sh
./scripts/util/install-phapp.sh

[ ! -d ../contentpool ] || (echo "Old install profile is still existing, please remove ../contentpool." && exit 1)

# Set GIT_BRANCH parameter to avoid inheriting it from the main repository.
export GIT_BRANCH=${LAUNCH_CONTENTPOOL_GIT_BRANCH:-8.x-1.x}

cd ..
git clone https://github.com/drunomics/contentpool.git --branch=$GIT_BRANCH contentpool
# Show commit info.
git --git-dir=./contentpool/.git show -s

./contentpool/scripts/create-project.sh
./contentpool/scripts/run-server.sh
./contentpool/scripts/init-project.sh

echo "Contentpool server project installed."
echo "SUCCESS."
