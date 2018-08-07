<?php

namespace Drupal\contentpool_client;

use Drupal\relaxed\Entity\RemoteInterface;

/**
 * Interface for RemoteAutopullManager.
 */
interface RemotePullManagerInterface {

  /**
   * Pulls from all registered remotes.
   */
  public function pullAllRemotes();

  /**
   * Checks which remotes need autopulls and invokes them.
   */
  public function checkAndDoAutopulls();

  /**
   * Checks the remote if an autopull is needed.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote on the contentpool server.
   */
  public function isAutopullNeeded(RemoteInterface $remote);

  /**
   * Does a pull for a single remote.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote on the contentpool server.
   * @param bool $process_immediately
   *   Forces the pull to happen immediately.
   */
  public function doPull(RemoteInterface $remote, $process_immediately = FALSE);

  /**
   * Provides a list of channel options from the contentpool server.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   */
  public function getChannelOptions(RemoteInterface $remote);

}
