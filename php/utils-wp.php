<?php

// Utilities that depend on WordPress code.

namespace WP_CLI\Utils;

function wp_not_installed() {
	if ( !is_blog_installed() && !defined( 'WP_INSTALLING' ) ) {
		\WP_CLI::error(
			"The site you have requested is not installed.\n" .
			'Run `wp core install`.' );
	}
}

function wp_debug_mode() {
	if ( \WP_CLI::get_config( 'debug' ) ) {
		if ( !defined( 'WP_DEBUG' ) )
			define( 'WP_DEBUG', true );

		error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );
	} else {
		\wp_debug_mode();
	}

	// XDebug already sends errors to STDERR
	ini_set( 'display_errors', function_exists( 'xdebug_debug_zval' ) ? false : 'STDERR' );
}

function replace_wp_die_handler() {
	\remove_filter( 'wp_die_handler', '_default_wp_die_handler' );
	\add_filter( 'wp_die_handler', function() { return __NAMESPACE__ . '\\' . 'wp_die_handler'; } );
}

function wp_die_handler( $message ) {
	if ( is_wp_error( $message ) ) {
		$message = $message->get_error_message();
	}

	if ( preg_match( '|^\<h1>(.+?)</h1>|', $message, $matches ) ) {
		$message = $matches[1];
	}

	$message = html_entity_decode( $message );

	\WP_CLI::error( $message );
}

function wp_redirect_handler( $url ) {
	\WP_CLI::warning( 'Some code is trying to do a URL redirect. Backtrace:' );

	ob_start();
	debug_print_backtrace();
	fwrite( STDERR, ob_get_clean() );

	return $url;
}

function maybe_require( $since, $path ) {
	if ( wp_version_compare( $since, '>=' ) ) {
		require $path;
	}
}

function get_upgrader( $class ) {
	if ( !class_exists( '\WP_Upgrader' ) )
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	return new $class( new \WP_CLI\UpgraderSkin );
}

/**
 * Converts a plugin basename back into a friendly slug.
 */
function get_plugin_name( $basename ) {
	if ( false === strpos( $basename, '/' ) )
		$name = basename( $basename, '.php' );
	else
		$name = dirname( $basename );

	return $name;
}

function is_plugin_skipped( $file ) {
	$name = get_plugin_name( str_replace( WP_PLUGIN_DIR . '/', '', $file ) );

	$skipped_plugins = \WP_CLI::get_runner()->config['skip-plugins'];
	if ( true === $skipped_plugins )
		return true;

	if ( ! is_array( $skipped_plugins ) ) {
		$skipped_plugins = explode( ',', $skipped_plugins );
	}

	return in_array( $name, array_filter( $skipped_plugins ) );
}

function get_theme_name( $path ) {
	return basename( $path );
}

function is_theme_skipped( $path ) {
	$name = get_theme_name( $path );

	$skipped_themes = \WP_CLI::get_runner()->config['skip-themes'];
	if ( true === $skipped_themes )
		return true;

	if ( ! is_array( $skipped_themes ) ) {
		$skipped_themes = explode( ',', $skipped_themes );
	}

	return in_array( $name, array_filter( $skipped_themes ) );
}

/**
 * Register the sidebar for unused widgets
 * Core does this in /wp-admin/widgets.php, which isn't helpful
 */
function wp_register_unused_sidebar() {

	register_sidebar(array(
		'name' => __('Inactive Widgets'),
		'id' => 'wp_inactive_widgets',
		'class' => 'inactive-sidebar',
		'description' => __( 'Drag widgets here to remove them from the sidebar but keep their settings.' ),
		'before_widget' => '',
		'after_widget' => '',
		'before_title' => '',
		'after_title' => '',
	));

}

/**
 * Attempts to determine which object cache is being used.
 *
 * Note that the guesses made by this function are based on the WP_Object_Cache classes
 * that define the 3rd party object cache extension. Changes to those classes could render
 * problems with this function's ability to determine which object cache is being used.
 *
 * @return string
 */
function wp_get_cache_type() {
	global $_wp_using_ext_object_cache, $wp_object_cache;

	if ( ! empty( $_wp_using_ext_object_cache ) ) {
		// Test for Memcached PECL extension memcached object cache (https://github.com/tollmanz/wordpress-memcached-backend)
		if ( isset( $wp_object_cache->m ) && is_a( $wp_object_cache->m, 'Memcached' ) ) {
			$message = 'Memcached';

		// Test for Memcache PECL extension memcached object cache (http://wordpress.org/extend/plugins/memcached/)
		} elseif ( isset( $wp_object_cache->mc ) ) {
			$is_memcache = true;
			foreach ( $wp_object_cache->mc as $bucket ) {
				if ( ! is_a( $bucket, 'Memcache' ) )
					$is_memcache = false;
			}

			if ( $is_memcache )
				$message = 'Memcache';

		// Test for Xcache object cache (http://plugins.svn.wordpress.org/xcache/trunk/object-cache.php)
		} elseif ( is_a( $wp_object_cache, 'XCache_Object_Cache' ) ) {
			$message = 'Xcache';

		// Test for WinCache object cache (http://wordpress.org/extend/plugins/wincache-object-cache-backend/)
		} elseif ( class_exists( 'WinCache_Object_Cache' ) ) {
			$message = 'WinCache';

		// Test for APC object cache (http://wordpress.org/extend/plugins/apc/)
		} elseif ( class_exists( 'APC_Object_Cache' ) ) {
			$message = 'APC';

		// Test for Redis Object Cache (https://github.com/alleyinteractive/wp-redis)
		} elseif ( isset( $wp_object_cache->redis ) && is_a( $wp_object_cache->redis, 'Redis' ) ) {
			$message = 'Redis';

		} else {
			$message = 'Unknown';
		}
	} else {
		$message = 'Default';
	}
	return $message;
}

/**
 * Clear all of the caches for memory management
 */
function wp_clear_object_cache() {
	global $wpdb, $wp_object_cache;

	$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	$wp_object_cache->group_ops = array();
	$wp_object_cache->stats = array();
	$wp_object_cache->memcache_debug = array();
	$wp_object_cache->cache = array();

	if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
		$wp_object_cache->__remoteset(); // important
	}
}
