Feature: Drupal init
  In order to use proctor on a Drupal site
  As a user
  I need to be able to configure Drupal specific settings

  Scenario: "drush" method.
    When I run "proctor setup:drupal --alias @live"
    Then it should pass with:
    """
    Wrote tests/proctor/drupal.yml
    """
    And  "tests/proctor/drupal.yml" should contain:
    """
    fetch-method: drush
    fetch-alias: '@live'
    """
    
  Scenario: Unsupported fetch method.
    When I run "proctor setup:drupal --method fake"
    Then it should fail with:
    """
    Unknown fetching method "fake".
    """
    
  Scenario: "dump-n-config" method.
    When I run "proctor setup:drupal --method dump-n-config"
    Then it should pass with:
    """
    Wrote tests/proctor/drupal.yml
    """
    And  "tests/proctor/drupal.yml" should contain:
    """
    fetch-method: dump-n-config
    fetch-dumpfile: configuration/base.sql.gz
    fetch-staging: configuration/staging
    """
    
