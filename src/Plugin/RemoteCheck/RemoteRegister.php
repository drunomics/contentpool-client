<?php

namespace Drupal\contentpool_client\Plugin\RemoteCheck;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relaxed\Entity\RemoteInterface;
use Drupal\relaxed\Plugin\RemoteCheckBase;
use Drupal\relaxed\SensitiveDataTransformer;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Serializer;

/**
 * A remote check that auto-registers the site at the remote.
 *
 * @RemoteCheck(
 *   id = "remote_register",
 *   label = "Register on remote",
 * )
 */
class RemoteRegister extends RemoteCheckBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The related request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The sensitive data transformer.
   *
   * @var \Drupal\relaxed\SensitiveDataTransformer
   */
  protected $sensitiveDataTransformer;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * RemoteRegister constructor.
   *
   * @param array $configuration
   *   The configuration array.
   * @param $plugin_id
   *   The plugin id.
   * @param $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The guzzle http client.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The symfony request stack.
   * @param \Drupal\relaxed\SensitiveDataTransformer $sensitive_data_transformer
   *   The relaxed sensitive data transformer.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The core messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Serializer $serializer, ClientInterface $http_client, RequestStack $request_stack, SensitiveDataTransformer $sensitive_data_transformer, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->serializer = $serializer;
    $this->httpClient = $http_client;
    $this->requestStack = $request_stack;
    $this->sensitiveDataTransformer = $sensitive_data_transformer;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Creates an instance of the remote check.
   *
   * @param \Drupal\contentpool_client\Plugin\RemoteCheck\ContainerInterface $container
   *   The dependency injection container.
   * @param array $configuration
   *   The configuration array.
   * @param $plugin_id
   *   The plugin id.
   * @param $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('serializer'),
      $container->get('http_client'),
      $container->get('request_stack'),
      $container->get('relaxed.sensitive_data.transformer'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(RemoteInterface $remote) {
    $url = (string) $remote->uri();

    if (!$remote->getThirdPartySetting('contentpool_client', 'is_contentpool', 0)) {
      $this->result = TRUE;
      $this->message = $this->t('Not marked as a contentpool server.');
      return;
    }

    // As the remote targets the relaxed endpoint we have to parse the url
    // to get the base host.
    $url_parts = parse_url($url);
    $credentials = '';
    if (isset($url_parts['user']) && isset($url_parts['pass'])) {
      $credentials = $url_parts['user'] . ':' . $url_parts['pass'] . '@';
    }
    $base_url = $url_parts['scheme'] . '://' . $credentials . $url_parts['host'];

    if ($url_parts['scheme'] != 'https') {
      $this->messenger->addWarning($this->t('Warning: Insecure connection used for remote.'));
    }

    try {
      $response = $this->httpClient->post($base_url . '/_remote-registration?_format=json', $this->generateRegistrationPayload($remote));

      if ($response->getStatusCode() === 200) {
        $this->result = TRUE;
        $this->message = $this->t('Registration on remote is valid.');
        $message_body = json_decode($response->getBody()->getContents());
        $remote->setThirdPartySetting('contentpool_client', 'remote_site_uuid', $message_body->site_uuid);
      }
      else {
        $this->message = $this->t('Remote returns status code @status.', ['@status' => $response->getStatusCode()]);
      }
    }
    catch (\Exception $e) {
      $this->message = $e->getMessage();
      watchdog_exception('relaxed', $e);
    }
  }

  /**
   * Collects the data necessary for registration on remote.
   */
  private function generateRegistrationPayload(RemoteInterface $remote) {
    // Basic Site information.
    $config = $this->configFactory->get('system.site');
    $site_name = $config->get('name');
    $site_uuid = $config->get('uuid');
    $site_host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    $body = [
      'site_name' => $site_name,
      'site_domain' => $site_host,
      'site_uuid' => $site_uuid,
    ];

    // Additional information about the relaxed endpoint.
    $relaxed_config = $this->configFactory->get('relaxed.settings');
    $relaxed_root = $relaxed_config->get('api_root');
    $relaxed_password = $this->sensitiveDataTransformer->get($relaxed_config->get('password'));

    // We create an encoded uri for this site.
    $uri = new Uri($site_host . $relaxed_root);
    $uri = $uri->withUserInfo(
      $relaxed_config->get('username'),
      $relaxed_password
    );
    $body['endpoint_uri'] = (string) $uri;
    $serialized_body = $this->serializer->serialize($body, 'json');

    return [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::BODY => $serialized_body,
    ];
  }

}
