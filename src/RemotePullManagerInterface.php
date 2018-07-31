<?php

namespace Drupal\contentpool_client;

use Drupal\relaxed\Entity\Remote;

/**
 * Interface for RemoteAutopullManager.
 */
interface RemotePullManagerInterface {

  /**
   * Pulls from all registered remotes.
   */
  public function pullAllRemotes();

  /**
   * Checks all remotes with enabled autopull if a new autopull has to be created.
   */
  public function checkAndDoAutopulls();

  /**
   * Checks the remote if an autopull is needed.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote on the contentpool server.
   */
  public function isAutopullNeeded(Remote $remote);

  /**
   * Does a pull for a single remote.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote on the contentpool server.
   * @param bool $process_immediately
   *   Forces the pull to happen immediately.
   */
  public function doPull(Remote $remote, $process_immediately = FALSE);

}
