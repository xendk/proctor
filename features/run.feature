Feature: Running tests
  In see the result of tests
  As a user
  I need to be able to run tests

  Scenario: Behat tests
    Given "tests/behat/features/test.feature" contains:
    """
    fake contents
    """
    And "vendor/bin/behat" contains:
    """
    #!/usr/bin/env php
    <?php
    echo "behat fake\noutput";
    """
    And I run "proctor run"
    Then it should pass with:
    """
    Running Behat tests
    behat fake
    output
    """
    
  Scenario: Failing tests
    Given "tests/behat/features/test.feature" contains:
    """
    fake contents
    """
    And "vendor/bin/behat" contains:
    """
    #!/usr/bin/env php
    <?php
    echo "behat fake\noutput";
    exit(1);
    """
    And I run "proctor run"
    Then it should fail with:
    """
    Running Behat tests
    behat fake
    output
    """
    
