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
   * Does a pull for a single remote.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote on the contentpool server.
   * @param bool $process_immediately
   *   Forces the pull to happen immediately.
   */
  public function doPull(Remote $remote, $process_immediately = FALSE);

  /**
   * Checks which remotes need automatic pulls and invokes them.
   *
   * This is used by automatic cron runs.
   */
  public function checkAndDoAutopulls();

  /**
   * Checks whether an automatic pull should be issued for the given remote.
   *
   * The internally used stated for tracking whether an auto-pull is needed,
   * gets updated when TRUE is returned. Enable dry-run to avoid this.
   *
   * @param \Drupal\relaxed\Entity\Remote $remote
   *   The remote of the contentpool server.
   * @param bool $dry_run
   *   (optional) If enabeld, do not update internal state tracking when an
   *   auto-pull is needed.
   *
   * @return bool
   *   Whether an automatic pull should be issued.
   */
  public function isAutopullNeeded(Remote $remote, $dry_run = FALSE);

}
