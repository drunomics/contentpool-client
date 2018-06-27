<?php

namespace Drupal\contentpool_client;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\workspace\ReplicatorInterface;

/**
 * Helper class to get training references and backreferences.
 */
class RemoteAutopullManager implements RemoteAutopullManagerInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

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
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ReplicatorInterface $replicator_manager, ConflictTrackerInterface $conflict_tracker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->replicatorManager = $replicator_manager;
    $this->conflictTracker = $conflict_tracker;
  }

  /**
   * @inheritdoc
   */
  public function checkAndDoAutopulls() {
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();

    $counter = 0;
    foreach ($remotes as $remote) {
      if ($this->isAutopullNeeded($remote)) {
        $this->doAutopull($remote);
        $counter++;
      }
    }

    return $counter;
  }

  /**
   * @inheritdoc
   */
  public function isAutopullNeeded($remote) {
    // Never needed if autopull is disabled.
    if(!$remote->getThirdPartySetting('contentpool_client', 'autopull', 0)) {
      return;
    }

    $remote_state_id = 'remote_last_autopull_' . $remote->id();
    $autopull_interval = $remote->getThirdPartySetting('contentpool_client', 'autopull_interval', 3600);
    $last_autopull = \Drupal::state()->get($remote_state_id);

    // If autopull was never run or the intervals has been reached, we pull.
    if (!$last_autopull || ($last_autopull + $autopull_interval) < time()) {
      $this->doAutopull($remote);
    }

    // Set the curent time as last pull time.
    \Drupal::state()->set($remote_state_id, time());
  }

  /**
   * @inheritdoc
   */
  public function doAutopull($remote) {
    // Check for a workspace configuration whose upstream is this remote.
    $workspace_pointers = \Drupal::service('entity_type.manager')
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
              '%workspace' => $target->label()
            ]));
          }
        }
        else {
          drupal_set_message($this->t('Error updating %workspace from %upstream.', [
            '%upstream' => $upstream->label(),
            '%workspace' => $target->label()
          ]), 'error');
        }
      } catch (\Exception $e) {
        watchdog_exception('Workspace', $e);
        drupal_set_message($e->getMessage(), 'error');
      }
    }
  }

}
