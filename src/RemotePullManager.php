<?php

namespace Drupal\contentpool_client;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\relaxed\Entity\Remote;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\workspace\ReplicatorInterface;

/**
 * Helper class to get training references and backreferences.
 */
class RemotePullManager implements RemotePullManagerInterface {

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
   * The injected service to track conflicts during replication.
   *
   * @var \Drupal\multiversion\Workspace\ConflictTrackerInterface
   */
  protected $conflictTracker;

  /**
   * Constructs a RemoteAutopullManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StateInterface $state, ReplicatorInterface $replicator_manager, ConflictTrackerInterface $conflict_tracker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->replicatorManager = $replicator_manager;
    $this->conflictTracker = $conflict_tracker;
  }

  /**
   * @inheritdoc
   */
  public function pullAllRemotes() {
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();

    $counter = 0;
    foreach ($remotes as $remote) {
      // We try to do a pull from the remote.
      $this->doPull($remote);
      $counter++;
    }

    return $counter;
  }

  /**
   * @inheritdoc
   */
  public function checkAndDoAutopulls() {
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();

    $counter = 0;
    foreach ($remotes as $remote) {
      // We check if an autopull is needed based on settings and interval.
      if ($this->isAutopullNeeded($remote)) {
        $this->doPull($remote);
        $counter++;
      }
    }

    return $counter;
  }

  /**
   * @inheritdoc
   */
  public function isAutopullNeeded(Remote $remote) {
    // Never needed if autopull is disabled.
    if (!$remote->getThirdPartySetting('contentpool_client', 'autopull', 0)) {
      return;
    }

    $remote_state_id = 'remote_last_autopull_' . $remote->id();
    $autopull_interval = $remote->getThirdPartySetting('contentpool_client', 'autopull_interval', 3600);
    $last_autopull = $this->state->get($remote_state_id);

    // If autopull was never run or the intervals has been reached, we pull.
    if (!$last_autopull || ($last_autopull + $autopull_interval) < time()) {
      $this->doAutopull($remote);
    }

    // Set the curent time as last pull time.
    $this->state->set($remote_state_id, time());
  }

  /**
   * @inheritdoc
   */
  public function doPull(Remote $remote) {
    // Check for a workspace configuration whose upstream is this remote.
    $workspace_pointers = $this->entityTypeManager
      ->getStorage('workspace_pointer')
      ->loadByProperties(['remote_pointer' => $remote->id()]);

    /** @var \Drupal\workspace\Entity\WorkspacePointer $target */
    foreach ($workspace_pointers as $target) {
      if (!$target->getWorkspace()) {
        break;
      }

      $upstream = $target->getWorkspace()->upstream->entity;

      // Replication task creation and conflict handling, derived from workspace
      // update form.
      try {
        // Derive a replication task from the Workspace we are acting on.
        $task = $this->replicatorManager->getTask($target->getWorkspace(), 'pull_replication_settings');
        $response = $this->replicatorManager->update($upstream, $target, $task);

        if (($response instanceof ReplicationLogInterface) && ($response->get('ok')->value == TRUE)) {
          // Notify the user if there are now conflicts.
          $conflicts = $this->conflictTracker
            ->useWorkspace($target->getWorkspace())
            ->getAll();

          if ($conflicts) {
            drupal_set_message($this->t(
              '%workspace has been updated with content from %upstream, but there are <a href=":link">@count conflict(s) with the %target workspace</a>.',
              [
                '%upstream' => $upstream->label(),
                '%workspace' => $target->label(),
                ':link' => Url::fromRoute('entity.workspace.conflicts', ['workspace' => $target->getWorkspace()->id()])->toString(),
                '@count' => count($conflicts),
                '%target' => $upstream->label(),
              ]
            ), 'error');
          }
          else {
            drupal_set_message($this->t('An update of %workspace has been queued with content from %upstream.', [
              '%upstream' => $upstream->label(),
              '%workspace' => $target->label(),
            ]));
          }
        }
        else {
          drupal_set_message($this->t('Error updating %workspace from %upstream.', [
            '%upstream' => $upstream->label(),
            '%workspace' => $target->label(),
          ]), 'error');
        }
      }
      catch (\Exception $e) {
        watchdog_exception('Workspace', $e);
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

}
