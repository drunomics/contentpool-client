<?php

/**
 * @file
 * The main behat context.
 */

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle6\Client;
use kamermans\OAuth2\GrantType\PasswordCredentials;
use kamermans\OAuth2\OAuth2Middleware;
use WoohooLabs\Yang\JsonApi\Request\JsonApiRequestBuilder;
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

    // Authorization client - this is used to request OAuth access tokens
    $reauth_client = new GuzzleClient([
      // URL for access_token request
      'base_uri' => "{$this->contentpoolBaseUrl}/oauth/token",
    ]);
    $reauth_config = [
      "client_id" => "4e612b91-d72e-4a7f-aac8-7f3b921a988e",
      "client_secret" => "behat123",
      "username" => "dru_admin",
      "password" => "changeme",
    ];

    $grant_type = new PasswordCredentials($reauth_client, $reauth_config);

    $oauth = new OAuth2Middleware($grant_type);

    $stack = HandlerStack::create();
    $stack->push($oauth);

    $client = Client::createWithConfig([
      'auth'     => 'oauth',
      'handler'  => $stack,
    ]);

    $this->jsonApiClient = new JsonApiClient($client);
  }

  /**
   * @Then /^I request an article$/
   */
  public function iRequestAnArticle() {
    // Instantiate an empty PSR-7 request.
    $request = new Request("", "");

    $requestBuilder = new JsonApiRequestBuilder($request);

    $requestBuilder
      ->setProtocolVersion("1.1")
      ->setMethod("GET")
      ->setUri("{$this->contentpoolBaseUrl}/jsonapi/node/article")
      ->setHeader("Accept-Charset", "utf-8");

    $request = $requestBuilder->getRequest();

    $response = $this->jsonApiClient->sendRequest($request);

    print_r($response);

  }

}
