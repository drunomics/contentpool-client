<?php

namespace Drupal\contentpool_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\workspace\Entity\Replication;
use Drupal\workspace\Entity\WorkspacePointer;
use Drupal\workspace\ReplicatorInterface;

/**
 * Helper class to operate better with replication entities.
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
   * The injected service to track conflicts during replication.
   *
   * @var \Drupal\multiversion\Workspace\ConflictTrackerInterface
   */
  protected $conflictTracker;

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
   * @param \Drupal\multiversion\Workspace\ConflictTrackerInterface $conflict_tracker
   *   The multiversion conflict tracker.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StateInterface $state, ReplicatorInterface $replicator_manager, WorkspaceManagerInterface $workspace_manager, ConflictTrackerInterface $conflict_tracker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->replicatorManager = $replicator_manager;
    $this->workspaceManager = $workspace_manager;
    $this->conflictTracker = $conflict_tracker;
  }

  /**
   * Gets the workspace pointer of currently active workspace.
   *
   * @return \Drupal\workspace\Entity\WorkspacePointer
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
   * @return \Drupal\workspace\Entity\WorkspacePointer
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
    $query->range(0, 1);
    $result = $query->execute();
    if (!$result) {
      return NULL;
    }
    $replication_id = reset($result);
    return Replication::load($replication_id);
  }

  /**
   *  Checks if given upstream has conflicts against given workspace.
   *
   * @param \Drupal\workspace\Entity\WorkspacePointer $source_workspace_pointer
   *   Source workspace pointer.
   * @param \Drupal\workspace\Entity\WorkspacePointer $target_workspace_pointer
   *   Target workspace pointer.
   * @param bool $silent
   *   Optional. Whether messages should be printed.
   *
   * @return bool|int
   *   False or number of conflicts.
   */
  public function hasConflicts(WorkspacePointer $source_workspace_pointer, WorkspacePointer $target_workspace_pointer, $silent = FALSE) {
    $conflicts = $this->conflictTracker
      ->useWorkspace($target_workspace_pointer->getWorkspace())
      ->getAll();

    if ($conflicts) {
      if (!$silent) {
        $this->messenger()
          ->addError($this->t('%workspace has been updated with content from %upstream, but there are <a href=":link">@count conflict(s) with the %upstream workspace</a>.', [
            '%upstream' => $source_workspace_pointer->label(),
            '%workspace' => $target_workspace_pointer->label(),
            ':link' => Url::fromRoute('entity.workspace.conflicts', [
              'workspace' => $target_workspace_pointer->getWorkspace()
                ->id(),
            ])->toString(),
            '@count' => count($conflicts),
          ]));
      }
      return count($conflicts);
    }
    return FALSE;
  }

  /**
   * Checks whether there is already a replication queued.
   *
   * @param bool $silent
   *   Optional. Whether messages should be printed.
   *
   * @return bool
   *   Whether replication was queued or not.
   */
  public function isReplicationQueued($silent = FALSE) {
    // Check if last replication is not already queued.
    $replication = $this->getLastReplication();
    if ($replication && $replication->replication_status->value == Replication::QUEUED) {
      if (!$silent) {
        $this->messenger()
          ->addMessage($this->t('An update of %workspace with content from %upstream has been already queued.', [
            '%upstream' => $replication->source->entity ? $replication->source->entity->label() : '',
            '%workspace' => $replication->target->entity ? $replication->target->entity->label() : '',
          ]));
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Create a replication task into the queue.
   *
   * @param \Drupal\workspace\Entity\WorkspacePointer $source_workspace_pointer
   *   Source workspace pointer.
   * @param \Drupal\workspace\Entity\WorkspacePointer $target_workspace_pointer
   *   Target workspace pointer.
   */
  public function queueReplicationTask(WorkspacePointer $source_workspace_pointer, WorkspacePointer $target_workspace_pointer) {
    // Queue replication if there no replication is queued yet.
    if (!$this->isReplicationQueued()) {
      try {
        // Derive a replication task from the Workspace we are acting on.
        $task = $this->replicatorManager->getTask($target_workspace_pointer->getWorkspace(), 'pull_replication_settings');
        $response = $this->replicatorManager->update($source_workspace_pointer, $target_workspace_pointer, $task);

        if (($response instanceof ReplicationLogInterface) && ($response->get('ok')->value == TRUE)) {
          if (!$this->hasConflicts($source_workspace_pointer, $target_workspace_pointer)) {
            $this->messenger()
              ->addMessage($this->t('An update of %workspace has been queued with content from %upstream.', [
                '%upstream' => $source_workspace_pointer->label(),
                '%workspace' => $target_workspace_pointer->label(),
              ]));
          }
        }
        else {
          $this->messenger()
            ->addError($this->t('Error updating %workspace from %upstream.', [
              '%upstream' => $source_workspace_pointer->label(),
              '%workspace' => $target_workspace_pointer->label(),
            ]));
        }
      }
      catch (\Exception $e) {
        watchdog_exception('Workspace', $e);
        $this->messenger()->addError($e->getMessage());
      }
    }
  }

  /**
   * Queue replication task with current active workspace.
   */
  public function queueReplicationTaskWithCurrentActiveWorkspace() {
    // If no upstream is found then no replication can be queued.
    if ($upstream_workspace_pointer = $this->getUpstreamWorkspacePointer()) {
      $active_workspace_pointer = $this->getActiveWorkspacePointer();
      $this->queueReplicationTask($upstream_workspace_pointer, $active_workspace_pointer);
    }
  }

  /**
   * Restarts replication for currently active workspace and its upstream.
   */
  public function restartReplication() {
    // Reset flag if last replication failed.
    $this->state->set('workspace.last_replication_failed', FALSE);

    // Queue replication for currently active workspace.
    $this->queueReplicationTaskWithCurrentActiveWorkspace();
  }

}
