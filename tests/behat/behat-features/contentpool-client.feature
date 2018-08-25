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
    # We subscribe to channel "Food", "Culutured meat" is in "Food/Barbequeue".
    Then I should see the text "Cultured meat"
    # We subscribe to tag "quantum".
    And I should see the text "First quantum byte created"
    # We subscribe to tag "cuba".
    And I should see the text "U.S. Congress considers lifting Cuba travel ban"
    # We do not subscribe to "science".
    And I should not see the text "Total lunar eclipse occurs in July 2018"

    # Ensure referenced tags got auto-added to the replicated entities.
    When I click "Cultured meat"
    Then I should see "In-vitro meat"
    And I should not see "science"
    And I should not see "cuba"
    When I move backward one page
    And I click "First quantum byte created"
    Then I should see "science"
    And I should not see "In-vitro meat"
    And I should not see "cuba"
    When I move backward one page
    And I click "U.S. Congress considers lifting Cuba travel ban"
    Then I should see "cuba"
    And I should not see "In-vitro meat"
    And I should not see "science"
