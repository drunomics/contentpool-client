<?php

/**
 * @file
 * The main behat context.
 */

use Behat\Mink\Exception\ExpectationException;
use GuzzleHttp\Psr7\Request;
use Http\Adapter\Guzzle6\Client;
use WoohooLabs\Yang\JsonApi\Request\JsonApiRequestBuilder;
use WoohooLabs\Yang\JsonApi\Client\JsonApiClient;
use WoohooLabs\Yang\JsonApi\Hydrator\ClassHydrator;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class JsonApiContext extends RawDrupalContext {

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
   * Consumer id.
   *
   * @var string
   */
  protected $consumerId;

  /**
   * Json api client.
   *
   * @var WoohooLabs\Yang\JsonApi\Client\JsonApiClient
   */
  protected $jsonApiClient;

  /**
   * Json api response.
   *
   * @var WoohooLabs\Yang\JsonApi\Response\JsonApiResponse
   */
  protected $response;

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

    $client = Client::createWithConfig([]);

    $this->jsonApiClient = new JsonApiClient($client);
  }

  /**
   * @Then I request an article with the uuid :uuid via json api
   */
  public function iRequestAnArticleWithTheUuid($uuid) {
    // Instantiate an empty PSR-7 request.
    $request = new Request("GET", "{$this->contentpoolBaseUrl}/jsonapi/node/article/$uuid");

    $requestBuilder = new JsonApiRequestBuilder($request);

    $requestBuilder
      ->setHeader("Accept-Charset", "utf-8");

    $request = $requestBuilder->getRequest();

    $this->response = $this->jsonApiClient->sendRequest($request);
  }

  /**
   * @Then The json api request was successful
   */
  public function theJsonApiRequestWasSuccessful() {
    if (!$this->response->isSuccessful() || !$this->response->hasDocument()) {
      $error = $this->response->document()->error(0);
      throw new ExpectationException(sprintf('Response was not successful: %s: %s', $error->title(), $error->detail()), $this->getSession());
    }
  }

  /**
   * @Then The json api request has results
   */
  public function theJsonApiRequestHasResults() {
    if ($this->response->isSuccessful() && !$this->response->hasDocument()) {
      throw new ExpectationException('Response has no results.', $this->getSession());
    }
  }

  /**
   * @Then The json api response contains the fields :fields
   */
  public function theJsonApiResponseContainsTheFields($fields) {
    $hydrator = new ClassHydrator();
    $article = $hydrator->hydrate($this->response->document());

    $fields_list = explode(',', $fields);
    foreach ($fields_list as $field) {
      if (empty($article->{$field})) {
        throw new ExpectationException("A field was not part of the response: $field", $this->getSession());
      }
    }
  }

  /**
   * @Then I save the consumer id for oauth authentication
   */
  public function iSaveTheConsumerIdForOuathAuthentication() {
    // Instantiate an empty PSR-7 request.
    $consumer_id = $this->getSession()->getDriver()->evaluateScript('jQuery("td:contains(\'BEHAT Consumer\')").first().prev().text()');
    if (empty($consumer_id)) {
      throw new ExpectationException('Unable to save consumer id.', $this->getSession());
    }
    $this->consumerId = $consumer_id;
  }

  /**
   * @Then I request an article with the uuid :uuid and included :fields via json api
   */
  public function iRequestAnArticleWithTheUuidAndIncludedFields($uuid, $fields) {
    // Instantiate an empty PSR-7 request.
    $request = new Request("", "");

    $requestBuilder = new JsonApiRequestBuilder($request);

    $requestBuilder
      ->setProtocolVersion("1.1")
      ->setMethod("GET")
      ->setUri("{$this->contentpoolBaseUrl}/jsonapi/node/article/$uuid?include=$fields")
      ->setHeader("Accept-Charset", "utf-8");

    $request = $requestBuilder->getRequest();

    $this->response = $this->jsonApiClient->sendRequest($request);
  }

  /**
   * @Then I create an article with the title :title via json_api
   */
  public function iCreateAnArticleWithTheTitle($title) {
    $oauth_access_token = $this->getOauthAccessToken();
    // Instantiate an empty PSR-7 request.
    $request = new Request("", "");

    $requestBuilder = new JsonApiRequestBuilder($request);

    $requestBuilder
      ->setProtocolVersion("1.1")
      ->setMethod("POST")
      ->setUri("{$this->contentpoolBaseUrl}/jsonapi/node/article")
      ->setHeader("Accept-Charset", "utf-8")
      ->setHeader("Authorization", "Bearer $oauth_access_token");

    $requestBuilder->setJsonApiBody('
    {
      "data": {
        "type": "node--article",
        "attributes": {
          "title": "' . $title . '",
          "field_seo_title": "' . $title . '"
        },
        "relationships": {
          "field_channel": {
            "data": {
              "type": "taxonomy_term--channel",
              "id": "1bc7757f-ff52-4dde-ab24-68c1cd7362b8"
            }
          }
        }
      }
    }
    ');

    $request = $requestBuilder->getRequest();

    $this->response = $this->jsonApiClient->sendRequest($request);
  }

  /**
   * Retrieves an oauth2 access token.
   *
   * @return string
   *   Access Token.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  private function getOauthAccessToken() {

    $config = [
      "client_id" => $this->consumerId,
      "client_secret" => "behat123",
      "username" => "dru_admin",
      "password" => "changeme",
      "grant_type" => "password",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "{$this->contentpoolBaseUrl}/oauth/token");
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $config);
    $data = curl_exec($ch);

    try {
      $data = json_decode($data);
      return $data->access_token;
    }
    catch (Exception $exception) {
      throw new ExpectationException(sprintf('Request to create an article was not successful or has no results: %s', $exception->getMessage()), $this->getSession());
    }
  }

}
