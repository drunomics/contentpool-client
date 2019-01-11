<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class ParagraphContext extends RawDrupalContext {

  /**
   * @Then /^I wait for the Instagram paragraph to be rendered$/
   */
  public function iWaitForTheInstagramParagraphToBeRendered() {
    $js_condition = <<<JS
return [...document.querySelectorAll('iframe')].filter(iframe => iframe.classList.contains('instagram-media-rendered')).length > 0
JS;

    $this->getSession()->wait(5000, $js_condition);
  }

  /**
   * @Given Paragraph :paragraph_type should be rendered
   */
  public function paragraphShouldBeRendered($paragraph_type) {
    $xpath = "//div[contains(@class, 'paragraph--$paragraph_type')]";
    $this->getSession()->getDriver()->find($xpath);
  }

}
