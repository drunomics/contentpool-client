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

    When I visit path "node/add/article" on contentpool
    # Choose Cooking, since satellite wants to replicate articles with Food channel and their descendants.
    And I fill in "title[0][value]" with "BEHAT: Cooking"
    And I fill in "field_seo_title[0][value]" with "test cooking"
    And I select "-Cooking" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"
    When I press "Save"
    Then I wait for "has been created."

    When I open the satellite
    And I am logged in as a user with the "administrator" role
    Then I am on satellite
    And I run drush cppull
    And I am on "/admin/content"
    And I should not see the text "BEHAT: Science"
    Then I should see the text "BEHAT: Cooking"

  @javascript @replication-filter-change
  Scenario: Replication filter changes take effect after reset

    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    # Choose Science, since satellite does not opt to replicate articles with this channel.
    And I fill in "title[0][value]" with "BEHAT: More science"
    And I fill in "field_seo_title[0][value]" with "more science"
    And I select "Science" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"
    When I press "Save"
    Then I wait for "has been created."

    When I open the satellite
    And I am logged in as a user with the "administrator" role
    Then I am on satellite
    And I run drush cppull
    And I am on "/admin/content"
    And I should not see the text "BEHAT: More science"

    When I am on "admin/config/services/relaxed/contentpool/replication_filter"
    # Open the autocompletion drop-down and select "Science"
    And I click on the element "#treeselect_filter-field_channel .vue-treeselect__input"
    And I click on the element '#treeselect_filter-field_channel .vue-treeselect__option[data-id="1bc7757f-ff52-4dde-ab24-68c1cd7362b8"] label'
    And I press "Save"
    Then I should see "Changes to the replication filter settings take affect on the next replication."
    Then I should see "Please reset the replication status in order to replicate the complete content with updated filters."

    # No new content after changing it.
    When I run drush cpc
    Then drush output should contain "There are no changes to be replicated."

    # Workaround around broken reset link - use drush for now.
    # When I click "reset"
    # Then I see "Some success message"
    When I run drush "contentpool-client:reset"

    And I run drush cpc
    Then drush output should contain "There are new changes to be replicated."
    When I run drush cppull
    And I am on "/admin/content"
    And I should see the text "BEHAT: More science"
