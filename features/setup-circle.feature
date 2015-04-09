Feature: Cicle CI setup
  In order to run tests on Circle
  As a user
  I need to be able to configure apache on Circle.

  Scenario: Basic setup
    Given "circle.yml" contains:
    """
    machine:
      environment:
        php:
          version: 5.4.21
    """
    And "sites/default" contains:
    """
    # Dummy vhost
    """
    When I run "proctor setup:circle -p --apache-sites=./sites"
    Then it should pass with:
    """
    Setting up Circle Apache virtual host
    Wrote ./sites/proctor.conf
    command: a2ensite proctor.conf
    command: sudo service apache2 restart
    Done
    """
    And "sites/proctor.conf" should contain:
    """
    Listen 8080

    <VirtualHost *:8080>
      LoadModule php5_module /home/ubuntu/.phpenv/versions/5.4.21/libexec/apache2/libphp5.so
      DocumentRoot /home/ubuntu/workdir
      ServerName proctor.dev
      <FilesMatch \.php$>
        SetHandler application/x-httpd-php
      </FilesMatch>
    </VirtualHost>
    """
  
  Scenario: Missing PHP version in circle.yml
    And "sites/default" contains:
    """
    # Dummy vhost
    """
    When I run "proctor setup:circle -p --apache-sites=./sites"
    Then it should fail with:
    """
    No PHP version found in circle.yml.
  
    Currently proctor needs PHP version to be pinned.
    """
    
