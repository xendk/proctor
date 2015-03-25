Feature: Use a site for testing
  In order to test the site
  As a user
  I need to fix behat/codeception config files to point at a site.

  Scenario: Fix behat config
    Given "tests/behat/behat.yml" contains:
    """
    default:
      suites:
        default:
          contexts:
            - FeatureContext
            - Behat\MinkExtension\Context\MinkContext
      extensions:
        Behat\MinkExtension:
          base_url: 'http://localhost' # proctor:host
          selenium2:
            browser: 'firefox'
          banana:  "http://localhost" # proctor:host
    """
    And "tests/behat/nota.ymlfile" contains:
    """
    url:  'http://localhost' # proctor:host
    """
    When I run "proctor use test.site.dev"
    Then it should pass with:
    """
    Modified tests/behat/behat.yml
    """
    And "tests/behat/behat.yml" should contain:
    """
    default:
      suites:
        default:
          contexts:
            - FeatureContext
            - Behat\MinkExtension\Context\MinkContext
      extensions:
        Behat\MinkExtension:
          base_url: 'http://test.site.dev' # proctor:host
          selenium2:
            browser: 'firefox'
          banana:  "http://test.site.dev" # proctor:host
    """
    And "tests/behat/nota.ymlfile" should contain:
    """
    url:  'http://localhost' # proctor:host
    """
    
