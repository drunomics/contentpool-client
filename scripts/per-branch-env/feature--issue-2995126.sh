#!/usr/bin/env bash
set -ex

export PRE_BUILD_COMMANDS="composer require drunomics/contentpool_replication:'2.x-dev as 2.0.1' --no-update"
