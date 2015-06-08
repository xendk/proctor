Feature: Building Drupal site
  In order to run tests on my site
  As a user
  I need to be able to build a Drupal site

  Scenario: "drush" strategy
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    And "~/.proctor.yml" contains:
    """
    mysql:
      host: myhostname
      user: myusername
      pass: mypassword
    database-mapping:
      "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-method: drush
    fetch-alias: @reality
    """
    When I run "proctor build test.site.dev -p -s 1337"
    Then it should pass with:
    """
    Building Drupal 7 site
    Configuring site
    command: mysql --host=myhostname --user=myusername --password=mypassword -e "CREATE DATABASE IF NOT EXISTS site_test;"
    Syncing database and files
    command: drush --uri=test.site.dev @reality sql-dump | mysql --host=myhostname --user=myusername --password=mypassword site_test
    command: drush --uri=test.site.dev rsync -y @reality:%files files
    command: drush --uri=test.site.dev rsync -y @reality:%private private
    command: drush --uri=test.site.dev cc all
    Done
    """
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
    $drupal_hash_salt = '01b3bff45d4e9e4c8f39dac6507b644433c1f75a96804cc61c12963615170592';
    """
    And "sites/sites.php" should contain:
    """
    <?php
    $sites['test.site.dev'] = 'test.site.dev';
    """

  Scenario: "dump-n-config" strategy
    Given "core/lib/Drupal.php" contains:
    """
    something
    """
    And "sites/default/default.services.yml" contains:
    """
    Fake services file.
    """
    And "~/.proctor.yml" contains:
    """
    mysql:
      host: myhostname
      user: myusername
      pass: mypassword
    database-mapping:
      "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-method: dump-n-config
    fetch-dumpfile: configuration/base.sql.gz
    fetch-staging: configuration/staging
    """
    And "configuration/base.sql.gz" contains:
    """
    Fake dump.
    """
    And "configuration/staging/core.yml" contains:
    """
    Fake configuration.
    """
    When I run "proctor build test.site.dev -p -s 1337"
    Then it should pass with:
    """
    Building Drupal 8 site
    Configuring site
    command: mysql --host=myhostname --user=myusername --password=mypassword -e "CREATE DATABASE IF NOT EXISTS site_test;"
    Importing SQL dump and config
    command: zcat configuration/base.sql.gz | mysql --host=myhostname --user=myusername --password=mypassword site_test
    command: drush --uri=test.site.dev -y cim
    Done
    """
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
    $settings['hash_salt'] = '01b3bff45d4e9e4c8f39dac6507b644433c1f75a96804cc61c12963615170592';
    """
    And "sites/test.site.dev/settings.php" should contain the string:
    """
    $config_directories[CONFIG_STAGING_DIRECTORY] = 'configuration/staging';
    $config_directories[CONFIG_ACTIVE_DIRECTORY] = 'sites/test.site.dev/private/config_active';
    """
    And "sites/test.site.dev/services.yml" should contain the string:
    """
    Fake services file.
    """
    And "sites/sites.php" should contain:
    """
    <?php
    $sites['test.site.dev'] = 'test.site.dev';
    """

  Scenario: Running custom commands
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    And "~/.proctor.yml" contains:
    """
    mysql:
      host: myhostname
      user: myusername
      pass: mypassword
    database-mapping:
      "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-method: drush
    fetch-alias: @reality
    """
    When I run "proctor build test.site.dev -p -s 1337"
    Then it should pass with:
    """
    Building Drupal 7 site
    Configuring site
    command: mysql --host=myhostname --user=myusername --password=mypassword -e "CREATE DATABASE IF NOT EXISTS site_test;"
    Syncing database and files
    command: drush --uri=test.site.dev @reality sql-dump | mysql --host=myhostname --user=myusername --password=mypassword site_test
    command: drush --uri=test.site.dev rsync -y @reality:%files files
    command: drush --uri=test.site.dev rsync -y @reality:%private private
    command: drush --uri=test.site.dev cc all
    Done
    """
    
    And "sites/test.site.dev/settings.php" should contain the string:
    """
    $drupal_hash_salt = '01b3bff45d4e9e4c8f39dac6507b644433c1f75a96804cc61c12963615170592';
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
    mysql:
      host: myhostname
      user: myusername
      pass: mypassword
    commands:
      drush: 'echo "bad"drush && false'
      mysql: 'echo "bad"mysql && false'
    database-mapping:
    "/^([^.]+).([^.]+).([^.]+)$/": "$2_$1"
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-method: drush
    fetch-alias: @reality
    """
    When I run "proctor build test.site.dev"
    Then it should fail with "badmysql"
    
  Scenario: "drush" strategy building on CircleCI
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    And the env variable "CIRCLECI" contains "true"
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-method: drush
    fetch-alias: @reality
    """
    When I run "proctor build -p test.site.dev"
    Then it should pass with:
    """
    Building Drupal 7 site
    Configuring site
    command: mysql --host=127.0.0.1 --user=ubuntu -e "CREATE DATABASE IF NOT EXISTS circle_test;"
    Syncing database and files
    command: drush --uri=test.site.dev @reality sql-dump | mysql --host=127.0.0.1 --user=ubuntu circle_test
    command: drush --uri=test.site.dev rsync -y @reality:%files files
    command: drush --uri=test.site.dev rsync -y @reality:%private private
    command: drush --uri=test.site.dev cc all
    Done
    """

  Scenario: Command timeout
    Given "includes/bootstrap.inc" contains:
    """
    define('VERSION', '7.34');
    """
    # We only set the sleep command to sleep one second more than the
    # limit as Symfony Proccess has trouble killing the sleep command
    # because it's part of a pipe.
    And "~/.proctor.yml" contains:
    """
    mysql:
      host: myhostname
      user: myusername
      pass: mypassword
    commands:
      drush: 'sleep 3 && true'
      mysql: 'true'
    """
    And "tests/proctor/drupal.yml" contains:
    """
    fetch-method: drush
    fetch-alias: @reality
    """
    When I run "proctor build --timeout 2 test.site.dev"
    Then it should fail with:
    """
    Building Drupal 7 site
    Configuring site
    Syncing database and files
    The process "sleep 3 && true --uri=test.site.dev @reality sql-dump | true --host=myhostname --user=myusername --password=mypassword proctor_test_site_dev" exceeded the timeout of 2 seconds.
    """
