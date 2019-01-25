<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Mink\Exception\ExpectationException;

/**
 * Defines application features from the specific context.
 */
class FormContext extends RawDrupalContext {

  /**
   * @Then :element_selector value is empty
   */
  public function shouldBeEmpty($element_selector) {
    $result = $this->getSession()
      ->evaluateScript("jQuery(\"$element_selector\").val()");
    if ($result) {
      throw new ExpectationException('Value is not empty.', $this->getSession());
    }
  }

}
