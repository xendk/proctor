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
    commands:
      drush: "echo drush >>$TESTLOG "
      mysql: "echo mysql >>$TESTLOG "
    database-mapping:
        "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-strategy: drush
    fetch-alias: @reality
    """
    When I run "proctor build test.site.dev"
    Then it should pass with "Building Drupal 7 site"
    And "sites/test.site.dev/settings.php" should contain the string:
    """
    $databases = array (
      'default' =>
      array (
        'default' =>
        array (
          'driver' => 'mysql',
          'database' => 'site_test',
          'username' => 'myusername',
          'password' => 'mypassword',
          'host' => 'myhostname',
          'port' => '',
          'prefix' => '',
        ),
      ),
    );
    """
    And "sites/test.site.dev/settings.php" should contain the string:
    """
    $drupal_hash_salt = '';
    """
    And "sites/sites.php" should contain:
    """
    <?php
    $sites['test.site.dev'] = 'test.site.dev';
    """
    And "test.log" should contain:
    """
    mysql -h myhostname -u myusername -pmypassword -e CREATE DATABASE IF NOT EXISTS site_test;
    drush @reality sql-dump
    mysql -h myhostname -u myusername -pmypassword site_test
    drush rsync -y @reality:%files @self:%files
    """

  Scenario: Fail on missing config file
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    When I run "proctor build test"
    Then it should fail with "Global configuration not found, please run proctor config:init"

  Scenario: Output from failing commands
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    # The funky quoting in the echo statemens will be handled my the
    # shell, so when we check for the string without quotes, it wont
    # match the command line itself, but only its output.
    And "~/.proctor.yml" contains:
    """
    mysql-hostname: myhostname
    mysql-username: myusername
    mysql-password: mypassword
    commands:
      drush: 'echo "bad"drush && false'
      mysql: 'echo "bad"mysql && false'
    database-mapping:
    "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-strategy: drush
    fetch-alias: @reality
    """
    When I run "proctor build test.site.dev"
    Then it should fail with "badmysql"
    
    
