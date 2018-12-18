<?php

namespace Drupal\contentpool_client\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;
use Drupal\relaxed\CouchdbReplicator;
use Drupal\replication\Entity\ReplicationLog;
use Drupal\replication\Entity\ReplicationLogInterface;
use Drupal\replication\ReplicationTask\ReplicationTask;
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
   * The replicator.
   *
   * @var \Drupal\relaxed\CouchdbReplicator
   */
  protected $replicator;

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

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
   * @param \Drupal\relaxed\CouchdbReplicator $replicator
   *   The replicator.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value
   *   The key value factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StateInterface $state, ReplicatorInterface $replicator_manager, WorkspaceManagerInterface $workspace_manager, ConflictTrackerInterface $conflict_tracker, CouchdbReplicator $replicator, KeyValueFactoryInterface $key_value, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->replicatorManager = $replicator_manager;
    $this->workspaceManager = $workspace_manager;
    $this->conflictTracker = $conflict_tracker;
    $this->replicator = $replicator;
    $this->keyValue = $key_value;
    $this->queueFactory = $queue_factory;
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
   * Gets replication entities.
   *
   * The current active workspace and its upstream is respected when obtaining
   * last replication.
   *
   * @param int $status
   *   The replication status (queued, replicated, failed, etc).
   * @param int $limit
   *   Optional. The number of items to be returned.
   *
   * @return \Drupal\workspace\Entity\Replication[]
   *   Loaded replication entities.
   */
  public function getReplications($status = NULL, $limit = NULL) {
    // If no upstream is found then there could not be a replication.
    if (!$upstream_workspace_pointer = $this->getUpstreamWorkspacePointer()) {
      return NULL;
    }
    $active_workspace_pointer = $this->getActiveWorkspacePointer();
    $query = $this->entityTypeManager->getStorage('replication')->getQuery();
    $query->condition('source', $upstream_workspace_pointer->id());
    $query->condition('target', $active_workspace_pointer->id());
    if (isset($status)) {
      $query->condition('replication_status', $status);
    }
    $query->sort('changed', 'DESC');
    if (isset($limit)) {
      $query->range(0, $limit);
    }
    $ids = $query->execute();
    return Replication::loadMultiple($ids);
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
    $replications = $this->getReplications(NULL, 1);
    return reset($replications);
  }

  /**
   * Checks if given upstream has conflicts against given workspace.
   *
   * @param \Drupal\workspace\Entity\WorkspacePointer $source_workspace_pointer
   *   Source workspace pointer.
   * @param \Drupal\workspace\Entity\WorkspacePointer $target_workspace_pointer
   *   Target workspace pointer.
   *
   * @return bool|int
   *   False or number of conflicts.
   */
  public function hasConflicts(WorkspacePointer $source_workspace_pointer, WorkspacePointer $target_workspace_pointer) {
    $conflicts = $this->conflictTracker
      ->useWorkspace($target_workspace_pointer->getWorkspace())
      ->getAll();
    return $conflicts ? count($conflicts) : FALSE;
  }

  /**
   * Checks whether there is already a replication queued.
   *
   * @return bool
   *   Whether replication was queued or not.
   */
  public function isReplicationQueued() {
    // Check if last replication is not already queued.
    $replication = $this->getLastReplication();
    return $replication && $replication->replication_status->value == Replication::QUEUED;
  }

  /**
   * Obtain replication id of remote in given source workspace.
   *
   * This cannot be reused on satellite site, so it needs to be rebuild.
   * @see \Relaxed\Replicator\Replication::generateReplicationId
   * @see \Drupal\workspace\Entity\WorkspacePointer::generateReplicationId
   *
   * @param \Drupal\workspace\Entity\WorkspacePointer $source_workspace_pointer
   *   The workspace pointer.
   * @param \Drupal\replication\ReplicationTask\ReplicationTask $task
   *   Replication task.
   *
   * @return string
   */
  protected function getReplicationId(WorkspacePointer $source_workspace_pointer, ReplicationTask $task) {
    /** @var \Doctrine\CouchDB\CouchDBClient $source */
    $source = $this->replicator->setupEndpoint($source_workspace_pointer);
    /** @var \Doctrine\CouchDB\HTTP\SocketClient $http_client */
    $http_client = $source->getHttpClient();
    // Build uuid manually. It could be obtained this via getUuid() method but
    // it calls the remote root relaxed endpoint to get it. Instead we build it
    // manually to not risk exceptions.
    $options = $http_client->getOptions();
    $remote_uuid = \md5($options['host'] . $options['port']);

    // Build replication log id.
    return \md5(
      $remote_uuid .
      $source->getDatabase() .
      $source->getDatabase() .
      var_export($task->getDocIds(), TRUE) .
      ($task->getCreateTarget() ? '1' : '0') .
      ($task->getContinuous() ? '1' : '0') .
      $task->getFilter() .
      '' .
      $task->getStyle() .
      var_export($task->getHeartbeat(), TRUE)
    );
  }

  /**
   * Get replication log entity by replication id.
   *
   * @param int $replication_id
   *   Replication id.
   *
   * @return \Drupal\replication\Entity\ReplicationLog
   *   Replication log entity.
   */
  protected function getReplicationLogByReplicationId($replication_id) {
    $entities = $this->entityTypeManager->getStorage('replication_log')->loadByProperties(['uuid' => $replication_id]);
    $replication_log = reset($entities);
    return $replication_log instanceof ReplicationLog ? $replication_log : NULL;
  }

  /**
   * Cleans up replication queue and queued replication entities.
   */
  protected function cleanUpQueue() {
    // Clean up queue replication queue.
    $replication_queue = $this->queueFactory->get('workspace_replication');
    $replication_queue->deleteQueue();
    // Clean up queued replication entities.
    $queued_replications = $this->getReplications(Replication::QUEUED);
    foreach ($queued_replications as $replication) {
      $replication->delete();
    }
  }

  /**
   * Create a replication task into the queue.
   *
   * @param \Drupal\workspace\Entity\WorkspacePointer $source_workspace_pointer
   *   Source workspace pointer.
   * @param \Drupal\workspace\Entity\WorkspacePointer $target_workspace_pointer
   *   Target workspace pointer.
   * @param bool $reset
   *   Optional. To reset replication state or not.
   */
  public function queueReplicationTask(WorkspacePointer $source_workspace_pointer, WorkspacePointer $target_workspace_pointer, $reset = FALSE) {
    // Queue replication if there no replication is queued yet.
    try {
      // All the queue tasks pending in database must be vanished, since it may
      // contain outdated state (outdated replication filters) and additionally
      // its processing would update `since` argument, so we wouldn't get all
      // the changes.
      if ($reset) {
        $this->cleanUpQueue();
      }

      $error = FALSE;
      $replication_log = ReplicationLog::create();
      // Derive a replication task from the Workspace we are acting on.
      $task = $this->replicatorManager->getTask($target_workspace_pointer->getWorkspace(), 'pull_replication_settings');

      // Do not create redundant items, if the replication is queued already.
      if (!$this->isReplicationQueued()) {
        $replication_log->save();
        $response = $this->replicatorManager->update($source_workspace_pointer, $target_workspace_pointer, $task);

        if (($response instanceof ReplicationLogInterface) && ($response->get('ok')->value == TRUE)) {
          if ($conflicts = $this->hasConflicts($source_workspace_pointer, $target_workspace_pointer)) {
            $error = TRUE;
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
        }
        else {
          $error = TRUE;
          $this->messenger()
            ->addError($this->t('Error updating %workspace from %upstream.', [
              '%upstream' => $source_workspace_pointer->label(),
              '%workspace' => $target_workspace_pointer->label(),
            ]));
        }
      }

      if ($reset) {
        // Reset the replication sequence by clearing up replication log entity
        // and key value uuid storage for current workspace which also holds
        // the replication log status.
        $replication_log_id = $this->getReplicationId($source_workspace_pointer, $task);
        $replication_log = $this->getReplicationLogByReplicationId($replication_log_id);
        if ($replication_log) {
          $replication_log->delete();
        }
        $key_value = $this->keyValue->get('multiversion.entity_index.uuid.' . $target_workspace_pointer->id());
        if ($key_value->get($replication_log_id)) {
          $key_value->delete($replication_log_id);
        }
      }

      if (!$error) {
        $this->messenger()
          ->addMessage($this->t('An update of %workspace has been queued with content from %upstream.', [
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

  /**
   * Queue replication task with current active workspace.
   *
   * @param bool $reset
   *   Optional. To reset replication state or not.
   */
  public function queueReplicationTaskWithCurrentActiveWorkspace($reset = FALSE) {
    // If no upstream is found then no replication can be queued.
    if ($upstream_workspace_pointer = $this->getUpstreamWorkspacePointer()) {
      $active_workspace_pointer = $this->getActiveWorkspacePointer();
      $this->queueReplicationTask($upstream_workspace_pointer, $active_workspace_pointer, $reset);
    }
  }

  /**
   * Restarts replication for currently active workspace and its upstream.
   */
  public function restartReplication() {
    // Reset flag if last replication failed.
    $this->state->set('workspace.last_replication_failed', FALSE);

    // Queue replication for currently active workspace and reset replication
    // state, so the next replication will include all the changes.
    $this->queueReplicationTaskWithCurrentActiveWorkspace(TRUE);
  }

}
