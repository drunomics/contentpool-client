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
    And I should see "quantum"
    And I should not see "In-vitro meat"
    And I should not see "cuba"
    When I move backward one page
    And I click "U.S. Congress considers lifting Cuba travel ban"
    Then I should see "cuba"
    And I should not see "In-vitro meat"
    And I should not see "science"

  @javascript
  Scenario: Pushing notification works
    Given I am logged in as a user with the "administrator" role
    # Trigger auto remote registration.
    And I visit path "/admin/reports/status" on contentpool
    And I wait for the page to be loaded
    When I am logged in to contentpool
    Then I should see current site registered
    When I click push notification link for current site
    Then I press "Confirm"
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "Replication behat test" and random suffix
    And I fill in "field_seo_title[0][value]" with "Replication behat test" and last random suffix
    And I select "-Bakery" from "edit-field-channel"
    And I check the box "status[value]"
    When I press "Save"
    Then I wait for "has been created."
    And I should get a 200 HTTP response
    # Check on satellite if there is article already pushed.
    And I should see in content overview article with "Replication behat test" and random suffix
    # Make sure edit link on satellite redirects to content pool.
    When I click first content edit link
    And I wait for "Edit Article"
    Then I am on contentpool
    # Make sure we get back to satellite after saving edits.
    When I fill in "title[0][value]" with "Replication behat edit" and random suffix
    And I fill in "field_seo_title[0][value]" with "Replication behat edit" and last random suffix
    And I press "Save"
    And I wait for the page to be loaded
    Then I am on satellite
    # Make sure the article is changed.
    And I should see in content overview article with "Replication behat edit" and random suffix
