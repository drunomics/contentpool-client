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
  public function shouldBe($element_selector, $element_value) {
    $result = $this->getSession()
      ->evaluateScript("jQuery(\"$element_selector\").val()");
    if (!$result) {
      throw new ExpectationException('Value is empty.', $this->getSession());
    }
    elseif ($result !== $element_value) {
      throw new ExpectationException(sprintf("Expected value: %s, got: %s", $element_value, $result), $this->getSession());
    }
  }

  /**
   * @Then :element_selector value should not be :element_value
   */
  public function shouldNotBe($element_selector, $element_value) {
    $result = $this->getSession()
      ->evaluateScript("jQuery(\"$element_selector\").val()");
    if (!$result) {
      throw new ExpectationException('Value is empty.', $this->getSession());
    }
    elseif ($result == $element_value) {
      throw new ExpectationException(sprintf("Expected value: %s, got: %s", $element_value, $result), $this->getSession());
    }
  }

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
