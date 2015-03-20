Feature: Building Drupal site
  In order to run tests on my site
  As a user
  I need to be able to build a Drupal site

  Scenario: Basic setup
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    And "~/.proctor.yml" contains:
    """
    mysql-hostname: myhostname
    mysql-username: myusername
    mysql-password: mypassword
    drush: "echo >>../cmd.log"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-strategy: drush
    fetch-alias: @reality
    """
    When I run "proctor build test"
    Then it should pass with "Building Drupal 7 site"
    And "sites/test/settings.php" should contain the string:
    """
    $databases = array (
      'default' =>
      array (
        'driver' => 'mysql',
        'database' => 'TODO',
        'username' => 'myusername',
        'password' => 'mypassword',
        'host' => 'myhostname',
        'port' => '',
        'prefix' => '',
      ),
    );
    """
    And "sites/test/settings.php" should contain the string:
    """
    $drupal_hash_salt = '';
    """
    And "sites/sites.php" should contain:
    """
    <?php
    $sites['test'] = 'test';
    """
    And "sites/cmd.log" should contain:
    """
    sql-sync @reality @self
    rsync @reality:%files @self:%files
    """
  Scenario: Fail on missing config file
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    When I run "proctor build test"
    Then it should fail with "Global configuration not found, please run proctor config:init"
