<?php

/**
 * @file
 * Drush integration for the contentpool_client module.
 */

/**
 * Implements hook_drush_command().
 */
function contentpool_client_drush_command() {
  $items['pull-content'] = [
    'description' => dt('Pulls content from remote contentpool servers.'),
    'aliases' => ['cpc'],
  ];

  return $items;
}

/**
 * Checks for expired media.
 */
function drush_pull_content() {
  /** @var \Drupal\contentpool_client\RemotePullManagerInterface $remote_pull_manager */
  $remote_pull_manager = \Drupal::service('contentpool_client.remote_pull_manager');
  $pull_count = $remote_pull_manager->pullAllRemotes();

  drush_print("Tried to pull from {$pull_count} remotes");
}
