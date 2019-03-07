@api
@ce-render
Feature: Content is rendered correctly via custom elements

  @javascript @render
  Scenario: Replicated content is rendered correctly
    # First add a new article to contentpool.
    And I am logged in to contentpool
    And I visit path "node/add/article" on contentpool
    Given I accept confirmation dialogs
    And I select "Food" from "Channel"
    And I fill in "Title" with "BEHAT: RENDER TEST"
    And I fill in "SEO Title" with "BEHAT: RENDER TEST"

    When I add a paragraph "Text" at slot number "1"
    And I wait for AJAX to finish
    And I fill in the Wysiwyg "Text" with "Lorem ipsum" in paragraph number "1"

    And I add a paragraph "Quote" at slot number "2"
    And I wait for AJAX to finish
    And I fill in the Wysiwyg "Quote" with "Quote" in paragraph number "2"

    And I add a paragraph "Link" at slot number "3"
    And I wait for AJAX to finish
    And I fill in "URL" with "https://drunomics.com" in paragraph number "3"
    And I fill in "Link text" with "Drunomics" in paragraph number "3"

    And I add a paragraph "Instagram" at slot number "4"
    And I wait for AJAX to finish
    And I fill in "Instagram url" with "https://www.instagram.com/p/Bi6TxPVAgIS/" in paragraph number "4"

    And I add a paragraph "Twitter" at slot number "5"
    And I wait for AJAX to finish
    And I fill in "Twitter url" with "https://twitter.com/SplashAwards_de/status/972088668788854792" in paragraph number "5"

    And I add a paragraph "Pinterest" at slot number "6"
    And I wait for AJAX to finish
    And I fill in "Pinterest url" with "https://www.pinterest.de/pin/562879653408840422/" in paragraph number "6"

    And I add a paragraph "Gallery" at slot number "7"
    And I wait for AJAX to finish
    And I fill in "Media name" with "BEHAT: Gallery; Moon or meat?" in paragraph number "7"
    And I press "Select images" in paragraph number "7"
    Then I wait for AJAX to finish
    Then I wait for ".views-row:nth-of-type(1)" in entity browser "multiple_image_browser"
    When I click on ".views-row:nth-of-type(1)" in entity browser "multiple_image_browser"
    And I click on ".views-row:nth-of-type(2)" in entity browser "multiple_image_browser"
    Then "#edit-selected" in entity browser "multiple_image_browser" should have at least "2" child elements
    Then I wait for ".entity-browser-use-selected" in entity browser "multiple_image_browser"
    And I click on ".entity-browser-use-selected" in entity browser "multiple_image_browser"
    And I wait for entity browser "multiple_image_browser" to close
    Then I should not see "Select existing"
    Then I wait for ".entities-list .media-form__item-widget img" in paragraph number "7"

    # Save and publish article.
    And I select "Published" from "moderation_state[0]"
    And I press "Save as"
    And I wait for the page to be loaded

    Given I run drush cppull
    And I open the satellite
    And I am logged in as a user with the "administrator" role
    # admin/content has some issues due to sticky table headers, so use frontpage instead.
    And I am on "/"
    When I click "BEHAT: RENDER TEST"

    # Check if paragraphs are visible.
    Then I should see "Lorem ipsum"
    Then I should see "Quote"
    Then I should see "Drunomics"
    Then I should see "BEHAT: Gallery; Moon or meat?"
    Then I wait for the Instagram paragraph to be rendered
    And the response should contain "<twitter-widget"
    Then I should see "We give you top ten reasons why Drupal rocks the CMS arena"

    # The custom-elements must be rendered, thus not visible any more.
    # The text below is a bit misleading, as the response originally contains the tag but the current HTML may not
    # contain it any more.
    And the response should not contain "<pg-text"
    And the response should not contain "<template slot="
    And the response should not contain "<pg-quote"
    And the response should not contain "<pg-link"
    And the response should not contain "<pg-instagram"
    And the response should not contain "<pg-twitter"
    And the response should not contain "<pg-pinterest"
    And the response should not contain "<pg-gallery"

    # Image paragraph is added later, because to create another paragraph after
    # using entity browser once the form needs to be reloaded.
    # TODO Test should be updated when we find the reason for this behaviour.
    Then I click "Edit" in local tasks
    And I wait for the page to be loaded
    Then I am on contentpool
    And I add a paragraph "Image" at slot number "8"
    And I wait for AJAX to finish
    And I press "Select image" in paragraph number "8"
    And I wait for AJAX to finish
    And I click on ".views-row:nth-of-type(1)" in entity browser "image_browser"
    Then ".views-row:nth-of-type(1)" in entity browser "image_browser" should have the class "checked"
    And I click on "#edit-submit" in entity browser "image_browser"
    And I wait for entity browser "image_browser" to close
    Then I should not see "Select existing"
    And I press "Save as"

    And I wait for the page to be loaded

    Given I run drush cppull
    And I open the satellite
    And I am logged in as a user with the "administrator" role
    And I am on "/"
    When I click "BEHAT: RENDER TEST"
    And the response should not contain "<pg-image"
    And Paragraph "image" should be rendered
