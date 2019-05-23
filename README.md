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

### Quick installation
Please refer to the quick installation instructions of the distribution at https://github.com/drunomics/contentpool#quick-installation.

### Regular installation

*  Install the module and it's dependencies as usual. It's recommended to do
   so via composer. This requires a composer-enabled Drupal projects, e.g. as
   provided by http://github.com/drunomics/drupal-project.

        composer require drunomics/contentpool-client
        drush en contentpool_client -y

* Configure the module to point to your contentpool instance. This can be done
  via the automated configruation or manually as following.

#### Automated configuration

Just run the provided drush command and provide it with the contentpool URL.
Be sure to provide correct authentication credentials of the replicator user
and to append the path `/relaxed` to your contentpool's base URL:

    drush cps http://replicator:YOURPASS@example.contentpool-project.localdev.space/relaxed
 
The drush command will check the connection to the contentpool and output errors
if there are connection issues.
 
#### Manual configuration

* Add a new "replicator" user for replication and grant it the "replicator"
  role. It will be used by the replication process to create the data via the
  CouchDB compatible /relaxedws API endpoint.

* Set the "replicator" user and its password `admin/config/relaxed/settings`.
  Do not change the API root.

* Add a new remote service "Contentpool" at `admin/config/services/relaxed/add`
  and enter the remote URL, including the /relaxedws suffix. After submitting
  the form, the connection will be checked. If there are no inidicated problems,
  it worked fine.

* Configure the live workspace at `admin/structure/workspace/` as follows:
  
  * Set "Assign default target workspace" to "Contentpool - Live".
  * Set "Replication settings on update" to "Replicate contentpool entities" 
  * Set "Replication settings on update" to "None"
  
That's it!

## Vue.js

For the contentpool filter selection, there is a dependency on Vue 2.5.17 or later.
Vue.js is loaded automatically via the pre-configured library of the vuejs module.

## Usage

The module configures all dependencies needed to replicate content from the
contentpool. That is, the necessary data model and the modules needed for the
replication. See http://www.drupaldeploy.org/ for more info about this modules.

The client can pull data automatically on a regular basis, or one can initiate
the data replication process manually. Finally, the client provides an API
endpoint which allows the contentpool to initiate an update instantly after
changes occurred.

### Pull data via the drush command

Just run the following command (requires Drush 9):

    drush cppull

### Pull data via the UI

 * As admin you should see an "Update" button in the top right corner of the
   toolbar. Use it to initiate an update.

 * Once done so, cron must be invoked to actually perform the replication. Do
   so; e.g. via the "Run cron" button at admin/reports/status.


### Automatic updates

The contentpool client can be configured to update content from configured
contentpool remote servers. The following options are possible:

#### Automatic pulls via Drupal's cron

In the configuration for a remote a pull interval can be specified. On a cron
run the interval will be checked and a pull scheduled if necessary. The pull
will run automatically at the end of cron execution, when Drupal processes
the workspace replication queue.

#### Automatic pulls via a manual cron entry

The update can be triggered manually using the ```contentpool-client:pull-content```
command. It will pull from all remote servers that are marked as a Contentpool.

For example:

    drush cppull

### Troubleshooting

When there are replication problems, be sure to:
 
* check status report. If there is an connection issues with the contentpool or
  replication errors it will be reported there.
* to clear the flood history on the contentpool when you have
  authentication troubles; e.g. run the query `DELETE FROM flood;`.
* check the recent log messages (watchdog) for replication errors.

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
