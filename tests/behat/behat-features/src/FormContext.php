<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Mink\Exception\ExpectationException;

/**
 * Defines application features from the specific context.
 */
class FormContext extends RawDrupalContext {

  /**
   * @Then :element_selector value should be :element_value
   */
  public function shouldNotBeEmpty($element_selector, $element_value) {
    $result = $this->getSession()
      ->evaluateScript("jQuery(\"$element_selector\").val()");
    if (!$result) {
      throw new ExpectationException('Value is empty.', $this->getSession());
    }
    elseif ($result !== $element_value) {
      throw new ExpectationException(sprintf("Expected value: %s, got: %s", $element_value, $result), $this->getSession());
    }
  }

}
