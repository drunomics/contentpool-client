# Contentpool client module

 The contentpool distribution combines the publishing features of the Thunder
 distribution with powerful content API & replication features! 
 https://www.drupal.org/project/contentpool 
 
## Overview

This repository is a Drupal client module that can connect to a contentpool
server. You'll need a drupal project for installing it. Refer to "Installation"
for details.

## Installation

* composer require drunomics/contentpool-client



## Development

  Just follow the above setup instructions and edit the module
  content at web/modules/contrib/contentpool-client. You can make sure it's a Git
  checkout by doing:
      
      rm -rf web/modules/contrib/contentpool-client
      composer install --prefer-source

## Running tests

There are no tests to run yet. The setup for runnign tests is there and working though.

### Locally, via travis scripts
    
 You can just launch the provided scripts in the same order as travis:
 
     ./scripts/create-project.sh
     ./scripts/run-server.sh
     ./scripts/init-project.sh
     ./scripts/launch-contentpoo.sh

## Credits

 - [Österreichischer Wirtschaftsverlag GmbH](https://www.drupal.org/%C3%B6sterreichischer-wirtschaftsverlag-gmbh): Initiator, Sponsor
 - [drunomics GmbH](https://www.drupal.org/drunomics): Concept, Development, Maintenance
