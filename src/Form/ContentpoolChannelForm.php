<?php

namespace Drupal\contentpool_client\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relaxed\Entity\RemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RemoteForm.
 */
class ContentpoolChannelForm extends FormBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs the object.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'remote_contentpool_channels';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, RemoteInterface $remote = NULL) {
    // Verify the connection to the remote.

    // Add channel subscription settings.
    list($channels, $topics) = \Drupal::service('contentpool_client.remote_pull_manager')->getChannelOptions($remote);
    $channel_uuids = $remote->getThirdPartySetting('contentpool_client', 'channels', []);

    if (empty($channels)) {
      $this->messenger->addWarning('No channels from contentpool server available.');
    }

    $form_state->set('remote', $remote);

    $form['contentpool_channels'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content channels'),
      '#description' => $this->t('Channels of content that this site is subscribed to.'),
      '#options' => $channels,
      '#default_value' => $channel_uuids,
      '#required' => TRUE,
    ];

    // Add topic subscription settings.
    $topic_uuids = $remote->getThirdPartySetting('contentpool_client', 'topics', []);

    $form['contentpool_topics'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content topics'),
      '#description' => $this->t('If no topic is selected all topics will be pulled.'),
      '#options' => $topics,
      '#default_value' => $topic_uuids,
    ];

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
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $remote = $form_state->get('remote');
    $selected_channels = array_filter($form_state->getValue('contentpool_channels'), function($value) {
      return $value !== 0;
    });

    $selected_topics = array_filter($form_state->getValue('contentpool_topics'), function($value) {
      return $value !== 0;
    });

    $remote->setThirdPartySetting('contentpool_client', 'channels', $selected_channels);
    $remote->setThirdPartySetting('contentpool_client', 'topics', $selected_topics);
    $remote->save();
  }

  /**
   * Access check handler.
   */
  public function access(AccountInterface $account, RemoteInterface $remote = NULL) {
    $is_contentpool = $remote->getThirdPartySetting('contentpool_client', 'is_contentpool', 0);
    return AccessResult::allowedIf($is_contentpool);
  }

}