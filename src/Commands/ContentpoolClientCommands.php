<?php

namespace Drupal\contentpool_client\Commands;

use Drupal\contentpool_client\RemotePullManagerTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ContentpoolClientCommands extends DrushCommands {

  use RemotePullManagerTrait;

  /**
   * Command description here.
   *
   * @usage contentpool-client:pull-content
   *   Usage description
   *
   * @command contentpool-client:pull-content
   * @aliases cpc
   */
  public function pullContent() {
    $pull_count = $this->getRemotePullManager()->pullAllRemotes();

    drush_print("Tried to pull from {$pull_count} remotes");
  }

}
