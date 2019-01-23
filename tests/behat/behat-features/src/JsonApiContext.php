<?php

/**
 * @file
 * The main behat context.
 */

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Client as GuzzleClient;
// use WoohooLabs\Yang\JsonApi\Request\JsonApiRequestBuilder;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class JsonApiContext extends RawDrupalContext {

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

    // Instantiate the Guzzle HTTP Client
    $guzzleClient = new GuzzleClient();

    $this->jsonApiClient = new JsonApiClient($guzzleClient);
  }

  /**
   * @Then /^I get a oauth access token$/
   */
  public function iGetAOauthAccessToken() {
    // Instantiate an empty PSR-7 request.
    $request = new Request("", "");

    $request = $request
      ->withProtocolVersion("1.1")
      ->withUri(new Uri("{$this->contentpoolBaseUrl}/oauth/token"))
      ->withHeader("Accept", "application/vnd.api+json")
      ->withHeader("Content-Type", "application/x-www-form-urlencoded");

    //$request->withBody("");

    $response = $this->jsonApiClient->sendRequest($request);

    print_r($response);

  }

}
