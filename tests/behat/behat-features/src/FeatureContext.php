<?php

/**
 * @file
 * The main behat context.
 */

use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  /**
   * Clean-up content we created.
   *
   * This is also run before each scenario  to e
   * Remove terms that we probably created. Nodes
   * are handled because when a user is deleted their content
   * is deleted as well. This not true for terms
   * that they create though.
   *
   * @BeforeFeature
   * @AfterScenario
   */
  public static function cleanupContent() {
    $nids = \Drupal::entityQuery('node')
      ->condition('title', 'BEHAT:', 'STARTS_WITH')
      ->execute();

    if (!$nids) {
      return;
    }

    $nodes = \Drupal::entityTypeManager('node')
      ->getStorage('node')
      ->loadMultiple($nids);

    if (!$nodes) {
      return;
    }

    \Drupal::entityTypeManager('node')
      ->getStorage('node')
      ->delete($nodes);
  }

  /**
   * Click first edit content link.
   *
   * @When I click first content edit link
   */
  public function clickFirstEditContentLink() {
    $xpath = '//ul[contains(@class, "dropbutton")]/li[contains(@class, "edit")]/a[text()="Edit"][1]';
    $edit_link_element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($edit_link_element)) {
      throw new Exception("Could not find edit link.");
    }
    $this->visitPath($edit_link_element->getAttribute('href'));
  }

  /**
   * Waits for x milliseconds.
   *
   * @Given I wait for :milliseconds ms
   */
  public function waitForSomeTime($milliseconds) {
    sleep($milliseconds / 1000);
  }

  /**
   * Focus some element.
   *
   * @When I focus the element :locator
   * @When I focus the field :locator
   */
  public function focusElement($locator) {
    $element = $this->getSession()->getPage()->find('css', $locator);

    if (!isset($element)) {
      throw new ElementNotFoundException($this->getDriver(), NULL, 'css', $locator);
    }

    $element->focus();
  }

  /**
   * Click some element.
   *
   * @When I click on the element :locator
   * @When I click in the field :locator
   */
  public function clickElement($locator) {
    $element = $this->getSession()->getPage()->find('css', $locator);

    if (!isset($element)) {
      throw new ElementNotFoundException($this->getDriver(), NULL, 'css', $locator);
    }

    $element->click();
  }

}
