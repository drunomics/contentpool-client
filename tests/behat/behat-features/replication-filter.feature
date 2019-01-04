@api
@replication-filter
Feature: Replication can be filtered and reset.

  Scenario: Replication filter works
    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Science"
    And I fill in "field_seo_title[0][value]" with "test science"
    # Choose Science, since satellite does not opt to replicate articles with this channel.
    And I select "Science" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"
    When I press "Save"
    Then I wait for "has been created."
    And I should get a 200 HTTP response
    And I should see "Ignored push to remote"

    When I visit path "node/add/article" on contentpool
    # Choose Cooking, since satellite wants to replicate articles with Food channel and their descendants.
    And I fill in "title[0][value]" with "BEHAT: Cooking"
    And I fill in "field_seo_title[0][value]" with "test cooking"
    And I select "-Cooking" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"
    When I press "Save"
    Then I wait for "has been created."
    And I should get a 200 HTTP response
    And I should see "Successfully triggered push to remote"

    When I open the satellite
    And I am logged in as a user with the "administrator" role
    Then I am on satellite
    And I am on "/admin/content"
    And I should not see the text "BEHAT: Science"
    Then I should see the text "BEHAT: Cooking"
