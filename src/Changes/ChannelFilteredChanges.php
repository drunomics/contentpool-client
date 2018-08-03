<?php

namespace Drupal\contentpool_client\Changes;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\replication\Changes\Changes;

/**
 * {@inheritdoc}
 */
class ChannelFilteredChanges extends Changes {

  /**
   * {@inheritdoc}
   */
  protected function getFilter() {
    // We always apply the default replication settings..
    $replication_settings = $this->entityTypeManager->getStorage('replication_settings')->load('contentpool_client');
    $settings = $replication_settings->getParameters();

    $workspace = $this->entityTypeManager->getStorage('workspace')->load($this->workspaceId);

    if ($workspace && !empty($workspace->upstream->isEmpty())) {
      $workspace_pointer = $workspace->upstream->entity;

      if ($workspace_pointer && !empty($workspace_pointer->remote_pointer->isEmpty())) {
        $settings['remote'] = $workspace->remote_pointer->entity;
      }
    }

    return $this->filterManager->createInstance($replication_settings->getFilterId(), $settings);
  }

}
