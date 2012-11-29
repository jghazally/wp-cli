<?php
WP_CLI::add_command( 'migration-tool', 'Migration_Tool' );

/**
 * Migration Tool Grabs the Data from one database using the command
 * `wp migration-tool get {post_name}`
 * And stores it as a json file in the root directory: {post_name}.json
 *
 * You can then scp this file to your production server and run the
 * command
 * `wp migration-tool insert {post_name}.json`
 * This will import the post, post_meta and featured image information.
 *
 * Limitations:
 * Currently will not import gallery and child posts apart from the
 * featured image.
 * another
 **/
class Migration_Tool extends WP_CLI_Command {

	/**
	 * Get receives the post_name as its argument.
	 * It pulls out all post, post_meta and thumbnail information 
	 * and saves it into a json file
	 **/
	function get($args) {
		$post_meta     = array();
		$output        = array();
		$has_thumbnail = false;

		// Get the fundamental post object
		$post = get_post($this->get_ID_by_slug($args));
		// Get all post meta associated with the post
		if ( !empty($post) ) {
			$post_meta = $this->get_all_post_meta($post->ID);
		}

		// If there is a thumbnail_id then the need to add the thumbnail
		// and change the value to represent the correct ID!
		foreach ( $post_meta as $meta ) {
			if ( $meta->meta_key === '_thumbnail_id' ) {
				$has_thumbnail = true;
				$thumb_id      = $meta->meta_value;
				continue;
			}
		}

		$output['post']      = $post;
		$output['post_meta'] = $post_meta;

		if ( $has_thumbnail ) {
			$output['thumb'] = $this->get_the_thumbnail($thumb_id);
		}
		$output = json_encode($output);

		// Create a json file for scp
		$fh = fopen($post->post_name.'.json', 'w');
		fwrite($fh, $output);
		fclose($fh);

		// scp the file to the server
		// run the import
	}

	/**
	 * Insert receives a file as its argument.
	 * It then opens the file to be read and decodes the json and 
	 * inserts the data into the db.
	 **/
	public function insert($args) {
		$fh         = fopen($args[0], 'r');
		$input      = fread($fh, 99999);
		$input      = json_decode($input);
		$post_array = $this->object_to_array($input->post);

		unset($post_array['ID']);
		$post_id = wp_insert_post($post_array);

		foreach ( $input->post_meta as $post_meta ) {
			update_post_meta($post_id, $post_meta->meta_key, $post_meta->meta_value);
		}

		if ( !empty($input->thumb) ) {
			$thumb_array = $this->object_to_array($input->thumb->post);
			unset($thumb_array['ID']);

			$thumb_id = wp_insert_post($thumb_array);
			foreach ( $input->thumb->post_meta as $thumb_meta ) {
				update_post_meta($thumb_id, $thumb_meta->meta_key, $thumb_meta->meta_value);
			}
			update_post_meta($post_id, '_thumbnail_id', $thumb_id);
		}

	}

	/**
	 * Convert objects into arrays
	 * Due to some weird json encode/decode behaviour
	 * arrays that get encoded seem to end up being decoded as 
	 * objects
	 * Therefore we need a way of churning objects into arrays for wp 
	 * functions
	 **/
	private function object_to_array($obj) {
		if ( is_object($obj) ) {
			$obj = get_object_vars($obj);
		}

		if ( is_array($obj) ) {
			return array_map(array($this, __FUNCTION__), $obj);
		} else {
			return $obj;
		}
	}

	/**
	 * Get the thumbnail post takes a thumbnail id as the argument
	 * It returns the thumb and thumb meta 
	 **/
	private function get_the_thumbnail($thumb_id) {
		$thumb['post']      = get_post($thumb_id);
		$thumb['post_meta'] = $this->get_all_post_meta($thumb_id);
		return $thumb;
	}

	/**
	 * get all post meta takes the post id as its argument 
	 * It then takes all meta key and meta values associated with the 
	 * post id and returns it.
	 **/
	private function get_all_post_meta($post_id) {
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%s", $post_id));
	}

	/**
	 * Get the id by slug takes a post_name / slug as its argument
	 * It returns the post ID
	 **/
	private function get_ID_by_slug($slug) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_name=%s", $slug));
	}
}
