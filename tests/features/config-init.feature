Feature: Config init
  In order to use proctor
  As a user
  I need to be able to create a config file

  Scenario: Creation of default config
    When I run "proctor config:init"
    Then the output should contain:
    """
    Created ~/.proctor.yml
    """
    And "~/.proctor.yml" should contain:
    """
    # Username for mysql.
    mysql-username: username
    # Password for mysql.
    mysql-password: password
    # Path to selenium-server jar.
    selenium-server: ""
    """
    
  Scenario: Should not overwrite existing config file
    Given "~/.proctor.yml" contains:
    """
    stuff
    """
    When I run "proctor config:init"
    Then the output should contain:
    """
    ~/.proctor.yml already exists
    """
    And "~/.proctor.yml" should contain:
    """
    stuff
    """
