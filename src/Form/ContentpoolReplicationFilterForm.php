<?php

namespace Drupal\contentpool_client\Form;

use Drupal\contentpool_client\RemotePullManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\relaxed\Entity\RemoteInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to setup additional content filters for the replication.
 */
class ContentpoolReplicationFilterForm extends FormBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The remote pull manager.
   *
   * @var \Drupal\contentpool_client\RemotePullManagerInterface
   */
  protected $remotePullManager;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\contentpool_client\RemotePullManagerInterface $remote_pull_manager
   *   The remote pull manager.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(MessengerInterface $messenger, RemotePullManagerInterface $remote_pull_manager, ClientInterface $http_client, RendererInterface $renderer) {
    $this->messenger = $messenger;
    $this->remotePullManager = $remote_pull_manager;
    $this->httpClient = $http_client;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('contentpool_client.remote_pull_manager'),
      $container->get('http_client'),
      $container->get('renderer')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'contentpool_replication_filter';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\relaxed\Entity\RemoteInterface|null $remote
   *   The remote entity.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, RemoteInterface $remote = NULL) {
    $filter_fields = [];

    $treeselect_filters = $this->fetchTermReferenceFilter($remote);

    foreach ($treeselect_filters as $field => $filter) {
      $filter_field = 'filter-' . $field;
      $filter_fields[] = $filter_field;
      $form['treeselect-' . $field] = $filter;
      $form[$filter_field] = [
        '#type' => 'hidden',
        '#attributes' => [
          'v-model' => 'treeselect_model_' . $field,
        ],
      ];
    }

    $form_state->set('remote', $remote);
    $form_state->set('filter_fields', $filter_fields);

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\relaxed\Entity\RemoteInterface $remote */
    $remote = $form_state->get('remote');

    $filter = [];
    foreach ((array) $form_state->get('filter_fields') as $form_field) {
      list(, $field) = explode("-", $form_field);
      $value = $form_state->getValue($form_field);
      if (!empty($value)) {
        $filter['node:article'][$field] = array_map('trim', explode(",", $value));
      }
    }

    $remote->setThirdPartySetting('contentpool_client', 'filter', $filter);
    $remote->save();
  }

  /**
   * Get options for the termreference filter.
   *
   * @param \Drupal\relaxed\Entity\RemoteInterface $remote
   *   Remote interface.
   *
   * @return string[]
   *   A list of render arrays which render treeselect filters.
   */
  protected function fetchTermReferenceFilter(RemoteInterface $remote) {
    if (!$remote->getThirdPartySetting('contentpool_client', 'is_contentpool', 0)) {
      return [];
    }

    $termreference_fields = [];

    $url = (string) $remote->uri();
    // As the remote targets the relaxed endpoint we have to parse the url
    // to get the base host.
    $url_parts = parse_url($url);
    $auth = [];
    if (isset($url_parts['user']) && isset($url_parts['pass'])) {
      $auth[] = $url_parts['user'];
      $auth[] = $url_parts['pass'];
    }
    $base_url = $url_parts['scheme'] . '://' . $url_parts['host'];

    try {
      $response = $this->httpClient->get($base_url . '/api/contentpool-term-reference-fields?entity_type=node&bundle=article', [
        'auth' => $auth,
      ]);
      if ($response->getStatusCode() == 200) {
        $termreference_fields = json_decode($response->getBody()->getContents(), TRUE);
      }
      else {
        $this->messenger->addError('Error fetching reference fields from contentpool.');
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError('Error fetching reference fields from contentpool.');
      watchdog_exception('contentpool_client', $e);
    }

    if (empty($termreference_fields)) {
      return [];
    }

    $treeselect_filters = [];
    $current_filter = $remote->getThirdPartySetting('contentpool_client', 'filter', []);
    foreach ($termreference_fields as $field => $field_data) {
      $value = isset($current_filter['node:article'][$field]) ? $current_filter['node:article'][$field] : NULL;
      $renderable = [
        '#theme' => 'treeselect_filter',
        '#field' => $field,
        '#label' => $field_data['label'],
        '#description' => $this->t('Term reference filter: only sync content with selected %term.', ['%term' => $field_data['label']]),
        '#attached' => [
          'library' => [
            'contentpool_client/treeselect_filter',
          ],
          'drupalSettings' => [
            'contentpoolClient' => [
              'treeselect' => [
                'data' => [
                  'treeselect_model_' . $field => $value,
                  'treeselect_options_' . $field => $this->mapTreeselectOptions($field_data['terms']),
                ],
              ],
            ],
          ],
        ],
      ];

      // Create own markup here otherwise the form renderer will remove
      // vue-attributes starting with `:`.
      $treeselect_filters[$field] = [
        '#type' => 'markup',
        '#markup' => $this->renderer->render($renderable),
      ];
    }

    return $treeselect_filters;
  }

  /**
   * Generate treeselect options array from the terms of the reference field.
   *
   * @param array $data
   *   API result data of a reference field.
   *
   * @return array
   *   Data array which is understood by the treeselect filter plugin.
   */
  protected function mapTreeselectOptions(array $data) {
    $options = [];
    foreach ($data as $item) {
      $option = [
        'id' => $item['id'],
        'label' => $item['label'],
      ];
      if (!empty($item['children'])) {
        // Map recursive.
        $option['children'] = $this->mapTreeselectOptions($item['children']);
      }
      $options[] = $option;
    }
    return $options;
  }

  /**
   * Access check handler.
   */
  public function access(AccountInterface $account, RemoteInterface $remote = NULL) {
    $access_result = AccessResult::allowed();

    $is_contentpool = $remote->getThirdPartySetting('contentpool_client', 'is_contentpool', 0);
    if (!$is_contentpool) {
      $access_result = AccessResult::forbidden();
    }
    else {
      $treeselect_filters = $this->fetchTermReferenceFilter($remote);
      if (empty($treeselect_filters)) {
        $access_result = AccessResult::forbidden();
      }
    }

    // Changes in the remote should invoke new access checks.
    $access_result->addCacheableDependency($remote);

    return $access_result;
  }

}
