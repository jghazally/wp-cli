<?php

WP_CLI::add_command('user', 'User_Command');

/**
 * Implement user command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class User_Command extends WP_CLI_Command {

	/**
	 * List users.
	 *
	 * @subcommand list
	 * @synopsis [--role=<role>]
	 */
	public function _list( $args, $assoc_args ) {
		global $blog_id;

		$params = array(
			'blog_id' => $blog_id,
			'fields' => 'all_with_meta',
		);

		if ( array_key_exists('role', $assoc_args) ) {
			$params['role'] = $assoc_args['role'];
		}

		$users = get_users( $params );
		$fields = array('ID', 'user_login', 'display_name', 'user_email',
			'user_registered');

		$table = new \cli\Table();

		$table->setHeaders( array_merge($fields, array('roles')) );

		foreach ( $users as $user ) {
			$line = array();

			foreach ( $fields as $field ) {
				$line[] = $user->$field;
			}
			$line[] = implode( ',', $user->roles );

			$table->addRow($line);
		}

		$table->display();

		WP_CLI::line( 'Total: ' . count($users) . ' users' );
	}

	/**
	 * Delete a user.
	 *
	 * @synopsis <id> [--reassign=<id>]
	 */
	public function delete( $args, $assoc_args ) {
		global $blog_id;

		list( $user_id ) = $args;

		$defaults = array( 'reassign' => NULL );

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( wp_delete_user( $user_id, $reassign ) ) {
			WP_CLI::success( "Deleted user $user_id." );
		} else {
			WP_CLI::error( "Failed deleting user $user_id." );
		}
	}

	/**
	 * Create a user.
	 *
	 * @synopsis <user-login> <user-email> [--role=<role>] [--porcelain]
	 */
	public function create( $args, $assoc_args ) {
		global $blog_id;

		list( $user_login, $user_email ) = $args;

		$defaults = array(
			'role' => get_option('default_role'),
			'user_pass' => wp_generate_password(),
			'user_registered' => strftime( "%F %T", time() ),
			'display_name' => false,
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::error( "Invalid role." );
		}

		$user_id = wp_insert_user( array(
			'user_email' => $user_email,
			'user_login' => $user_login,
			'user_pass' => $user_pass,
			'user_registered' => $user_registered,
			'display_name' => $display_name,
			'role' => $role,
		) );

		if ( is_wp_error($user_id) ) {
			WP_CLI::error( $user_id );
		} else {
			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}
		}

		if ( isset( $assoc_args['porcelain'] ) )
			WP_CLI::line( $user_id );
		else
			WP_CLI::success( "Created user $user_id." );
	}

	/**
	 * Update a user.
	 *
	 * @synopsis <id> --<field>=<value>
	 */
	public function update( $args, $assoc_args ) {
		list( $user_id ) = $args;

		if ( empty( $assoc_args ) ) {
			WP_CLI::error( "Need some fields to update." );
		}

		$params = array_merge( array( 'ID' => $user_id ), $assoc_args );

		$updated_id = wp_update_user( $params );

		if ( is_wp_error( $updated_id ) ) {
			WP_CLI::error( $updated_id );
		} else {
			WP_CLI::success( "Updated user $updated_id." );
		}
	}

	/**
	 * Generate users.
	 *
	 * @synopsis [--count=100] [--role=<role>]
	 */
	public function generate( $args, $assoc_args ) {
		global $blog_id;

		$defaults = array(
			'count' => 100,
			'role' => get_option('default_role'),
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::warning( "invalid role." );
			exit;
		}

		$user_count = count_users();

		$total = $user_count['total_users'];

		$limit = $count + $total;

		$notify = new \cli\progress\Bar( 'Generating users', $count );

		for ( $i = $total; $i < $limit; $i++ ) {
			$login = sprintf( 'user_%d_%d', $blog_id, $i );
			$name = "User $i";

			$user_id = wp_insert_user( array(
				'user_login' => $login,
				'user_pass' => $login,
				'nickname' => $name,
				'display_name' => $name,
				'role' => $role
			) );

			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}

			$notify->tick();
		}

		$notify->finish();
	}

	/**
	 * Add a user to a blog
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function add_to_blog( $args, $assoc_args ) {

		$defaults = array(
				'id_or_login'      => $args[0],
				'role'             => $args[1],
			);
		$args = array_merge( $assoc_args, $defaults );

		if ( is_numeric( $args['id_or_login'] ) )
			$user = get_user_by( 'id', $args['id_or_login'] );
		else
			$user = get_user_by( 'login', $args['id_or_login'] );

		if ( empty( $args['id_or_login'] ) || empty( $user ) )
			WP_CLI::error( "Please specify a valid user ID or user login to add to this blog" );

		global $wp_roles;
		if ( empty( $args['role'] ) || ! array_key_exists( $args['role'], $wp_roles->roles ) )
			$args['role'] = get_option( 'default_role' );

		add_user_to_blog( get_current_blog_id(), $user->ID, $args['role'] );
		WP_CLI::success( "Added {$user->user_login} ({$user->ID}) to " . site_url() . " as {$args['role']}" );
	}

	/**
	 * Remove a user from a blog
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function remove_from_blog( $args, $assoc_args ) {

		$defaults = array(
				'id_or_login'      => $args[0],
			);
		$args = array_merge( $assoc_args, $defaults );

		if ( is_numeric( $args['id_or_login'] ) )
			$user = get_user_by( 'id', $args['id_or_login'] );
		else
			$user = get_user_by( 'login', $args['id_or_login'] );

		if ( empty( $args['id_or_login'] ) || empty( $user ) )
			WP_CLI::error( "Please specify a valid user ID or user login to remove from this blog" );

		remove_user_from_blog( $user->ID, get_current_blog_id() );
		WP_CLI::success( "Removed {$user->user_login} ({$user->ID}) from " . site_url() );
	}

}
