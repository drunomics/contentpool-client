<?php

namespace Drupal\contentpool_client\Plugin\RemoteCheck;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relaxed\Entity\RemoteInterface;
use Drupal\relaxed\Plugin\RemoteCheckBase;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @RemoteCheck(
 *   id = "remote_register",
 *   label = "Register on remote",
 * )
 */
Class RemoteRegister extends RemoteCheckBase implements ContainerFactoryPluginInterface {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * RemoteRegister constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Symfony\Component\Serializer\Serializer $serializer
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Serializer $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serializer = $serializer;
  }

  /**
   * @param \Drupal\contentpool_client\Plugin\RemoteCheck\ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(RemoteInterface $remote) {
    $url = (string) $remote->uri();

    // As the remote targets the relaxed endpoint we have to parse the url
    // to get the base host.
    $url_parts = parse_url($url);
    $base_url = $url_parts['scheme'] . '://' . $url_parts['user'] . ':' . $url_parts['pass'] . '@' . $url_parts['host'];

    /** @var \GuzzleHttp\Client $client */
    $client = \Drupal::httpClient();

    try {
      $response = $client->post($base_url . '/_remote-registration?_format=json', $this->generateRegistrationPayload());

      if ($response->getStatusCode() === 200) {
        $this->result = TRUE;
        $this->message = t('Registration on remote is valid.');
      }
      else {
        $this->message = t('Remote returns status code @status.', ['@status' => $response->getStatusCode()]);
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
  private function generateRegistrationPayload() {
    // Basic Site information.
    $config = \Drupal::config('system.site');
    $site_name = $config->get('name');
    $site_uuid = $config->get('uuid');
    $site_host = \Drupal::request()->getSchemeAndHttpHost();

    $body = [
      'site_name' => $site_name,
      'site_domain' => $site_host,
      'site_uuid' => $site_uuid,
    ];

    // Additional information about the relaxed endpoint.
    $relaxed_config = \Drupal::config('relaxed.settings');
    $relaxed_root = $relaxed_config->get('api_root');
    $relaxed_password = \Drupal::service('relaxed.sensitive_data.transformer')->get($relaxed_config->get('password'));

    // We create an encoded uri for this site.
    $uri = new Uri($site_host . $relaxed_root);
    $uri = $uri->withUserInfo(
      $relaxed_config->get('username'),
      $relaxed_password
    );
    $encoded = \Drupal::service('relaxed.sensitive_data.transformer')->set((string) $uri);
    $body['endpoint_uri'] = $encoded;

    $serialized_body = $this->serializer->serialize($body, 'json');

    return [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json'
      ],
      RequestOptions::BODY => $serialized_body,
    ];
  }
}
