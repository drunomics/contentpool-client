@api
@json-api
Feature: The Json Api is working.

  Scenario: Json Api Consumer is created.
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

  @access-token
  Scenario: I can get a oauth access token.
    When I am on "/"
    Then I request an article