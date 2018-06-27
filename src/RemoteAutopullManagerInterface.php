<?php

namespace Drupal\contentpool_client;

/**
 * Interface for RemoteAutopullManager
 */
interface RemoteAutopullManagerInterface {

  /**
   * Checks all remotes with enabled autopull if a new autopull has to
   * be created.
   */
  public function checkAndDoAutopulls();

  /**
   * Checks the remote if an autopull is needed.
   *
   * @param $remote
   */
  public function isAutopullNeeded($remote);

  /**
   * Does an autopull for a single remote.
   *
   * @param $remote
   */
  public function doAutopull($remote);

}
