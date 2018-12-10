<?php

namespace Drupal\contentpool_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\relaxed\Entity\Remote;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\ReplicatorInterface;

/**
 * Helper class to get replication related information.
 */
class ReplicationHelper {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The replicator manager.
   *
   * @var \Drupal\workspace\ReplicatorInterface
   */
  protected $replicatorManager;

  /**
   * The workspace manager.
   *
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a RemoteAutopullManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state interface.
   * @param \Drupal\workspace\ReplicatorInterface $replicator_manager
   *   The replicator manager.
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   *   The multiversion workspace manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StateInterface $state, ReplicatorInterface $replicator_manager, WorkspaceManagerInterface $workspace_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->replicatorManager = $replicator_manager;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * Gets the workspace pointer of currently active workspace.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The active workspace pointer.
   */
  public function getActiveWorkspacePointer() {
    /** @var \Drupal\multiversion\Entity\WorkspaceInterface $workspace */
    $workspace = $this->workspaceManager->getActiveWorkspace();
    /** @var \Drupal\workspace\WorkspacePointerInterface[] $pointers */
    $pointers = $this->entityTypeManager
      ->getStorage('workspace_pointer')
      ->loadByProperties(['workspace_pointer' => $workspace->id()]);
    return reset($pointers);
  }

  /**
   * Gets the workspace pointer of upstream of currently active workspace.
   *
   * @return \Drupal\workspace\WorkspacePointerInterface
   *   The upstream workspace pointer.
   */
  public function getUpstreamWorkspacePointer() {
    $workspace = $this->workspaceManager->getActiveWorkspace();
    if (isset($workspace->upstream)) {
      return $workspace->upstream->entity;
    }
  }

  /**
   * Gets last replication.
   *
   * The current active workspace and its upstream is respected when obtaining
   * last replication.
   *
   * @return \Drupal\workspace\Entity\Replication
   *   Replication entity of last executed replication.
   */
  public function getLastReplication() {
    // If no upstream is found then there could not be a replication.
    if (!$upstream_workspace_pointer = $this->getUpstreamWorkspacePointer()) {
      return NULL;
    }
    $active_workspace_pointer = $this->getActiveWorkspacePointer();
    $query = $this->entityTypeManager->getStorage('replication')->getQuery();
    $query->condition('source', $upstream_workspace_pointer->id());
    $query->condition('target', $active_workspace_pointer->id());
    $query->sort('changed', 'DESC');
    $query->range(0,1);
    $result = $query->execute();
    if (!$result) {
      return NULL;
    }
    $replication_id = reset($result);
    return Replication::load($replication_id);
  }

  /**
   * Restarts replication for currently active workspace and its upstream.
   */
  public function restartReplication() {
    // Reset flag if last replication failed.
    $this->state->set('workspace.last_replication_failed', FALSE);
    // If no upstream is found then there is nothing to restart.
    if (!$upstream_workspace_pointer = $this->getUpstreamWorkspacePointer()) {
      return;
    }
    $active_workspace_pointer = $this->getActiveWorkspacePointer();

    // Check if last replication is not already queued.
    $replication = $this->getLastReplication();
    if ($replication && $replication->replication_status->value == Replication::QUEUED) {
      $this->messenger()
        ->addMessage($this->t('An update of %workspace with content from %upstream has been already queued.', [
          '%upstream' => $upstream_workspace_pointer->label(),
          '%workspace' => $active_workspace_pointer->label(),
        ]));
      return;
    }

    try {
      // Derive a replication task from the Workspace we are acting on.
      $task = $this->replicatorManager->getTask($active_workspace_pointer->getWorkspace(), 'pull_replication_settings');
      $response = $this->replicatorManager->update($upstream_workspace_pointer, $active_workspace_pointer, $task);

      if (($response instanceof ReplicationLogInterface) && ($response->get('ok')->value == TRUE)) {
        $this->messenger()->addMessage($this->t('An update of %workspace has been queued with content from %upstream.', ['%upstream' => $upstream_workspace_pointer->label(), '%workspace' => $active_workspace_pointer->label()]));
      }
      else {
        $this->messenger()->addError($this->t('Error updating %workspace from %upstream.', ['%upstream' => $upstream_workspace_pointer->label(), '%workspace' => $active_workspace_pointer->label()]));
      }
    }
    catch (\Exception $e) {
      watchdog_exception('Workspace', $e);
      $this->messenger()->addError($e->getMessage());
    }
  }

}
