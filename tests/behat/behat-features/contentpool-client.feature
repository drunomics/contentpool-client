@api
@smoke
@replication
Feature: Contentpool client-side replication works.

  Make sure that content can be pulled from remote contentpool server.

  Scenario: I configure the contentpool client module
    Given I am logged in as a user with the "administrator" role
    And I go to "admin/config/relaxed/settings"
    And I fill in "username" with "replicator"
    And I fill in "password" with "test"

  Scenario: Replication via drush works
    Given I run drush cpc
    And I run drush cron
    And I am logged in as a user with the "administrator" role
    And I am on "/admin/content"
    Then I should see the text "Cultured meat"
    And I should see the text "First quantum byte created"
    And I should see the text "U.S. Congress considers lifting Cuba travel ban"
