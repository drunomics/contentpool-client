<?php

namespace Drupal\contentpool_client;

use Drupal\relaxed\Entity\Remote;

/**
 * Interface for RemoteAutopullManager.
 */
interface RemotePullManagerInterface {

  /**
   * Pulls from all registered remotes.
   *
   * @param bool $process_immediately
   *   (optional) Whether to process the queue tasks immediately.
   */
  public function pullAllRemotes($process_immediately = FALSE);

  /**
   * Checks which remotes need autopulls and invokes them.
   */
  public function checkAndDoAutopulls();

  /**
   * Checks the remote if an autopull is needed.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote on the contentpool server.
   * @param bool $dry_run
   *   (optional) If it's a dry run so no pull happens.
   */
  public function isAutopullNeeded(Remote $remote, $dry_run = FALSE);

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
