<?php

namespace Drupal\contentpool_client\Controller;

use Drupal\contentpool_client\Exception\ReplicationException;
use Drupal\contentpool_client\ReplicationHelperTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Resets the replication of currently active workspace and its upstream.
 */
class ReplicationResetController extends ControllerBase {

  use ReplicationHelperTrait;

  /**
   * Resets the replication.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to status reports page.
   */
  public function resetReplication() {
    try {
      $this->getReplicationHelper()->resetReplication();
      $this->messenger()->addMessage($this->t('The replication status has been successfully reset.'));
    }
    catch (ReplicationException $e) {
      $e->printError();
    }
    return new RedirectResponse('/admin/reports/status');
  }

}
