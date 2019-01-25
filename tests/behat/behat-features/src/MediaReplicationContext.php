<?php

/**
 * @file
 * The behat context for replication tests.
 */

use Behat\Mink\Exception\ExpectationException;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines features from the contentpool context.
 */
class MediaReplicationContext extends RawDrupalContext {

  /**
   * Media entity that were created during test.
   *
   * @var int[]
   *   Array of entity ids.
   */
  protected static $createdMediaEntities;

  /**
   * Media entity that were selected in article and replicated.
   *
   * @var int[]
   *   Array of entity ids.
   */
  protected static $replicatedMediaEntities;

  /**
   * Media entity id that is selected.
   *
   * @var int
   *   Currently selected media id in article.
   */
  protected static $currentlySelectedMedia;

  /**
   * Media entity id that was selected.
   *
   * @var int
   *   Previously selected media id in article.
   */
  protected static $previouslySelectedMedia;

  /**
   * Media entity id tested for unused.
   *
   * @var int
   *   Media entity id tested for unused.
   */
  protected static $unusedMedia;

  /**
   * I save media to array.
   *
   * @Then I save media to array with title :title
   */
  public function iSaveMediaToArray($title) {
    $args = explode('/', $this->getSession()->getCurrentUrl());
    $id = end($args);
    self::$createdMediaEntities[$id] = $title;
  }

  /**
   * @Then Save :element_selector value as currently selected
   */
  public function saveSelected($element_selector) {
    $result = $this->getSession()
      ->evaluateScript("jQuery(\"$element_selector\").val()");
    if (!$result) {
      throw new ExpectationException('Value is empty.', $this->getSession());
    }

    $parts = explode(':', $result);

    self::$currentlySelectedMedia = $parts[1] ?? NULL;
    self::$replicatedMediaEntities[] = $parts[1] ?? NULL;
  }

  /**
   * Checks if first unused media title exists.
   *
   * @Then Unused media is not replicated
   */
  public function checkUnusedMedia() {
    foreach (self::$createdMediaEntities as $id => $title) {
      if (!in_array($id, self::$replicatedMediaEntities)) {
        self::$unusedMedia = $id;
        $found_element = $this->getSession()
          ->evaluateScript("jQuery(\"*:contains('$title')\").length > 0");
        if ($found_element) {
          throw new ExpectationException('Element should not be found.', $this->getSession());
        }
        break;
      }
    }
  }

  /**
   * @Given I click on :index of created media in :entity_browser
   */
  public function iClickOnIndexOfCreatedMediaInEntityBrowser($index, $entity_browser) {
    $id = array_slice(array_keys(self::$createdMediaEntities), $index, 1)[0];
    $found_element = $this->getSession()
      ->evaluateScript("jQuery(\"#entity_browser_iframe_$entity_browser\").contents().find(\"#edit-entity-browser-select-media$id\").length > 0");
    if (!$found_element) {
      throw new ExpectationException('Element not found.', $this->getSession());
    }
    $this->getSession()
      ->evaluateScript("jQuery(\"#entity_browser_iframe_$entity_browser\").contents().find(\"#edit-entity-browser-select-media$id\").closest(\".views-row\").click()");
  }

  /**
   * @Given I click on edited media in :entity_browser
   */
  public function iClickOnCurrentOfCreatedMediaInEntityBrowser($entity_browser) {
    $id = self::$currentlySelectedMedia;
    $found_element = $this->getSession()
      ->evaluateScript("jQuery(\"#entity_browser_iframe_$entity_browser\").contents().find(\"#edit-entity-browser-select-media$id\").length > 0");
    if (!$found_element) {
      throw new ExpectationException('Element not found.', $this->getSession());
    }
    $this->getSession()
      ->evaluateScript("jQuery(\"#entity_browser_iframe_$entity_browser\").contents().find(\"#edit-entity-browser-select-media$id\").closest(\".views-row\").click()");
  }

  /**
   * @Given I click on unused media in :entity_browser
   */
  public function iClickOnUnsedMediaInEntityBrowser($entity_browser) {
    $unused = self::$unusedMedia;
    $found_element = $this->getSession()
      ->evaluateScript("jQuery(\"#entity_browser_iframe_$entity_browser\").contents().find(\"#edit-entity-browser-select-media$unused\").length > 0");
    if (!$found_element) {
      throw new ExpectationException('Element not found.', $this->getSession());
    }
    $this->getSession()
      ->evaluateScript("jQuery(\"#entity_browser_iframe_$entity_browser\").contents().find(\"#edit-entity-browser-select-media$unused\").closest(\".views-row\").click()");
  }

  /**
   * I edit last selected media on contentpool.
   *
   * @Given I edit last media on contentpool
   */
  public function editLastMedia() {
    $id = self::$currentlySelectedMedia;
    $found_element = $this->getSession()
      ->evaluateScript("jQuery(\"a[href^='/media/$id/edit']\")..length > 0");
    if (!$found_element) {
      throw new ExpectationException('Edit link not found.', $this->getSession());
    }
    $this->getSession()
      ->evaluateScript("jQuery(\"a[href^='/media/$id/edit']\").click()");
  }

  /**
   * @Then I should see title of currently selected media
   */
  public function currentlySelectedVisible() {
    $title = self::$createdMediaEntities[self::$currentlySelectedMedia];
    $found_element = $this->getSession()
      ->evaluateScript("jQuery(\"*:contains('$title')\").length > 0");
    if (!$found_element) {
      throw new ExpectationException('Element not found.', $this->getSession());
    }
  }

}
