<?php

namespace Drupal\contentpool_client\Controller;

use Drupal\contentpool_client\Service\ReplicationHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Resets the replication of currently active workspace and its upstream.
 */
class ReplicationResetController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The replication helper.
   *
   * @var \Drupal\contentpool_client\Service\ReplicationHelper
   */
  protected $replicationHelper;

  /**
   * Constructor.
   *
   * @param \Drupal\contentpool_client\Service\ReplicationHelper $replication_helper
   *   The replication helper.
   */
  public function __construct(ReplicationHelper $replication_helper) {
    $this->replicationHelper = $replication_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('contentpool_client.replication_helper')
    );
  }

  /**
   * Resets the replication.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to status reports page.
   */
  public function resetReplication() {
    $this->replicationHelper->resetReplication();
    return new RedirectResponse('/admin/reports/status');
  }

}
