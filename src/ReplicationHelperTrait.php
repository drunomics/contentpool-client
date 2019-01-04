<?php

namespace Drupal\contentpool_client;

/**
 * Allows setter injection and simple usage of the service.
 */
trait ReplicationHelperTrait {

  /**
   * The replication helper service.
   *
   * @var \Drupal\contentpool_client\ReplicationHelper
   */
  protected $replicationHelper;

  /**
   * Gets the replication helper service.
   *
   * @return \Drupal\contentpool_client\ReplicationHelper
   *   The replication helper service.
   */
  protected function getReplicationHelper() {
    if (!$this->replicationHelper) {
      $this->replicationHelper = \Drupal::service('contentpool_client.replication_helper');
    }
    return $this->replicationHelper;
  }

}
