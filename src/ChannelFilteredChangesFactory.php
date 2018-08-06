<?php

namespace Drupal\contentpool_client;

use Drupal\contentpool_client\Changes\ChannelFilteredChanges;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\multiversion\Entity\Index\SequenceIndexInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\replication\ChangesFactoryInterface;
use Drupal\replication\Plugin\ReplicationFilterManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ResolvedChangesFactory
 *
 * @package Drupal\contentpool_replication
 */
class ChannelFilteredChangesFactory implements ChangesFactoryInterface {

  /**
   * @var \Drupal\multiversion\Entity\Index\SequenceIndexInterface
   */
  protected $sequenceIndex;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * @var \Drupal\replication\Plugin\ReplicationFilterManagerInterface
   */
  protected $filterManager;

  /**
   * @var \Drupal\replication\Changes\ResolvedChanges[]
   */
  protected $instances = [];

  /**
   * @param \Drupal\multiversion\Entity\Index\SequenceIndexInterface $sequence_index
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   * @param \Drupal\replication\Plugin\ReplicationFilterManagerInterface $filter_manager
   */
  public function __construct(SequenceIndexInterface $sequence_index, EntityTypeManagerInterface $entity_type_manager, SerializerInterface $serializer, ReplicationFilterManagerInterface $filter_manager) {
    $this->sequenceIndex = $sequence_index;
    $this->entityTypeManager = $entity_type_manager;
    $this->serializer = $serializer;
    $this->filterManager = $filter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function get(WorkspaceInterface $workspace) {
    if (!isset($this->instances[$workspace->id()])) {
      $this->instances[$workspace->id()] = new ChannelFilteredChanges(
        $this->sequenceIndex,
        $workspace,
        $this->entityTypeManager,
        $this->serializer,
        $this->filterManager
      );
    }
    return $this->instances[$workspace->id()];
  }

}
