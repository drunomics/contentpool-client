#!/usr/bin/env bash
set -ex

export PRE_BUILD_COMMANDS="composer require drunomics/behat-drupal-utils:'dev-feature/WV-2495 as 2.3.0' --no-update"
