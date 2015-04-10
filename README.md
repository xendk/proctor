
Proctor
=======

[![Circle CI](https://circleci.com/gh/xendk/proctor.svg?style=svg)](https://circleci.com/gh/xendk/proctor)
[![Travis CI](https://travis-ci.org/xendk/proctor.svg?branch=master)](https://travis-ci.org/xendk/proctor)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/xendk/proctor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/xendk/proctor/?branch=master)

Helps with testing of Drupal sites.

Testing of Drupal sites using Behat, Selenium, Codeception or other
"browser based" methods, involves a lot of setting up and
configuration, both locally and on CI servers. Proctor tries to
automate as much as possible.

Walk-through
------------

Install Proctor and its dependencies in your Drupal root:

  composer require xendk/proctor:~0.1

Commit the `composer.json` and `composer.lock` files.

Run:

  ./vendor/bin/proctor config:init

To initialize a `~/.proctor.yml` configuration file. Edit the file and
supply mysql credentials for your local environment. This allows
Proctor to create test sites.

Run:
      ./vendor/bin/proctor setup:drupal @alias

Where `@alias` is a Drush alias to sync database and files from. This
can be the production site, a staging site or a site used exclusively
as source for tests.

Run:

      ./vendor/bin/proctor build test.mysite.dev

This will create a new `test.mysite.dev` site in `sites/`, add it to
`sites/sites.php`, sync the database and files and clear the cache on
the site. You now have a fresh test site. Re-running the command will
overwrite the site with a fresh copy.

Now you're ready to add tests. You can place Behat tests in
`tests/behat/`, Codeception tests in `tests/codecept`, and Proctor
will run the appropriate tool (further testing frameworks might be
forthcoming).

Run: 
    ./vendor/bin/proctor use test.mysite.dev

This will fix up Behat/Codeception YAML config files to point at the
hostname being tested. To mark an URL for fixing, append
`# proctor:host` to the end of the line.

Run:

    ./vendor/bin/proctor prepare

To start Selenium Server. You can either configure the path to the
Selenium Server JAR file in `~/.proctor.yml`, or add the --fetch
switch to download it.

Run:

    ./vendor/bin/proctor running

To run all tests locally.

CircleCI
--------

Proctor knows about Circle CI, so to run tests there, you need a
circle.yml that looks something like this:

```yaml
machine:
  environment:
    # Add composer global bin dir to path, needed to find drush.
    PATH: $HOME/.composer/vendor/bin:$PATH
  php:
    # Currently Proctor needs to have the PHP version specified in here.
    version: 5.4.21

dependencies:
  override:
    # Install Proctor and dependencies.
    - composer install --no-interaction
    # Install Drush
    - composer --prefer-source --no-interaction global require drush/drush:6.2.0
    # This will make sending mail from PHP not fail.
    - echo "sendmail_path = /bin/true" > ~/.phpenv/versions/$(phpenv global)/etc/conf.d/sendmail.ini
  cache_directories:
    - "~/.composer"
    # Cache the Selenium Server JAR file here.
    - "~/aux"
  post:
    # Prepare Apache virtual host.
    - ./vendor/bin/proctor setup:circle
    # Start Selenium Server in the background.
    - ./vendor/bin/proctor prepare --fetch --selenium-dir ~/aux:
        background: true
    # Temporary hack. This ensures that the Drush command that Proctor uses
    # for syncing doesn't get the message from SSH about a new host. It
    # messes things up.
    - ssh drush-user@hostname echo "test"
    # Build site.
    - ./vendor/bin/proctor build default
    # And fix Behat/Codeception files to point at it.
    - ./vendor/bin/proctor use localhost:8080

test:
  override:
    # Run the tests.
    - ./vendor/bin/proctor run
```
