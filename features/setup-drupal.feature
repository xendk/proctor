Feature: Drupal init
  In order to use proctor on a Drupal site
  As a user
  I need to be able to configure Drupal specific settings

  Scenario: Basic setup
    When I run "proctor setup:drupal @live"
    Then it should pass with:
    """
    Wrote tests/proctor/drupal.yml
    """
    And  "tests/proctor/drupal.yml" should contain:
    """
    fetch-strategy: drush
    fetch-alias: '@live'
    """
    
