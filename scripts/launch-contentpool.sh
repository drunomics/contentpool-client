#!/bin/bash

set -ex
cd `dirname $0`/..
source ./scripts/util/per-branch-env.sh
./scripts/util/install-phapp.sh

[ ! -d ../contentpool ] || (echo "Old install profile is still existing, please remove ../contentpool." && exit 1)

cd ..
git clone https://github.com/drunomics/contentpool.git --branch=${LAUNCH_CONTENTPOOL_GIT_BRANCH:-8.x-1.x} contentpool
# Show commit info.
git --git-dir=./contentpool/.git show -s

# Ensure the contentpool-client GIT_BRANCH variables are not inherited - those
# refer to the client.
unset GIT_BRANCH
unset GIT_CURRENT_BRANCH

./contentpool/scripts/create-project.sh
./contentpool/scripts/run-server.sh
./contentpool/scripts/init-project.sh

echo "Contentpool server project installed."
echo "SUCCESS."
