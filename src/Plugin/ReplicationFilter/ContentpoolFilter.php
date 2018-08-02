<?php

namespace Drupal\contentpool_replication\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\replication\Plugin\ReplicationFilter\EntityTypeFilter;
use Drupal\replication\Plugin\ReplicationFilter\ReplicationFilterBase;

/**
 * Provides a filter based on entity type.
 *
 * Use the configuration "types" which is an array of values in the format
 * "{entity_type_id}.{bundle}".
 *
 * @ReplicationFilter(
 *   id = "contentpool",
 *   label = @Translation("Filter by types required for contentpool"),
 *   description = @Translation("Replicate only entities that match the contentpool data model.")
 * )
 */
class ContentpoolFilter extends EntityTypeFilter {

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
    return parent::filter($entity);
  }

}
