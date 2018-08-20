<?php

namespace Drupal\contentpool_client\Plugin\rest\resource;

use Drupal\contentpool_client\RemotePullManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relaxed\SensitiveDataTransformer;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A rest resource that allows a remote to trigger a pull from a contentpool.
 *
 * @RestResource(
 *   id = "contentpool_client:trigger_pull",
 *   label = "Trigger pull from contentpool",
 *   uri_paths = {
 *     "create" = "/api/trigger-pull"
 *   }
 * )
 */
class TriggerPullResource extends ResourceBase {

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
   * The remote pull manager.
   *
   * @var \Drupal\contentpool_client\RemotePullManagerInterface
   */
  protected $remotePullManager;

  /**
   * RemoteRegistrationResource constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param array $serializer_formats
   *   An array of serializer formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\relaxed\SensitiveDataTransformer $sensitive_data_transformer
   *   The relaxed sensitive data transformer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\contentpool_client\RemotePullManagerInterface $remote_pull_manager
   *   The remote pull manager.
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

  /**
   * Implements get resource callback.
   *
   * @param mixed $data
   *   Data provided from http request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function post($data) {
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

    $this->logger->info('Remote contentpool server initiated pull.');

    return new ModifiedResourceResponse([], $status_code);
  }

}
