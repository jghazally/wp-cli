Feature: WP-CLI Commands

  Scenario: Invalid class is specified for a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php

      WP_CLI::add_command( 'command example', 'Non_Existent_Class' );
      """

    When I try `wp --require=custom-cmd.php help`
    Then the return code should be 1
    And STDERR should contain:
      """
      Callable "Non_Existent_Class" does not exist, and cannot be registered as `wp command example`.
      """

  Scenario: Invalid subcommand of valid command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * @when before_wp_load
       */
      class Custom_Command_Class extends WP_CLI_Command {

          public function valid() {
             WP_CLI::success( 'Hello world' );
          }

      }
      WP_CLI::add_command( 'command', 'Custom_Command_Class' );
      """

    When I try `wp --require=custom-cmd.php command invalid`
    Then STDERR should be:
      """
      Error: 'invalid' is not a registered subcommand of 'command'. See 'wp help command'.
      """

  Scenario: Use a closure as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * My awesome closure command
       *
       * <message>
       * : An awesome message to display
       *
       * @when before_wp_load
       */
      $foo = function( $args ) {
        WP_CLI::success( $args[0] );
      };
      WP_CLI::add_command( 'foo', $foo );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome closure command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: bar
      """

  Scenario: Use a function as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * My awesome function command
       *
       * <message>
       * : An awesome message to display
       *
       * @when before_wp_load
       */
      function foo( $args ) {
        WP_CLI::success( $args[0] );
      }
      WP_CLI::add_command( 'foo', 'foo' );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome function command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: bar
      """

  Scenario: Use a class method as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class extends WP_CLI_Command {
        /**
         * My awesome class method command
         *
         * <message>
         * : An awesome message to display
         *
         * @when before_wp_load
         */
        function foo( $args ) {
          WP_CLI::success( $args[0] );
        }
      }
      $foo = new Foo_Class;
      WP_CLI::add_command( 'foo', array( $foo, 'foo' ) );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome class method command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: bar
      """

  Scenario: Use an invalid class method as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class extends WP_CLI_Command {
        /**
         * My awesome class method command
         *
         * <message>
         * : An awesome message to display
         *
         * @when before_wp_load
         */
        function foo( $args ) {
          WP_CLI::success( $args[0] );
        }
      }
      $foo = new Foo_Class;
      WP_CLI::add_command( 'bar', array( $foo, 'bar' ) );
      """

    When I try `wp --require=custom-cmd.php bar`
    Then STDERR should contain:
      """
      Error: Callable ["Foo_Class","bar"] does not exist, and cannot be registered as `wp bar`.
      """

  Scenario: Register a synopsis for a given command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      function foo( $args ) {
        $message = array_shift( $args );
        WP_CLI::log( 'Message is: ' . $message );
        WP_CLI::success( $args[0] );
      }
      WP_CLI::add_command( 'foo', 'foo', array(
        'shortdesc'   => 'My awesome function command',
        'when'        => 'before_wp_load',
        'synopsis'    => array(
          array(
            'type'          => 'positional',
            'name'          => 'message',
            'description'   => 'An awesome message to display',
            'optional'      => false,
          ),
          array(
            'type'          => 'assoc',
            'name'          => 'apple',
            'description'   => 'A type of fruit.',
            'optional'      => false,
          ),
          array(
            'type'          => 'assoc',
            'name'          => 'meal',
            'description'   => 'A type of meal.',
            'optional'      => true,
          ),
        ),
      ) );
      """
    And a wp-cli.yml file:
      """
      require:
        - custom-cmd.php
      """

    When I try `wp foo`
    Then STDOUT should contain:
      """
      usage: wp foo <message> --apple=<apple> [--meal=<meal>]
      """

    When I run `wp help foo`
    Then STDOUT should contain:
      """
      My awesome function command
      """
