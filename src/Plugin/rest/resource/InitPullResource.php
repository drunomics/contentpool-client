<?php

namespace Drupal\contentpool_client\Plugin\rest\resource;

use Drupal\contentpool_client\RemotePullManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relaxed\SensitiveDataTransformer;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @RestResource(
 *   id = "contentpool_client:init_pull",
 *   label = "Init pull from contentpool",
 *   uri_paths = {
 *     "canonical" = "/_init-pull",
 *   }
 * )
 */
class InitPullResource extends ResourceBase {

  /**
   * The sensitive data transformer.
   *
   * @var \Drupal\relaxed\SensitiveDataTransformer
   */
  protected $sensitiveDataTransformer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\contentpool_client\RemotePullManagerInterface
   */
  protected $remotePullManager;

  /**
   * RemoteRegistrationResource constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param array $serializer_formats
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\relaxed\SensitiveDataTransformer $sensitive_data_transformer
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, SensitiveDataTransformer $sensitive_data_transformer, EntityTypeManagerInterface $entity_type_manager, RemotePullManagerInterface $remote_pull_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->sensitiveDataTransformer = $sensitive_data_transformer;
    $this->entityTypeManager = $entity_type_manager;
    $this->remotePullManager = $remote_pull_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('relaxed.sensitive_data.transformer'),
      $container->get('entity_type.manager'),
      $container->get('contentpool_client.remote_pull_manager')
    );
  }

  public function get($data) {
    $status_code = 404;

    // We check for the site uuid and do a pull if found.
    $sent_site_uuid = $data['site_uuid'];
    $remotes = $this->entityTypeManager->getStorage('remote')->loadMultiple();
    /** @var \Drupal\relaxed\Entity\RemoteInterface $remote */
    foreach ($remotes as $remote) {
      if ($remote_site_uuid = $remote->getThirdPartySetting('contentpool_client', 'remote_site_uuid')) {
        if ($remote_site_uuid == $sent_site_uuid) {
          $this->remotePullManager->doPull($remote, TRUE);
          $status_code = 200;
        }
      }
    }

    return new ResourceResponse([], $status_code);
  }

}
