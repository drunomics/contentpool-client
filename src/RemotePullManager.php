<?php

namespace Drupal\contentpool_client;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\contentpool_client\Exception\ReplicationException;
use Drupal\relaxed\Entity\Remote;

/**
 * Helper class to get training references and backreferences.
 */
class RemotePullManager implements RemotePullManagerInterface {

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
   * The replicator helper service.
   *
   * @var \Drupal\contentpool_client\ReplicationHelper
   */
  protected $replicationHelper;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The queue plugin manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Datetime service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Constructs a RemoteAutopullManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state interface.
   * @param \Drupal\contentpool_client\ReplicationHelper $replication_helper
   *   The replication helper service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StateInterface $state, ReplicationHelper $replication_helper, QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_manager, LoggerChannelInterface $logger, TimeInterface $time, LockBackendInterface $lock) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
    $this->replicationHelper = $replication_helper;
    $this->logger = $logger;
    $this->time = $time;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public function pullAllRemotes($process_immediately = FALSE) {
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();

    $counter = 0;
    foreach ($remotes as $remote) {
      // We check if the remote is marked as a contentpool instance.
      if (!$remote->getThirdPartySetting('contentpool_client', 'is_contentpool', 0)) {
        return $counter;
      }

      // We try to do a pull from the remote.
      $this->doPull($remote, $process_immediately);
      $counter++;
    }

    return $counter;
  }

  /**
   * {@inheritdoc}
   */
  public function doPull(Remote $remote, $process_immediately = FALSE) {
    // Just define lock key.
    $lock_key = 'contentpool_client.remote_' . $remote->id() . '_is_active_pull';
    try {
      // First check if pull wasn't triggered already.
      $is_locked = $this->lock->lockMayBeAvailable($lock_key);
      if ($is_locked) {
        $message = $this->t('Skipped simultaneous pulls for remote %remote', [
          '%remote' => $remote->label(),
        ]);
        $this->logger->notice('Skipped simultaneous pulls for remote %remote', [
          '%remote' => $remote->label(),
        ]);
        $this->messenger()->addMessage($message);
        return;
      }
      // Lock to prevent simulation replications.
      $this->lock->acquire($lock_key);
      // Queue replication for currently active workspace.
      $this->replicationHelper->queueReplicationTaskWithCurrentActiveWorkspace();

      if ($process_immediately) {
        $this->processReplicationQueue();
        $message = $this->t('Content of remote %remote has been replicated with status %status.', [
          '%remote' => $remote->label(),
          '%status' => $this->replicationHelper->getLastReplicationStatusSummary(),
        ]);
        $this->logger->notice('Content of remote %remote has been replicated with status %status.', [
          '%remote' => $remote->label(),
          '%status' => $this->replicationHelper->getLastReplicationStatusSummary(),
        ]);
        $this->messenger()->addMessage($message);
      }
      else {
        $message = $this->t('Replicating content of remote %remote has been queued.', [
          '%remote' => $remote->label(),
        ]);
        $this->logger->notice('Replicating content of remote %remote has been queued.', [
          '%remote' => $remote->label(),
        ]);
        $this->messenger()->addMessage($message);
      }
    }
    catch (ReplicationException $exception) {
      $this->logger->error('Unable to write replication logs. Exception: @message', ['@message' => $exception->getMessage()]);
      $exception->printError();
    }
    finally {
      $this->lock->release($lock_key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAndDoAutopulls() {
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();

    $counter = 0;
    foreach ($remotes as $remote) {
      // We check if an auto-pull is needed based on settings and interval.
      if ($this->isAutopullNeeded($remote)) {
        $this->doPull($remote);
        $counter++;
      }
    }

    return $counter;
  }

  /**
   * {@inheritdoc}
   */
  public function isAutopullNeeded(Remote $remote, $dry_run = FALSE) {
    // Never needed if autopull is disabled.
    if ($remote->getThirdPartySetting('contentpool_client', 'autopull_interval', 3600) == 'never') {
      return;
    }

    $remote_state_id = 'remote_last_autopull_' . $remote->id();
    $autopull_interval = $remote->getThirdPartySetting('contentpool_client', 'autopull_interval', 3600);
    $autopull_interval -= 60;
    $last_autopull = $this->state->get($remote_state_id);

    // If autopull was never run or the intervals has been reached, we pull.
    $request_time = $this->time->getRequestTime();
    if (!$last_autopull || ($last_autopull + $autopull_interval) < $request_time) {
      if (!$dry_run) {
        // Set the current time as last pull time.
        $this->state->set($remote_state_id, $request_time);
      }
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Process only the workflow_replication queue.
   */
  protected function processReplicationQueue() {
    $info = $this->queueManager->getDefinition('workspace_replication');
    $this->queueFactory->get('workspace_replication')->createQueue();
    $queue_worker = $this->queueManager->createInstance('workspace_replication');

    $end = $this->time->getRequestTime() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
    $queue = $this->queueFactory->get('workspace_replication');
    $lease_time = isset($info['cron']['time']) ?: NULL;

    while ($this->time->getRequestTime() < $end && ($item = $queue->claimItem($lease_time))) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (RequeueException $e) {
        $queue->releaseItem($item);
        watchdog_exception('contentpool-client', $e);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        watchdog_exception('contentpool-client', $e);
        return;
      }
      catch (\Exception $e) {
        watchdog_exception('contentpool-client', $e);
      }
    }
  }

}
