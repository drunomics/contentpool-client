@api
@replication @replication-aditions
Feature: Contentpool media replication basically works.

  Make sure that media referenced in content can be pulled from remote contentpool server.

  @replication-aditions-test-media
  Scenario: Creating media entities
    Given I am logged in to contentpool

    # Add media entitiy
    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Image media 1"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"
    Then I save media to array with title "BEHAT: Image media 1"

    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Image media 2"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"
    Then I save media to array with title "BEHAT: Image media 2"

    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Image media 3"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"
    Then I save media to array with title "BEHAT: Image media 3"

    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Image media 4"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"
    Then I save media to array with title "BEHAT: Image media 4"

  @javascript @replication-aditions-default
  Scenario: Replication media works
    # Create article with teaser
    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Media"
    And I fill in "field_seo_title[0][value]" with "test media"
    And I select "-Cooking" from "edit-field-channel"
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    Then I wait for ".views-row:nth(0)" in entity browser "image_browser"
    When I click on "0" of created media in "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish
    Then Save "[name='field_teaser_media[target_id]']" value as currently selected
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check article replicated
    And I am on "/"
    Then I should see the text "BEHAT: Media"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see title of currently selected media
    And I should get a 200 HTTP response

    # Edit article and change teaser
    Given I visit path "/" on contentpool
    And I click "BEHAT: Media"
    And I click "Edit" in local tasks
    And I press "edit-field-teaser-media-current-items-0-remove-button"
    And I wait for AJAX to finish
    Then "[name='field_teaser_media[target_id]']" value is empty
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    Then I wait for ".views-row:nth(2)" in entity browser "image_browser"
    When I click on "1" of created media in "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish
    Then Save "[name='field_teaser_media[target_id]']" value as currently selected
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check article replicated
    And I am on "/"
    Then I should see the text "BEHAT: Media"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see title of currently selected media
    And I should get a 200 HTTP response

  @javascript @replication-aditions-only-used
  Scenario: Replication media works only when used
    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check media is not replicated
    And I am on "admin/content/media"
    And Unused media is not replicated

    # Create article
    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Media 2"
    And I fill in "field_seo_title[0][value]" with "test media 2"
    And I select "-Cooking" from "edit-field-channel"
    And I select "published" from "moderation_state[0]"

    # Add teaser
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    Then I wait for ".views-row:nth(3)" in entity browser "image_browser"
    And I click on unused media in "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish
    Then Save "[name='field_teaser_media[target_id]']" value as currently selected

    # Save and publish article.
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check media and article are replicated
    And I am on "/"
    Then I should see the text "BEHAT: Media 2"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see title of currently selected media
    And I should get a 200 HTTP response

  @javascript @replication-aditions-media-edited
  Scenario: Replication works for edited media
    # Remove reference media and replicate
    Given I am logged in to contentpool
    And I visit path "/" on contentpool
    And I click "BEHAT: Media 2"
    And I wait for the page to be loaded
    Then I should get a 200 HTTP response
    Then I should see the text "Edit"
    And I click "Edit" in local tasks
    Then Save "[name='field_teaser_media[target_id]']" value as currently selected
    And I press "edit-field-teaser-media-current-items-0-remove-button"
    And I wait for AJAX to finish
    Then "[name='field_teaser_media[target_id]']" value is empty
    Then I wait for AJAX to finish

    # Save and publish article.
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check article replicated
    And I am on "/"
    Then I should see the text "BEHAT: Media 2"

    # Edit media
    Given I visit path "admin/content/media" on contentpool
    And I edit last media on contentpool
    And I fill in "name[0][value]" with "BEHAT: Image media edited"
    And I press "Save and keep published"

    # Edit article
    And I visit path "/" on contentpool
    And I click "BEHAT: Media 2"
    And I click "Edit" in local tasks

    # Re add media to article
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    Then I wait for ".views-row:nth(3)" in entity browser "image_browser"
    And I click on edited media in "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish

    # Save article
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check media changes got replicated
    And I am on "/"
    Then I should see the text "BEHAT: Media 2"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see the text "BEHAT: Image media edited"
    And I should get a 200 HTTP response
