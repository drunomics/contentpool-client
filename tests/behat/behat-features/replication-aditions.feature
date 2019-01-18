@api
@replication @replication-aditions
Feature: Contentpool media replication basically works.

  Make sure that media referenced in content can be pulled from remote contentpool server.

  @javascript
  Scenario: Replication media works
    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    # Choose Cooking, since satellite wants to replicate articles with Food channel and their descendants.
    And I fill in "title[0][value]" with "BEHAT: Media"
    And I fill in "field_seo_title[0][value]" with "test media"
    And I select "-Cooking" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"

    #Teaser
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    Then I wait for ".views-row:nth-of-type(2)" in entity browser "image_browser"
    When I click on ".views-row:nth-of-type(2)" in entity browser "image_browser"
    Then ".views-row:nth-of-type(2)" in entity browser "image_browser" should have the class "checked"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish
    Then "[name='field_teaser_media[target_id]']" value should be "media:3"

    # Save and publish article.
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    Given I open the satellite
    And I am logged in as a user with the "administrator" role
    Then I am on satellite
    And I run drush cppull
    And I am on "/admin/content"
    And I should see the text "BEHAT: Media"
