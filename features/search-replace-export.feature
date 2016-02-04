Feature: Search / replace with file export

  Scenario: Search / replace export to STDOUT
    Given a WP install

    When I run `wp search-replace example.com example.net --export`
    Then STDOUT should contain:
      """
      DROP TABLE IF EXISTS `wp_commentmeta`;
      CREATE TABLE `wp_commentmeta`
      """
    And STDOUT should contain:
      """
      INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES ('1', 'siteurl', 'http://example.net', 'yes');
      """

    When I run `wp option get home`
    Then STDOUT should be:
      """
      http://example.com
      """

    When I run `wp search-replace example.com example.net --skip-columns=option_value --export`
    Then STDOUT should contain:
      """
      INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES ('1', 'siteurl', 'http://example.com', 'yes');
      """

    When I run `wp search-replace foo bar --export | tail -n 1`
    Then STDOUT should not contain:
      """
      Success: Made
      """

    When I run `wp search-replace example.com example.net --export > wordpress.sql`
    And I run `wp db import wordpress.sql`
    Then STDOUT should not be empty

    When I run `wp option get home`
    Then STDOUT should be:
      """
      http://example.net
      """

  Scenario: Search / replace export to file
    Given a WP install
    And I run `wp post generate --count=30`

    When I run `wp search-replace example.com example.net --export=wordpress.sql`
    Then STDOUT should contain:
      """
      Success: Made 39 replacements and exported to wordpress.sql
      """
    And STDOUT should be a table containing rows:
      | Table         | Column       | Replacements | Type |
      | wp_options    | option_value | 5            | PHP  |

    When I run `wp option get home`
    Then STDOUT should be:
      """
      http://example.com
      """

    When I run `wp site empty --yes`
    And I run `wp post list --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp db import wordpress.sql`
    Then STDOUT should not be empty

    When I run `wp option get home`
    Then STDOUT should be:
      """
      http://example.net
      """

    When I run `wp post list --format=count`
    Then STDOUT should be:
      """
      31
      """

  Scenario: Search / replace export to file with verbosity
    Given a WP install

    When I run `wp search-replace example.com example.net --export=wordpress.sql --verbose`
    Then STDOUT should contain:
      """
      Checking: wp_posts
      """
    And STDOUT should contain:
      """
      Checking: wp_options
      """

  Scenario: Search / replace export with dry-run
    Given a WP install

    When I try `wp search-replace example.com example.net --export --dry-run`
    Then STDERR should be:
      """
      Error: You cannot supply --dry-run and --export at the same time.
      """

  Scenario: Search / replace shouldn't affect primary key
    Given a WP install
    And I run `wp post create --post_title=foo --porcelain`
    Then save STDOUT as {POST_ID}

    When I run `wp option update {POST_ID} foo`
    And I run `wp option get {POST_ID}`
    Then STDOUT should be:
      """
      foo
      """

    When I run `wp search-replace {POST_ID} 99999999 --export=wordpress.sql`
    And I run `wp db import wordpress.sql`
    Then STDOUT should not be empty

    When I run `wp post get {POST_ID} --field=title`
    Then STDOUT should be:
      """
      foo
      """

    When I try `wp option get {POST_ID}`
    Then STDOUT should be empty

    When I run `wp option get 99999999`
    Then STDOUT should be:
      """
      foo
      """

  Scenario: Search / replace export invalid file
    Given a WP install

    When I try `wp search-replace example.com example.net --export=foo/bar.sql`
    Then STDERR should contain:
      """
      Error: Unable to open "foo/bar.sql" for writing.
      """
