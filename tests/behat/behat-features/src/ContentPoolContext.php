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
class ContentPoolContext extends RawDrupalContext {

  /**
   * Satellite base url.
   *
   * @var string
   */
  protected $satelliteBaseUrl;

  /**
   * Contentpool base url.
   *
   * @var string
   */
  protected $contentpoolBaseUrl;

  /**
   * @BeforeScenario
   */
  public function before($scope) {
    if (!getenv('PHAPP_BASE_URL')) {
      throw new Exception('Missing satellite base URL.');
    }
    $this->satelliteBaseUrl = getenv('PHAPP_BASE_URL');
    if (!getenv('CONTENTPOOL_BASE_URL')) {
      throw new Exception('Missing contentpool base URL.');
    }
    $this->contentpoolBaseUrl = getenv('CONTENTPOOL_BASE_URL');
  }

  /**
   * I open the contentpool.
   *
   * @Given I open the contentpool
   */
  public function openContentpool() {
    $this->visitContentpoolPath('/');
  }

  /**
   * I visit path on contentpool.
   *
   * @param string $path
   *   Given path.
   *
   * @Given I visit path :path on contentpool
   */
  public function visitContentpoolPath($path) {
    $this->setMinkParameter('base_url', $this->contentpoolBaseUrl);
    $this->visitPath($path);
  }

  /**
   * I open the satellite.
   *
   * @Given I open the satellite
   */
  public function openSatellite() {
    $this->visitSatellitePath('/');
  }

  /**
   * I visit path on satellite.
   *
   * @param string $path
   *   Given path.
   *
   * @Given I visit path :path on satellite
   */
  public function visitSatellitePath($path) {
    $this->setMinkParameter('base_url', $this->satelliteBaseUrl);
    $this->visitPath($path);
  }

  /**
   * Login to contentpool on user login page.
   */
  protected function loginToContentpool() {
    // Login as user.
    $element = $this->getSession()->getPage();
    $element->fillField($this->getDrupalText('username_field'), 'dru_admin');
    $element->fillField($this->getDrupalText('password_field'), 'changeme');
    $submit = $element->findButton($this->getDrupalText('log_in'));
    if (empty($submit)) {
      throw new ExpectationException(sprintf("No submit button at %s", $this->getSession()->getCurrentUrl()));
    }
    $submit->click();
    // Quick check that user was logged in successfully.
    $this->assertSession()->pageTextContains("Member for");
  }

  /**
   * I am logged in to contentpool.
   *
   * @Given I am logged in to contentpool
   */
  public function loggedInToContentpool() {
    // Visit user login page on contentpool.
    $this->visitContentpoolPath('user/login');
    // Login to contentpool.
    $this->loginToContentpool();
  }

  /**
   * Check if this is contentpool site.
   *
   * @When I am on contentpool
   */
  public function iAmOnContentpool() {
    $url = $this->getSession()->getCurrentUrl();
    if (strpos($url, $this->contentpoolBaseUrl) !== 0) {
      throw new ExpectationException('Expected contentpool url, got: ' . $url, $this->getSession());
    }
  }

  /**
   * Check if this is satellite site.
   *
   * @When I am on satellite
   */
  public function iAmOnSatellite() {
    $url = $this->getSession()->getCurrentUrl();
    if (strpos($url, $this->satelliteBaseUrl) !== 0) {
      throw new ExpectationException('Expected satellite url, got: ' . $url, $this->getSession());
    }
  }

  /**
   * I click push notification link for current site.
   *
   * @Then I click push notification link for current site
   */
  public function clickPushNotificationLinkForCurrentSite() {
    $site_uuid = \Drupal::config('system.site')->get('uuid');
    $xpath = "//table//td[text()='$site_uuid']/../td/a[@title='Click to enable'][1]";
    $push_notification_link_element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (!$push_notification_link_element) {
      throw new ExpectationException('Push notification link not found."', $this->getSession());
    }
    $push_notification_link = $push_notification_link_element->getAttribute('href');
    if (!$push_notification_link) {
      throw new ExpectationException('Push notification link is empty."', $this->getSession());
    }
    $this->visitPath($push_notification_link);
  }

  /**
   * I should see current site registered.
   *
   * @Then I should see current site registered
   */
  public function seeCurrentSiteRegistered() {
    $tries = 0;
    $tries_limit = 5;
    $site_uuid = \Drupal::config('system.site')->get('uuid');

    do {
      // Sleep one second before next try.
      if ($tries) {
        sleep(1);
      }
      $this->visitPath('/admin/config/remote-registrations');
      $content = $this->getSession()->getPage()->getContent();
      $found = strpos($content, $site_uuid) !== FALSE;
    } while (!$found && ++$tries < $tries_limit);
    if (!$found) {
      throw new ExpectationException('Site uuid was not found in remote registration.', $this->getSession());
    }
  }

}
