@api
@ce-render
Feature: Content is rendered correctly via custom elements

  @javascript @render
  Scenario: Replicated content is rendered correctly
    Given I run drush cppull
    And I am logged in as a user with the "administrator" role
    # admin/content has some issues due to sticky table headers, so use frontpage instead.
    And I am on "/"
    When I click "Cultured meat"
    Then I should see "The concept of cultured meat was popularized"
    # The custom-elements must be rendered, thus not visible any more.
    # The text below is a bit misleading, as the response originally contains the tag but the current HTML may not
    # contain it any more.
    And the response should not contain "<pg-text"
    And the response should not contain "<template slot=\"field-text\">"
