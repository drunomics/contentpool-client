#!/usr/bin/env bash
set -ex

export LAUNCH_CONTENTPOOL_GIT_BRANCH="feature/WV-3831"
export PRE_BUILD_COMMANDS="composer require drunomics/contentpool_data_model:'dev-feature/WV-3831 as 1.1.2' --no-update"
