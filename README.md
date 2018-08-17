# Contentpool client module

 The contentpool distribution combines the publishing features of the Thunder
 distribution with powerful content API & replication features! 
 https://www.drupal.org/project/contentpool 


 [![Build Status](https://travis-ci.org/drunomics/contentpool-client.svg?branch=8.x-1.x)](https://travis-ci.org/drunomics/contentpool-client)

 
## Overview

This repository is a Drupal client module that can connect to a contentpool
server. You'll need a drupal project for installing it. Refer to "Installation"
for details.

## Installation

* composer require drunomics/contentpool-client

## Usage

### Triggering updates from remote contentpool server

The contentpool client can be configured to update content from configured
contentpool remote servers. The following options are possible:

#### Automatically with cron

In the configuration for a remote a pull interval can be specified. On a cron
run the interval will be checked and a pull scheduled if necessary. The pull
will run automatically at the end of cron execution, when Drupal processes
the workspace replication queue.

#### Manually with drush

The update can be triggered manually using the ```contentpool-client:pull-content```
command. It will pull from all remote servers that are marked as a Contentpool.

For example:

    drush cpc && drush queue-run workspace_replication


## Development

### Via the provided development setup

  For development purposes one can use the provided docker-compose setup. This
  is exactly the same setup as it's used by automated tests.

  First, ensure do you do not use docker-composer version 1.21, as it contains
  this regression: https://github.com/docker/compose/issues/5874

      docker-compose --version

  If so, update to version 1.22 which is known to work. See
  https://github.com/docker/compose/releases/tag/1.22.0
  
       ./scripts/create-project.sh
       ./scripts/run-server.sh
       ./scripts/init-project.sh
       # Optionally, launch a pool instance.
       ./scripts/launch-contentpool.sh
  
### On a custom site

  Just follow the above installation instructions and edit the module
  content at web/modules/contrib/contentpool-client. You can make sure it's a Git
  checkout by doing:
      
      rm -rf web/modules/contrib/contentpool-client
      composer install --prefer-source

## Running tests

### Locally, via provided scripts
  
 After installation with the provided scripts (see above) you can just launch
 the tests as the following:
 
     ./scripts/create-project.sh
     ./scripts/run-server.sh
     ./scripts/init-project.sh
     ./scripts/launch-contentpool.sh
     ./scripts/run-tests.sh

### Manually

Based upon the manual installation instructions you can launch tests with the
following helper script:

    ./web/modules/contrib/contentpool-client/tests/behat/run.sh

You might have to set some environment variables for it to work. It needs a
running a chrome with remote debugging enabled. Set the CHROME_URL variable.

## Credits

 - [Österreichischer Wirtschaftsverlag GmbH](https://www.drupal.org/%C3%B6sterreichischer-wirtschaftsverlag-gmbh): Initiator, Sponsor
 - [drunomics GmbH](https://www.drupal.org/drunomics): Concept, Development, Maintenance
