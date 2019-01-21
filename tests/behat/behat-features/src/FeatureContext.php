<?php

/**
 * @file
 * The main behat context.
 */

use Drupal\DrupalExtension\Context\DrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

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

}
