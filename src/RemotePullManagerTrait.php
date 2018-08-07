<?php

namespace Drupal\contentpool_client;

/**
 * Allows setter injection and simple usage of the service.
 */
trait RemotePullManagerTrait {

  /**
   * The remote pull manager.
   *
   * @var \Drupal\contentpool_client\RemotePullManagerInterface
   */
  protected $remotePullManager;

  /**
   * Sets the remote pull manager object to use.
   *
   * @param \Drupal\contentpool_client\RemotePullManagerInterface $remote_pull_manager
   *   The remote pull manager object.
   *
   * @return $this
   */
  public function setRemotePullManagerInterface(RemotePullManagerInterface $remote_pull_manager) {
    $this->remotePullManager = $remote_pull_manager;
    return $this;
  }

  /**
   * Gets the remote pull manager.
   *
   * @return \Drupal\contentpool_client\RemotePullManagerInterface
   *   The remote pull manager.
   */
  protected function getRemotePullManager() {
    if (!$this->remotePullManager) {
      $this->remotePullManager = \Drupal::service('contentpool_client.remote_pull_manager');
    }
    return $this->remotePullManager;
  }

}
