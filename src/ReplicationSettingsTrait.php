<?php

namespace Drupal\contentpool_client;

use drunomics\ServiceUtils\Core\Entity\EntityTypeManagerTrait;
use Drupal\relaxed\Entity\RemoteInterface;

/**
 * Allows to obtain replication settings for given remote.
 */
trait ReplicationSettingsTrait {

  use EntityTypeManagerTrait;

  /**
   * Gets the current replication settings for the given remote.
   *
   * @param \Drupal\relaxed\Entity\RemoteInterface $remote
   *   The remote.
   *
   * @return \Drupal\replication\Entity\ReplicationSettingsInterface
   *   The replication settings.
   */
  protected function getReplicationSettings(RemoteInterface $remote) {
    // We keep one replicatoin_settings entity per remote.
    $settings = $this->getEntityTypeManager()->getStorage('replication_settings')
      ->load($remote->id());
    if (!$settings) {
      // Auto-create a settings config entity with the defaults.
      $settings = $this->getEntityTypeManager()->getStorage('replication_settings')
        ->create([
          'id' => $remote->id(),
          'filter_id' => 'contentpool',
          'label' => 'Replicate ' . $remote->label() . ' entities',
          'parameters' => ['types' => ['node.article', 'taxonomy_term.channel']],
        ]);
      $settings->save();
    }
    return $settings;
  }

}
