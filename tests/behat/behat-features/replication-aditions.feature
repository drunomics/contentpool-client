@api
@replication @replication-aditions
Feature: Contentpool media replication basically works.

  Make sure that media referenced in content can be pulled from remote contentpool server.

  Scenario:
    Given I am logged in to contentpool

    # Add media entities
    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Media image 1"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"

    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Media image 2"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"

    And I visit path "media/add/image" on contentpool
    And I fill in "name[0][value]" with "BEHAT: Media image 3"
    When I attach the file "core/profiles/demo_umami/modules/demo_umami_content/default_content/images/chili-sauce-umami.jpg" to "files[field_image_0]"
    And I press "Save and publish"

  @javascript @replication-aditions-default
  Scenario: Media attached to replicated entities is replicated
    # Create article with teaser
    Given I am logged in to contentpool
    And I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Media article"
    And I fill in "field_seo_title[0][value]" with "Behat media article"
    And I select "-Cooking" from "edit-field-channel"
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    When I click on media "BEHAT: Media image 1" in entity browser "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check article replicated
    And I am on "admin/content"
    Then I should see the text "BEHAT: Media article"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see the text "BEHAT: Media image 1"

    # Edit article and change teaser
    Given I open the contentpool
    And I visit path "admin/content" on contentpool
    And I follow the "Edit" link below the element ".view-content tr:contains('BEHAT: Media article')"
    Then I wait for the page to be loaded
    And I press "edit-field-teaser-media-current-items-0-remove-button"
    And I wait for AJAX to finish
    Then Value of input field "[name='field_teaser_media[target_id]']" is "empty"
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    And I click on media "BEHAT: Media image 2" in entity browser "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I wait for AJAX to finish
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check article replicated
    And I am on "admin/content"
    Then I should see the text "BEHAT: Media article"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see the text "BEHAT: Media image 2"

  @javascript @replication-aditions-only-used
  Scenario: Replication media works only for referenced media
    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check media is not replicated
    And I am on "admin/content/media"
    Then I should not see the text "BEHAT: Media image 3"

    # Create article
    Given I am logged in to contentpool
    When I visit path "node/add/article" on contentpool
    And I fill in "title[0][value]" with "BEHAT: Media article 2"
    And I fill in "field_seo_title[0][value]" with "Media article 2"
    And I select "-Cooking" from "edit-field-channel"

    # Add teaser
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    When I click on media "BEHAT: Media image 3" in entity browser "image_browser"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
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

    # Check media and article are replicated
    And I am on "admin/content"
    Then I should see the text "BEHAT: Media article 2"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see the text "BEHAT: Media image 3"

  @javascript @replication-aditions-media-edited
  Scenario: Replication of media works after media is edited
    # Remove reference media BEHAT: Media image 3 and replicate
    Given I am logged in to contentpool
    And I visit path "admin/content" on contentpool
    And I follow the "Edit" link below the element ".view-content tr:contains('BEHAT: Media article 2')"
    Then I wait for the page to be loaded
    And I press "edit-field-teaser-media-current-items-0-remove-button"
    And I wait for AJAX to finish
    Then Value of input field "[name='field_teaser_media[target_id]']" is "empty"
    And I press "Save as"
    And I wait for the page to be loaded

    # Replicate
    Given I open the satellite
    When I run drush cppull
    Then drush output should contain "Content of remote /Contentpool/ has been replicated successfully."
    And I am logged in as a user with the "administrator" role

    # Check article replicated
    And I am on "admin/content"
    Then I should see the text "BEHAT: Media article 2"

    # Edit media
    Given I visit path "admin/content/media" on contentpool
    And I follow the "Edit" link below the element ".view-content tr:contains('BEHAT: Media image 3')"
    Then I wait for the page to be loaded
    And I fill in "name[0][value]" with "BEHAT: Media image 3 edited"
    And I check the box "Create new revision"
    And I fill in "edit-revision-log" with "Changed media title."
    And I press "Save and keep published"

    # Edit media second time
    Given I visit path "admin/content/media" on contentpool
    Then I should see the text "BEHAT: Media image 3 edited"
    And I follow the "Edit" link below the element ".view-content tr:contains('BEHAT: Media image 3 edited')"
    Then I wait for the page to be loaded
    And I fill in "name[0][value]" with "BEHAT: Media image 3 edited second time"
    And I check the box "Create new revision"
    And I fill in "edit-revision-log" with "Media edited second time and changed media title."
    And I press "Save and keep published"

    # Edit article
    And I visit path "admin/content" on contentpool
    And I follow the "Edit" link below the element ".view-content tr:contains('BEHAT: Media article 2')"
    And I wait for the page to be loaded

    # Re add media to article
    And I press "field_teaser_media_entity_browser_entity_browser"
    Then I wait for AJAX to finish
    When I click on media "BEHAT: Media image 3 edited second time" in entity browser "image_browser"
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
    And I am on "admin/content"
    Then I should see the text "BEHAT: Media article 2"

    # Check media is replicated
    And I am on "admin/content/media"
    Then I should see the text "BEHAT: Media image 3 edited second time"
