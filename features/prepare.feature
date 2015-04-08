Feature: Starting Selenium up for testing
  In order for tests to run
  As a user
  I need to start Selenium Server

  Scenario: Starting the server
    Given "~/.proctor.yml" contains:
    """
    selenium-server: './selenium-server-standalone-2.42.2.jar'
    """
    And "selenium-server-standalone-2.42.2.jar" is available in workdir
    When I run "proctor prepare"
    Then it should pass with:
    """
    Starting Selenium server
    Server started, PID:
    """
    And I should see the started Selenium process
    And I can kill the Selenium process
    
  Scenario: Missing configuration
    Given "~/.proctor.yml" contains:
    """
    selenium-server: dummy
    """
    When I run "proctor prepare"
    Then it should fail with:
    """
    Could not find Selenium jar file.
    Please download Selenium Server from http://www.seleniumhq.org/download/ and add the location of the jar file to ~/.proctor.yml
    """
    
