@api
@replication @replication-base
Feature: Contentpool client-side replication basically works.

  Make sure that content can be pulled from remote contentpool server.

  @replication-base-default
  Scenario: Replication with pre-configured filter and default-content works
    Given I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    When I am logged in as a user with the "administrator" role
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

  @replication-base-edit
  Scenario: Editing on contentpool and replicating new changes.
    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Bakery"
    And I fill in "field_seo_title[0][value]" with "Behat bakery"
    And I select "-Bakery" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"
    And I press "Save"

    And I open the satellite
    And I run drush cppull
    And I am logged in as a user with the "administrator" role
    # Trigger auto remote registration during status check.
    And I visit "/admin/reports/status"
    And I am on "/admin/content"
    And I click "BEHAT: Bakery"
    And I click on "Edit" below the element ".tabs"
    Then I am on contentpool
    When I fill in "title[0][value]" with "BEHAT: The best bakery"
    And I press "Save"

    Then I am on satellite
    # Verify drush cpc reports changes.
    When I run drush cpc
    Then drush output should contain "There are new changes to be replicated."
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    When I run drush cpc
    Then drush output should contain "There are no changes to be replicated."

    When I am on "/admin/content"
    Then I should see "BEHAT: The best bakery"

  @replication-base-push
  Scenario: Pushing notification works
    Given I am logged in as a user with the "administrator" role
    # Trigger auto remote registration during status check.
    And I visit "/admin/reports/status"
    When I am logged in to contentpool
    Then I should see current site registered
    When I click push notification link "Click to enable" for current site
    And I press "Confirm"
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Bakery"
    And I fill in "field_seo_title[0][value]" with "Behat bakery"
    And I select "-Bakery" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"
    When I press "Save"
    Then I wait for "has been created."
    And I should get a 200 HTTP response
    ## Workaround: Edit the article again. For some reason the push does not work the first time.
    And I click on "Edit" below the element ".tabs"
    And I press "Save"

    # Check on satellite if there is article already pushed.
    When I open the satellite
    # First wait a bit so replication is finished.
    And I wait for "1000" ms
    And I am on "/admin/content"
    Then I should see "BEHAT: Bakery"
    # Make sure edit link on satellite redirects to content pool.
    When I click "BEHAT: Bakery"
    And I click on "Edit" below the element ".tabs"
    Then I am on contentpool
    # Make sure we get back to satellite after saving edits.
    When I fill in "title[0][value]" with "BEHAT: Bakery2"
    And I fill in "field_seo_title[0][value]" with "behat bakery2"
    And I press "Save"
    Then I am on satellite
    # Make sure the article is changed. First wait a bit so replication is finished.
    And I wait for "3000" ms
    And I reload the page
    And I should see "BEHAT: Bakery2"
    # Finally, disable push registration again.
    When I open the contentpool
    Then I should see current site registered
    When I click push notification link 'Click to disable' for current site
    And I press "Confirm"
    Then I should see "Disabled"
