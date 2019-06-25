<?php

/**
 * @file
 * Token integration for the contentpool_client module.
 */

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Implements hook_token_info().
 */
function contentpool_client_token_info() {
  $info['tokens']['node']['contentpool-canonical-url'] = [
    'name' => t('Contentpool Canonical URL'),
    'description' => t('The canonical URL as provided by the contentpool.'),
  ];
  return $info;
}

/**
 * Implements hook_tokens().
 */
function contentpool_client_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  if ($type == 'node' && !empty($data['node'])) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $data['node'];
    $replacements = [];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'contentpool-canonical-url':
          $url_options = ['absolute' => TRUE];
          if (isset($options['langcode'])) {
            $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
          }
          // Display the canonical URL unless it points to the current site,
          // then we generate the canonical URL as usual.
          if (isset($node->field_canonical_url) && $node->field_canonical_url->uri) {
            $canonical_url = $node->field_canonical_url->uri;
            $base_url = Url::fromUri('base:/', $url_options)->toString();
            if (strpos($canonical_url, $base_url) !== 0) {
              $url = $canonical_url;
            }
          }
          if (empty($url)) {
            $url = $node->toUrl('canonical', $url_options)->toString();
          }
          $replacements[$original] = !empty($options['sanitize']) ? new HtmlEscapedText($url) : $url;
          break;
      }
    }
    return $replacements;
  }

}
