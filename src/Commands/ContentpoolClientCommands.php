<?php

namespace Drupal\contentpool_client\Commands;

use drunomics\ServiceUtils\Core\Config\ConfigFactoryTrait;
use drunomics\ServiceUtils\Core\Entity\EntityTypeManagerTrait;
use drunomics\ServiceUtils\Core\State\StateTrait;
use Drupal\contentpool_client\RemotePullManagerTrait;
use Drupal\contentpool_client\ReplicationHelperTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\multiversion\Entity\Index\RevisionIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\ConflictTrackerInterface;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ContentpoolClientCommands extends DrushCommands {

  use StateTrait;
  use ConfigFactoryTrait;
  use EntityTypeManagerTrait;
  use RemotePullManagerTrait;
  use ReplicationHelperTrait;

  /**
   * The content lock.
   *
   * @var \Drupal\content_lock\ContentLock\ContentLock
   */
  protected $contentLock;

  /**
   * The factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The conflict tracker.
   *
   * @var \Drupal\multiversion\Workspace\ConflictTrackerInterface
   */
  protected $conflictTracker;

  /**
   * The revision index.
   *
   * @var \Drupal\multiversion\Entity\Index\RevisionIndexInterface
   */
  protected $revisionIndex;

  /**
   * ContentpoolClientCommands constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The factory.
   * @param \Drupal\multiversion\Workspace\ConflictTrackerInterface $conflictTracker
   *   The conflict tracker.
   * @param \Drupal\multiversion\Entity\Index\RevisionIndexInterface $revisionIndex
   *   The revision index.
   */
  public function __construct(KeyValueFactoryInterface $keyValueFactory, ConflictTrackerInterface $conflictTracker, RevisionIndexInterface $revisionIndex) {
    // phpcs:ignore
    $this->contentLock = \Drupal::getContainer()->get('content_lock', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    $this->keyValueFactory = $keyValueFactory;
    $this->conflictTracker = $conflictTracker;
    $this->revisionIndex = $revisionIndex;
  }

  /**
   * Checks if the remote requires a pull.
   *
   * @usage contentpool-client:check
   *   drush cpc
   *
   * @command contentpool-client:check
   * @aliases cpc
   */
  public function check() {
    if ($this->getReplicationHelper()->checkReplication()) {
      $this->output()->writeln("There are new changes to be replicated.");
    }
    else {
      $this->output()->writeln("There are no changes to be replicated.");
    }
  }

  /**
   * Pulls content from the contentpool server.
   *
   * @usage contentpool-client:pull-content
   *   drush cppull
   *
   * @option queue Do not replicate immediately, but queue replication tasks.
   *
   * @command contentpool-client:pull-content
   * @aliases cppull
   */
  public function pullContent($options = ['queue' => FALSE]) {
    $this->getRemotePullManager()->pullAllRemotes(!$options['queue']);
  }

  /**
   * Runs the default setup routine.
   *
   * Configures a contentpool remote, a "replicator" user and points the live
   * workspace to replicate data with the contentpool.
   *
   * @usage contentpool-client:setup
   *   drush cps https://replicator:PASS@example.com/relaxed
   * @usage drush cps https://replicator:PASS@example.com/relaxed --replicator_pass=PASS
   *   Use specific password for replicator user configuration.
   *
   * @option replicator_pass Use given pass for replicator user instead of generated one.
   *
   * @command contentpool-client:setup
   * @aliases cps
   */
  public function setupModule($remote_url, $options = ['replicator_pass' => NULL]) {
    // Part one, setup the remote and workspace.
    $storage = $this->getEntityTypeManager()->getStorage('remote');
    $remote = $storage->load('contentpool');
    /** @var \Drupal\relaxed\Entity\RemoteInterface $remote */
    if (!$remote) {
      $remote = $storage->create();
      $remote->set('id', 'contentpool');
      $remote->set('label', 'Contentpool');
    }
    $remote->setThirdPartySetting('contentpool_client', 'is_contentpool', 1);
    $remote->setThirdPartySetting('contentpool_client', 'autopull_interval', 3600);
    $remote->set('uri', base64_encode($remote_url));
    $remote->save();
    $this->output()->writeln(dt('Configured contentpool remote.'));

    $storage = $this->getEntityTypeManager()->getStorage('workspace');
    $workspaces = $storage->loadByProperties(['machine_name' => 'live']);
    if (empty($workspaces)) {
      $this->io()->warning(dt('Missing live workspace, skipping workspace configuration!'));
    }
    $workspace = reset($workspaces);

    $storage = $this->getEntityTypeManager()->getStorage('workspace_pointer');
    $pointers = $storage->loadByProperties([
      'remote_pointer' => 'contentpool',
      'remote_database' => 'live',
    ]);
    if (empty($pointers)) {
      $this->io()->error(dt('Missing pointer to contentpool live workspace, aborting configuration!'));
      return;
    }
    $workspace->upstream->entity = reset($pointers);
    $workspace->pull_replication_settings->target_id = 'contentpool';
    $workspace->push_replication_settings = [];
    $workspace->save();

    // Part two, setup the user.
    $user_name = 'replicator';
    $storage = $this->getEntityTypeManager()->getStorage('user');
    $users = $storage->loadByProperties(['name' => $user_name]);
    if (empty($users)) {
      $pass = $options['replicator_pass'] ?? StringUtils::generatePassword();
      $user = $storage->create([
        'name' => 'replicator',
        'roles' => ['replicator'],
        'pass' => $pass,
      ]);
      $user->activate();
      $user->save();
      $this->io()->writeln(dt('Replicator user created with password "@pass".', ['@pass' => $pass]));

      $config = $this->getConfigFactory()->getEditable('relaxed.settings');
      $config->set('username', 'replicator');
      $config->set('password', base64_encode($pass));
      $config->save();
      $this->io()->writeln(dt('Configured replicator user to be used for replication.'));
    }
    else {
      $this->io()->warning(dt('The "replicator" user exists, skipping user configuration.'));
    }

    // Part three setup entity edit redirect.
    $remote_url_parts = parse_url($remote_url);
    $config = $this->getConfigFactory()->getEditable('entity_edit_redirect.settings');
    $config->set('append_destination', 1);
    $config->set('destination_querystring', 'trusted_destination');
    $config->set('base_redirect_url', $remote_url_parts['scheme'] . '://' . $remote_url_parts['host']);
    $path_patterns = $config->get('entity_edit_path_patterns');
    $path_patterns['node']['article'] = '/entity/{uuid}/edit';
    $config->set('entity_edit_path_patterns', $path_patterns);
    $config->save();
  }

  /**
   * Shows the last replication status.
   *
   * @usage contentpool-client:status
   *   drush cpst
   *
   * @command contentpool-client:status
   * @aliases cpst
   */
  public function status() {
    $status = $this->getReplicationHelper()->getLastReplicationStatusSummary();
    $this->logger()->notice(dt("Last replication status: %status", ['%status' => $status]));
  }

  /**
   * Removes conflicting revisions.
   *
   * Since there is no easy way to delete a revision, we purge the whole entity
   * having conflicting revisions.
   *
   * @usage contentpool-client:remove-conflicts
   *   drush cpconf
   *
   * @command contentpool-client:remove-conflicts
   * @aliases cpconf
   */
  public function removeConflictingRevisions() {
    $workspace = $this->getReplicationHelper()
      ->getActiveWorkspacePointer()
      ->getWorkspace();

    $conflicts = $this->conflictTracker
      ->useWorkspace($workspace)
      ->getAll();

    foreach ($conflicts as $uuid => $conflict) {
      $conflict_keys = array_keys($conflict);
      $rev = reset($conflict_keys);
      $rev_info = $this->revisionIndex
        ->useWorkspace($workspace->id())
        ->get("$uuid:$rev");

      $storage = $this->getEntityTypeManager()
        ->getStorage($rev_info['entity_type_id']);

      if (!empty($rev_info['revision_id']) && $entity = $storage->loadRevision($rev_info['revision_id'])) {
        // Make sure there is no lock preventing us to delete the entity.
        if ($lock_service = $this->contentLock) {
          if ($lock_service->isLockable($entity) && $lock_service->fetchLock($entity->id(), NULL, $entity->language()->getId(), $entity->getEntityTypeId())) {
            $lock_service->release($entity->id(), $entity->language(), NULL, NULL, $entity->getEntityTypeId());
          }
        }
        // Remove the revision from the index, so it will be ignored when
        // building the revision tree again on next replication of the entity.
        // This ensures the conflict does not popup again.
        $this->removeFromRevisionIndex($workspace, $entity);
        // Mark the conflicting revision as deleted.
        $storage->delete([$entity]);

        $this->logger->notice(dt('Deleted conflicting revision of entity %type %id with label %label and uuid %uuid.', [
          '%type' => $entity->getEntityTypeId(),
          '%id' => $entity->id(),
          '%label' => $entity->label(),
          '%uuid' => $entity->uuid(),
        ]));
      }
      $this->conflictTracker
        ->useWorkspace($workspace)
        ->resolveAll($uuid);
    }
  }

  /**
   * Removes the revision from the index.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity revision.
   *
   * @see \Drupal\multiversion\Entity\Index\RevisionIndex
   */
  protected function removeFromRevisionIndex(WorkspaceInterface $workspace, EntityInterface $entity) {
    $this->keyValueFactory->get('multiversion.entity_index.rev.' . $workspace->id())
      ->delete($entity->uuid() . ':' . $entity->_rev->value);
  }

  /**
   * Resets the replication history.
   *
   * Allows to start over replication, so next replication handles all changes
   * again.
   *
   * @usage contentpool-client:reset
   *   drush cpr
   *
   * @command contentpool-client:reset
   * @aliases cpr
   */
  public function reset() {
    $this->getReplicationHelper()->resetReplicationHistory();
    $this->logger()->notice(dt("The replication history has been reset."));
  }

  /**
   * Unblocks the replication.
   *
   * @usage contentpool-client:unblock-replication
   *   drush cpun
   *
   * @command contentpool-client:unblock-replication
   * @aliases cpun
   */
  public function unblockReplication() {
    // Reset flag if last replication failed.
    $this->getState()->set('workspace.last_replication_failed', FALSE);
    $this->logger()->notice(dt("The replication has been unblocked."));
  }

}
