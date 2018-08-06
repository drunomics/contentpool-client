<?php

namespace Drupal\contentpool_client\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\replication\Plugin\ReplicationFilter\EntityTypeFilter;

/**
 * Provides a filter based on entity type.
 *
 * Use the configuration "types" which is an array of values in the format
 * "{entity_type_id}.{bundle}".
 *
 * @ReplicationFilter(
 *   id = "contentpool_client",
 *   label = @Translation("Filter by types required for contentpool client"),
 *   description = @Translation("Replicate only entities that match channels specified in contentpool remote.")
 * )
 */
class ContentpoolClientFilter extends EntityTypeFilter {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'types' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(EntityInterface $entity) {
    if(parent::filter($entity)) {
      $configuration = $this->getConfiguration();
      $remote = $configuration['remote'];

      $channels = $remote->getThirdPartySetting('contentpool_client', 'channels', []);

      // If the entity has a channel field and it is not empty.
      if ($entity->hasField('field_channel') && !$entity->field_channel->isEmpty()) {
        $uuid = $entity->field_channel->entity->uuid();

        // If the entity references a channel that is specified in the remote
        // settings we allow it.
        if (in_array($uuid, array_keys($channels))) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
