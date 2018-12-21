<?php

namespace Drupal\contentpool_client\Commands;

use drunomics\ServiceUtils\Core\Config\ConfigFactoryTrait;
use drunomics\ServiceUtils\Core\Entity\EntityTypeManagerTrait;
use Drupal\contentpool_client\RemotePullManagerTrait;
use Drupal\contentpool_client\ReplicationHelperTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

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

  use ConfigFactoryTrait;
  use EntityTypeManagerTrait;
  use RemotePullManagerTrait;
  use ReplicationHelperTrait;

  /**
   * Checks the remote for the content changes and does required pulls.
   *
   * @usage contentpool-client:check
   *   drush cpc
   *
   * @command contentpool-client:check
   * @aliases cpc
   */
  public function checkContent() {
    $check_count = $this->getRemotePullManager()->checkAndDoAutopulls();

    $this->output()->writeln("Checked and pulled {$check_count} remotes");
  }

  /**
   * Pulls content from the contentpool server.
   *
   * @usage contentpool-client:pull-content
   *   drush cppull
   *
   * @command contentpool-client:pull-content
   * @aliases cppull
   */
  public function pullContent() {
    $pull_count = $this->getRemotePullManager()->pullAllRemotes(TRUE);

    $this->output()->writeln("Pulled from {$pull_count} remotes");
  }

  /**
   * Runs the default setup routine.
   *
   * Configures a contentpool remote, a "replicator" user and points the live
   * workspace to replicate data with the contentpool.
   *
   * @usage contentpool-client:setup
   *   drush cps https://replicator:PASS@example.com/relaxed
   *
   * @command contentpool-client:setup
   * @aliases cps
   */
  public function setupModule($remote_url) {
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
    $pointers = $storage->loadByProperties(['remote_pointer' => 'contentpool', 'remote_database' => 'live']);
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
      $pass = StringUtils::generatePassword();
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
    $config->set('base_redirect_url', $remote_url_parts['scheme'] . '://' . $remote_url_parts['host']);
    $config->set('entity_edit_path_patterns', ['node' => ['article' => '/entity/{uuid}/edit']]);
    $config->save();
  }

  /**
   * Resets the replication status.
   *
   * @usage contentpool-client:reset
   *   drush cpr
   *
   * @command contentpool-client:reset
   * @aliases cpr
   */
  public function reset() {
    $this->getReplicationHelper()->resetReplication();
  }

}
