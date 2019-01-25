@api
@json-api
Feature: The Json Api is working.

  Scenario: I can retrieve an article via json_api.
    When I am on "/"
    Then I request an article with the uuid "3b8f521b-c990-46b2-b7c8-0e5a2210a620" via json api
    And The json api request was successful
    And The json api request has results
    Then I request an article with the uuid "3b8f521b-c990-46b2-b7c8-0e5a2210a620" and included "field_teaser_media,field_paragraphs,field_tags,field_channel" via json api
    And The json api request was successful
    And The json api request has results
    And The json api response contains the fields "field_teaser_media,field_paragraphs,field_tags,field_channel"

  @javascript
  @oauth
  Scenario: Json Api Consumer is created and I can create an article.
    Given I am logged in to contentpool
    When I visit path "admin/config/services/consumer/add" on contentpool
    And I fill in "label[0][value]" with "BEHAT Consumer"
    And I fill in "new_secret" with "behat123"
    And I uncheck the box "third_party[value]"
    And I check the box "roles[editor]"
    And I check the box "roles[replicator]"
    And I check the box "roles[restricted_editor]"
    And I check the box "roles[seo]"
    When I press "Save"
    Then I wait for "Created the BEHAT Consumer"
    Then I save the consumer id for oauth authentication
    When I am on "/"
    Then I create an article with the title "Behat API Test Article" via json_api


