<?php
/**
 * Post functions and post utility function.
 *
 * @package WordPress
 * @subpackage Post
 * @since 1.5.0
 */

/**
 * Retrieve attached file path based on attachment ID.
 *
 * You can optionally send it through the 'get_attached_file' filter, but by
 * default it will just return the file path unfiltered.
 *
 * The function works by getting the single post meta name, named
 * '_wp_attached_file' and returning it. This is a convenience function to
 * prevent looking up the meta name and provide a mechanism for sending the
 * attached filename through a filter.
 *
 * @since 2.0.0
 * @uses apply_filters() Calls 'get_attached_file' on file path and attachment ID.
 *
 * @param int $attachment_id Attachment ID.
 * @param bool $unfiltered Whether to apply filters or not.
 * @return string The file path to the attached file.
 */
function get_attached_file( $attachment_id, $unfiltered = false ) {
	$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
	// If the file is relative, prepend upload dir
	if ( 0 !== strpos($file, '/') && !preg_match('|^.:\\\|', $file) && ( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) )
		$file = $uploads['basedir'] . "/$file";
	if ( $unfiltered )
		return $file;
	return apply_filters( 'get_attached_file', $file, $attachment_id );
}

/**
 * Update attachment file path based on attachment ID.
 *
 * Used to update the file path of the attachment, which uses post meta name
 * '_wp_attached_file' to store the path of the attachment.
 *
 * @since 2.1.0
 * @uses apply_filters() Calls 'update_attached_file' on file path and attachment ID.
 *
 * @param int $attachment_id Attachment ID
 * @param string $file File path for the attachment
 * @return bool False on failure, true on success.
 */
function update_attached_file( $attachment_id, $file ) {
	if ( !get_post( $attachment_id ) )
		return false;

	$file = apply_filters( 'update_attached_file', $file, $attachment_id );

	// Make the file path relative to the upload dir
	if ( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) { // Get upload directory
		if ( 0 === strpos($file, $uploads['basedir']) ) {// Check that the upload base exists in the file path
				$file = str_replace($uploads['basedir'], '', $file); // Remove upload dir from the file path
				$file = ltrim($file, '/');
		}
	}

	return update_post_meta( $attachment_id, '_wp_attached_file', $file );
}

/**
 * Retrieve all children of the post parent ID.
 *
 * Normally, without any enhancements, the children would apply to pages. In the
 * context of the inner workings of WordPress, pages, posts, and attachments
 * share the same table, so therefore the functionality could apply to any one
 * of them. It is then noted that while this function does not work on posts, it
 * does not mean that it won't work on posts. It is recommended that you know
 * what context you wish to retrieve the children of.
 *
 * Attachments may also be made the child of a post, so if that is an accurate
 * statement (which needs to be verified), it would then be possible to get
 * all of the attachments for a post. Attachments have since changed since
 * version 2.5, so this is most likely unaccurate, but serves generally as an
 * example of what is possible.
 *
 * The arguments listed as defaults are for this function and also of the
 * {@link get_posts()} function. The arguments are combined with the
 * get_children defaults and are then passed to the {@link get_posts()}
 * function, which accepts additional arguments. You can replace the defaults in
 * this function, listed below and the additional arguments listed in the
 * {@link get_posts()} function.
 *
 * The 'post_parent' is the most important argument and important attention
 * needs to be paid to the $args parameter. If you pass either an object or an
 * integer (number), then just the 'post_parent' is grabbed and everything else
 * is lost. If you don't specify any arguments, then it is assumed that you are
 * in The Loop and the post parent will be grabbed for from the current post.
 *
 * The 'post_parent' argument is the ID to get the children. The 'numberposts'
 * is the amount of posts to retrieve that has a default of '-1', which is
 * used to get all of the posts. Giving a number higher than 0 will only
 * retrieve that amount of posts.
 *
 * The 'post_type' and 'post_status' arguments can be used to choose what
 * criteria of posts to retrieve. The 'post_type' can be anything, but WordPress
 * post types are 'post', 'pages', and 'attachments'. The 'post_status'
 * argument will accept any post status within the write administration panels.
 *
 * @see get_posts() Has additional arguments that can be replaced.
 * @internal Claims made in the long description might be inaccurate.
 *
 * @since 2.0.0
 *
 * @param mixed $args Optional. User defined arguments for replacing the defaults.
 * @param string $output Optional. Constant for return type, either OBJECT (default), ARRAY_A, ARRAY_N.
 * @return array|bool False on failure and the type will be determined by $output parameter.
 */
function &get_children($args = '', $output = OBJECT) {
	if ( empty( $args ) ) {
		if ( isset( $GLOBALS['post'] ) ) {
			$args = array('post_parent' => (int) $GLOBALS['post']->post_parent );
		} else {
			return false;
		}
	} elseif ( is_object( $args ) ) {
		$args = array('post_parent' => (int) $args->post_parent );
	} elseif ( is_numeric( $args ) ) {
		$args = array('post_parent' => (int) $args);
	}

	$defaults = array(
		'numberposts' => -1, 'post_type' => 'any',
		'post_status' => 'any', 'post_parent' => 0,
	);

	$r = wp_parse_args( $args, $defaults );

	$children = get_posts( $r );
	if ( !$children ) {
		$kids = false;
		return $kids;
	}

	update_post_cache($children);

	foreach ( $children as $key => $child )
		$kids[$child->ID] =& $children[$key];

	if ( $output == OBJECT ) {
		return $kids;
	} elseif ( $output == ARRAY_A ) {
		foreach ( (array) $kids as $kid )
			$weeuns[$kid->ID] = get_object_vars($kids[$kid->ID]);
		return $weeuns;
	} elseif ( $output == ARRAY_N ) {
		foreach ( (array) $kids as $kid )
			$babes[$kid->ID] = array_values(get_object_vars($kids[$kid->ID]));
		return $babes;
	} else {
		return $kids;
	}
}

/**
 * Get extended entry info (<!--more-->).
 *
 * There should not be any space after the second dash and before the word
 * 'more'. There can be text or space(s) after the word 'more', but won't be
 * referenced.
 *
 * The returned array has 'main' and 'extended' keys. Main has the text before
 * the <code><!--more--></code>. The 'extended' key has the content after the
 * <code><!--more--></code> comment.
 *
 * @since 1.0.0
 *
 * @param string $post Post content.
 * @return array Post before ('main') and after ('extended').
 */
function get_extended($post) {
	//Match the new style more links
	if ( preg_match('/<!--more(.*?)?-->/', $post, $matches) ) {
		list($main, $extended) = explode($matches[0], $post, 2);
	} else {
		$main = $post;
		$extended = '';
	}

	// Strip leading and trailing whitespace
	$main = preg_replace('/^[\s]*(.*)[\s]*$/', '\\1', $main);
	$extended = preg_replace('/^[\s]*(.*)[\s]*$/', '\\1', $extended);

	return array('main' => $main, 'extended' => $extended);
}

/**
 * Retrieves post data given a post ID or post object.
 *
 * See {@link sanitize_post()} for optional $filter values. Also, the parameter
 * $post, must be given as a variable, since it is passed by reference.
 *
 * @since 1.5.1
 * @uses $wpdb
 * @link http://codex.wordpress.org/Function_Reference/get_post
 *
 * @param int|object $post Post ID or post object.
 * @param string $output Optional, default is Object. Either OBJECT, ARRAY_A, or ARRAY_N.
 * @param string $filter Optional, default is raw.
 * @return mixed Post data
 */
function &get_post(&$post, $output = OBJECT, $filter = 'raw') {
	global $wpdb;
	$null = null;

	if ( empty($post) ) {
		if ( isset($GLOBALS['post']) )
			$_post = & $GLOBALS['post'];
		else
			return $null;
	} elseif ( is_object($post) && empty($post->filter) ) {
		_get_post_ancestors($post);
		wp_cache_add($post->ID, $post, 'posts');
		$_post = &$post;
	} else {
		if ( is_object($post) )
			$post = $post->ID;
		$post = (int) $post;
		if ( ! $_post = wp_cache_get($post, 'posts') ) {
			$_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d LIMIT 1", $post));
			if ( ! $_post )
				return $null;
			_get_post_ancestors($_post);
			wp_cache_add($_post->ID, $_post, 'posts');
		}
	}

	$_post = sanitize_post($_post, $filter);

	if ( $output == OBJECT ) {
		return $_post;
	} elseif ( $output == ARRAY_A ) {
		$__post = get_object_vars($_post);
		return $__post;
	} elseif ( $output == ARRAY_N ) {
		$__post = array_values(get_object_vars($_post));
		return $__post;
	} else {
		return $_post;
	}
}

/**
 * Retrieve ancestors of a post.
 *
 * @since 2.5.0
 *
 * @param int|object $post Post ID or post object
 * @return array Ancestor IDs or empty array if none are found.
 */
function get_post_ancestors($post) {
	$post = get_post($post);

	if ( !empty($post->ancestors) )
		return $post->ancestors;

	return array();
}

/**
 * Retrieve data from a post field based on Post ID.
 *
 * Examples of the post field will be, 'post_type', 'post_status', 'content',
 * etc and based off of the post object property or key names.
 *
 * The context values are based off of the taxonomy filter functions and
 * supported values are found within those functions.
 *
 * @since 2.3.0
 * @uses sanitize_post_field() See for possible $context values.
 *
 * @param string $field Post field name
 * @param id $post Post ID
 * @param string $context Optional. How to filter the field. Default is display.
 * @return WP_Error|string Value in post field or WP_Error on failure
 */
function get_post_field( $field, $post, $context = 'display' ) {
	$post = (int) $post;
	$post = get_post( $post );

	if ( is_wp_error($post) )
		return $post;

	if ( !is_object($post) )
		return '';

	if ( !isset($post->$field) )
		return '';

	return sanitize_post_field($field, $post->$field, $post->ID, $context);
}

/**
 * Retrieve the mime type of an attachment based on the ID.
 *
 * This function can be used with any post type, but it makes more sense with
 * attachments.
 *
 * @since 2.0.0
 *
 * @param int $ID Optional. Post ID.
 * @return bool|string False on failure or returns the mime type
 */
function get_post_mime_type($ID = '') {
	$post = & get_post($ID);

	if ( is_object($post) )
		return $post->post_mime_type;

	return false;
}

/**
 * Retrieve the post status based on the Post ID.
 *
 * If the post ID is of an attachment, then the parent post status will be given
 * instead.
 *
 * @since 2.0.0
 *
 * @param int $ID Post ID
 * @return string|bool Post status or false on failure.
 */
function get_post_status($ID = '') {
	$post = get_post($ID);

	if ( is_object($post) ) {
		if ( ('attachment' == $post->post_type) && $post->post_parent && ($post->ID != $post->post_parent) )
			return get_post_status($post->post_parent);
		else
			return $post->post_status;
	}

	return false;
}

/**
 * Retrieve all of the WordPress supported post statuses.
 *
 * Posts have a limited set of valid status values, this provides the
 * post_status values and descriptions.
 *
 * @since 2.5.0
 *
 * @return array List of post statuses.
 */
function get_post_statuses( ) {
	$status = array(
		'draft'			=> __('Draft'),
		'pending'		=> __('Pending Review'),
		'private'		=> __('Private'),
		'publish'		=> __('Published')
	);

	return $status;
}

/**
 * Retrieve all of the WordPress support page statuses.
 *
 * Pages have a limited set of valid status values, this provides the
 * post_status values and descriptions.
 *
 * @since 2.5.0
 *
 * @return array List of page statuses.
 */
function get_page_statuses( ) {
	$status = array(
		'draft'			=> __('Draft'),
		'private'		=> __('Private'),
		'publish'		=> __('Published')
	);

	return $status;
}

/**
 * Retrieve the post type of the current post or of a given post.
 *
 * @since 2.1.0
 *
 * @uses $wpdb
 * @uses $posts The Loop post global
 *
 * @param mixed $post Optional. Post object or post ID.
 * @return bool|string post type or false on failure.
 */
function get_post_type($post = false) {
	global $posts;

	if ( false === $post )
		$post = $posts[0];
	elseif ( (int) $post )
		$post = get_post($post, OBJECT);

	if ( is_object($post) )
		return $post->post_type;

	return false;
}

/**
 * Updates the post type for the post ID.
 *
 * The page or post cache will be cleaned for the post ID.
 *
 * @since 2.5.0
 *
 * @uses $wpdb
 *
 * @param int $post_id Post ID to change post type. Not actually optional.
 * @param string $post_type Optional, default is post. Supported values are 'post' or 'page' to name a few.
 * @return int Amount of rows changed. Should be 1 for success and 0 for failure.
 */
function set_post_type( $post_id = 0, $post_type = 'post' ) {
	global $wpdb;

	$post_type = sanitize_post_field('post_type', $post_type, $post_id, 'db');
	$return = $wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET post_type = %s WHERE ID = %d", $post_type, $post_id) );

	if ( 'page' == $post_type )
		clean_page_cache($post_id);
	else
		clean_post_cache($post_id);

	return $return;
}

/**
 * Retrieve list of latest posts or posts matching criteria.
 *
 * The defaults are as follows:
 *     'numberposts' - Default is 5. Total number of posts to retrieve.
 *     'offset' - Default is 0. See {@link WP_Query::query()} for more.
 *     'category' - What category to pull the posts from.
 *     'orderby' - Default is 'post_date'. How to order the posts.
 *     'order' - Default is 'DESC'. The order to retrieve the posts.
 *     'include' - See {@link WP_Query::query()} for more.
 *     'exclude' - See {@link WP_Query::query()} for more.
 *     'meta_key' - See {@link WP_Query::query()} for more.
 *     'meta_value' - See {@link WP_Query::query()} for more.
 *     'post_type' - Default is 'post'. Can be 'page', or 'attachment' to name a few.
 *     'post_parent' - The parent of the post or post type.
 *     'post_status' - Default is 'published'. Post status to retrieve.
 *
 * @since 1.2.0
 * @uses $wpdb
 * @uses WP_Query::query() See for more default arguments and information.
 * @link http://codex.wordpress.org/Template_Tags/get_posts
 *
 * @param array $args Optional. Override defaults.
 * @return array List of posts.
 */
function get_posts($args = null) {
	$defaults = array(
		'numberposts' => 5, 'offset' => 0,
		'category' => 0, 'orderby' => 'post_date',
		'order' => 'DESC', 'include' => '',
		'exclude' => '', 'meta_key' => '',
		'meta_value' =>'', 'post_type' => 'post',
		'suppress_filters' => true
	);

	$r = wp_parse_args( $args, $defaults );
	if ( empty( $r['post_status'] ) )
		$r['post_status'] = ( 'attachment' == $r['post_type'] ) ? 'inherit' : 'publish';
	if ( ! empty($r['numberposts']) )
		$r['posts_per_page'] = $r['numberposts'];
	if ( ! empty($r['category']) )
		$r['cat'] = $r['category'];
	if ( ! empty($r['include']) ) {
		$incposts = preg_split('/[\s,]+/',$r['include']);
		$r['posts_per_page'] = count($incposts);  // only the number of posts included
		$r['post__in'] = $incposts;
	} elseif ( ! empty($r['exclude']) )
		$r['post__not_in'] = preg_split('/[\s,]+/',$r['exclude']);

	$r['caller_get_posts'] = true;

	$get_posts = new WP_Query;
	return $get_posts->query($r);

}

//
// Post meta functions
//

/**
 * Add meta data field to a post.
 *
 * Post meta data is called "Custom Fields" on the Administration Panels.
 *
 * @since 1.5.0
 * @uses $wpdb
 * @link http://codex.wordpress.org/Function_Reference/add_post_meta
 *
 * @param int $post_id Post ID.
 * @param string $key Metadata name.
 * @param mixed $value Metadata value.
 * @param bool $unique Optional, default is false. Whether the same key should not be added.
 * @return bool False for failure. True for success.
 */
function add_post_meta($post_id, $meta_key, $meta_value, $unique = false) {
	global $wpdb;

	// make sure meta is added to the post, not a revision
	if ( $the_post = wp_is_post_revision($post_id) )
		$post_id = $the_post;

	// expected_slashed ($meta_key)
	$meta_key = stripslashes($meta_key);

	if ( $unique && $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE meta_key = %s AND post_id = %d", $meta_key, $post_id ) ) )
		return false;

	$meta_value = maybe_serialize( stripslashes_deep($meta_value) );

	$wpdb->insert( $wpdb->postmeta, compact( 'post_id', 'meta_key', 'meta_value' ) );

	wp_cache_delete($post_id, 'post_meta');

	return true;
}

/**
 * Remove metadata matching criteria from a post.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @since 1.5.0
 * @uses $wpdb
 * @link http://codex.wordpress.org/Function_Reference/delete_post_meta
 *
 * @param int $post_id post ID
 * @param string $meta_key Metadata name.
 * @param mixed $meta_value Optional. Metadata value.
 * @return bool False for failure. True for success.
 */
function delete_post_meta($post_id, $meta_key, $meta_value = '') {
	global $wpdb;

	// make sure meta is added to the post, not a revision
	if ( $the_post = wp_is_post_revision($post_id) )
		$post_id = $the_post;

	// expected_slashed ($meta_key, $meta_value)
	$meta_key = stripslashes( $meta_key );
	$meta_value = maybe_serialize( stripslashes_deep($meta_value) );

	if ( empty( $meta_value ) )
		$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta_key ) );
	else
		$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $post_id, $meta_key, $meta_value ) );

	if ( !$meta_id )
		return false;

	if ( empty( $meta_value ) )
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta_key ) );
	else
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $post_id, $meta_key, $meta_value ) );

	wp_cache_delete($post_id, 'post_meta');

	return true;
}

/**
 * Retrieve post meta field for a post.
 *
 * @since 1.5.0
 * @uses $wpdb
 * @link http://codex.wordpress.org/Function_Reference/get_post_meta
 *
 * @param int $post_id Post ID.
 * @param string $key The meta key to retrieve.
 * @param bool $single Whether to return a single value.
 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
 */
function get_post_meta($post_id, $key, $single = false) {
	$post_id = (int) $post_id;

	$meta_cache = wp_cache_get($post_id, 'post_meta');

	if ( !$meta_cache ) {
		update_postmeta_cache($post_id);
		$meta_cache = wp_cache_get($post_id, 'post_meta');
	}

	if ( isset($meta_cache[$key]) ) {
		if ( $single ) {
			return maybe_unserialize( $meta_cache[$key][0] );
		} else {
			return array_map('maybe_unserialize', $meta_cache[$key]);
		}
	}

	return '';
}

/**
 * Update post meta field based on post ID.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and post ID.
 *
 * If the meta field for the post does not exist, it will be added.
 *
 * @since 1.5
 * @uses $wpdb
 * @link http://codex.wordpress.org/Function_Reference/update_post_meta
 *
 * @param int $post_id Post ID.
 * @param string $key Metadata key.
 * @param mixed $value Metadata value.
 * @param mixed $prev_value Optional. Previous value to check before removing.
 * @return bool False on failure, true if success.
 */
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
	global $wpdb;

	// make sure meta is added to the post, not a revision
	if ( $the_post = wp_is_post_revision($post_id) )
		$post_id = $the_post;

	// expected_slashed ($meta_key)
	$meta_key = stripslashes($meta_key);

	if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE meta_key = %s AND post_id = %d", $meta_key, $post_id ) ) ) {
		return add_post_meta($post_id, $meta_key, $meta_value);
	}

	$meta_value = maybe_serialize( stripslashes_deep($meta_value) );

	$data  = compact( 'meta_value' );
	$where = compact( 'meta_key', 'post_id' );

	if ( !empty( $prev_value ) ) {
		$prev_value = maybe_serialize($prev_value);
		$where['meta_value'] = $prev_value;
	}

	$wpdb->update( $wpdb->postmeta, $data, $where );
	wp_cache_delete($post_id, 'post_meta');
	return true;
}

/**
 * Delete everything from post meta matching meta key.
 *
 * @since 2.3.0
 * @uses $wpdb
 *
 * @param string $post_meta_key Key to search for when deleting.
 * @return bool Whether the post meta key was deleted from the database
 */
function delete_post_meta_by_key($post_meta_key) {
	global $wpdb;
	if ( $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $post_meta_key)) ) {
		/** @todo Get post_ids and delete cache */
		// wp_cache_delete($post_id, 'post_meta');
		return true;
	}
	return false;
}

/**
 * Retrieve post meta fields, based on post ID.
 *
 * The post meta fields are retrieved from the cache, so the function is
 * optimized to be called more than once. It also applies to the functions, that
 * use this function.
 *
 * @since 1.2.0
 * @link http://codex.wordpress.org/Function_Reference/get_post_custom
 *
 * @uses $id Current Loop Post ID
 *
 * @param int $post_id post ID
 * @return array
 */
function get_post_custom($post_id = 0) {
	global $id;

	if ( !$post_id )
		$post_id = (int) $id;

	$post_id = (int) $post_id;

	if ( ! wp_cache_get($post_id, 'post_meta') )
		update_postmeta_cache($post_id);

	return wp_cache_get($post_id, 'post_meta');
}

/**
 * Retrieve meta field names for a post.
 *
 * If there are no meta fields, then nothing (null) will be returned.
 *
 * @since 1.2.0
 * @link http://codex.wordpress.org/Function_Reference/get_post_custom_keys
 *
 * @param int $post_id post ID
 * @return array|null Either array of the keys, or null if keys could not be retrieved.
 */
function get_post_custom_keys( $post_id = 0 ) {
	$custom = get_post_custom( $post_id );

	if ( !is_array($custom) )
		return;

	if ( $keys = array_keys($custom) )
		return $keys;
}

/**
 * Retrieve values for a custom post field.
 *
 * The parameters must not be considered optional. All of the post meta fields
 * will be retrieved and only the meta field key values returned.
 *
 * @since 1.2.0
 * @link http://codex.wordpress.org/Function_Reference/get_post_custom_values
 *
 * @param string $key Meta field key.
 * @param int $post_id Post ID
 * @return array Meta field values.
 */
function get_post_custom_values( $key = '', $post_id = 0 ) {
	$custom = get_post_custom($post_id);

	return isset($custom[$key]) ? $custom[$key] : null;
}

/**
 * Check if post is sticky.
 *
 * Sticky posts should remain at the top of The Loop. If the post ID is not
 * given, then The Loop ID for the current post will be used.
 *
 * @since 2.7.0
 *
 * @param int $post_id Optional. Post ID.
 * @return bool Whether post is sticky (true) or not sticky (false).
 */
function is_sticky($post_id = null) {
	global $id;

	$post_id = absint($post_id);

	if ( !$post_id )
		$post_id = absint($id);

	$stickies = get_option('sticky_posts');

	if ( !is_array($stickies) )
		return false;

	if ( in_array($post_id, $stickies) )
		return true;

	return false;
}

/**
 * Sanitize every post field.
 *
 * If the context is 'raw', then the post object or array will just be returned.
 *
 * @since 2.3.0
 * @uses sanitize_post_field() Used to sanitize the fields.
 *
 * @param object|array $post The Post Object or Array
 * @param string $context Optional, default is 'display'. How to sanitize post fields.
 * @return object|array The now sanitized Post Object or Array (will be the same type as $post)
 */
function sanitize_post($post, $context = 'display') {
	if ( 'raw' == $context )
		return $post;
	if ( is_object($post) ) {
		if ( !isset($post->ID) )
			$post->ID = 0;
		foreach ( array_keys(get_object_vars($post)) as $field )
			$post->$field = sanitize_post_field($field, $post->$field, $post->ID, $context);
		$post->filter = $context;
	} else {
		if ( !isset($post['ID']) )
			$post['ID'] = 0;
		foreach ( array_keys($post) as $field )
			$post[$field] = sanitize_post_field($field, $post[$field], $post['ID'], $context);
		$post['filter'] = $context;
	}

	return $post;
}

/**
 * Sanitize post field based on context.
 *
 * Possible context values are: raw, edit, db, attribute, js, and display. The
 * display context is used by default.
 *
 * @since 2.3.0
 *
 * @param string $field The Post Object field name.
 * @param mixed $value The Post Object value.
 * @param int $post_id Post ID.
 * @param string $context How to sanitize post fields.
 * @return mixed Sanitized value.
 */
function sanitize_post_field($field, $value, $post_id, $context) {
	$int_fields = array('ID', 'post_parent', 'menu_order');
	if ( in_array($field, $int_fields) )
		$value = (int) $value;

	if ( 'raw' == $context )
		return $value;

	$prefixed = false;
	if ( false !== strpos($field, 'post_') ) {
		$prefixed = true;
		$field_no_prefix = str_replace('post_', '', $field);
	}

	if ( 'edit' == $context ) {
		$format_to_edit = array('post_content', 'post_excerpt', 'post_title', 'post_password');

		if ( $prefixed ) {
			$value = apply_filters("edit_$field", $value, $post_id);
			// Old school
			$value = apply_filters("${field_no_prefix}_edit_pre", $value, $post_id);
		} else {
			$value = apply_filters("edit_post_$field", $value, $post_id);
		}

		if ( in_array($field, $format_to_edit) ) {
			if ( 'post_content' == $field )
				$value = format_to_edit($value, user_can_richedit());
			else
				$value = format_to_edit($value);
		} else {
			$value = attribute_escape($value);
		}
	} else if ( 'db' == $context ) {
		if ( $prefixed ) {
			$value = apply_filters("pre_$field", $value);
			$value = apply_filters("${field_no_prefix}_save_pre", $value);
		} else {
			$value = apply_filters("pre_post_$field", $value);
			$value = apply_filters("${field}_pre", $value);
		}
	} else {
		// Use display filters by default.
		if ( $prefixed )
			$value = apply_filters($field, $value, $post_id, $context);
		else
			$value = apply_filters("post_$field", $value, $post_id, $context);
	}

	if ( 'attribute' == $context )
		$value = attribute_escape($value);
	else if ( 'js' == $context )
		$value = js_escape($value);

	return $value;
}

/**
 * Make a post sticky.
 *
 * Sticky posts should be displayed at the top of the front page.
 *
 * @since 2.7.0
 *
 * @param int $post_id Post ID.
 */
function stick_post($post_id) {
	$stickies = get_option('sticky_posts');

	if ( !is_array($stickies) )
		$stickies = array($post_id);

	if ( ! in_array($post_id, $stickies) )
		$stickies[] = $post_id;

	update_option('sticky_posts', $stickies);
}

/**
 * Unstick a post.
 *
 * Sticky posts should be displayed at the top of the front page.
 *
 * @since 2.7.0
 *
 * @param int $post_id Post ID.
 */
function unstick_post($post_id) {
	$stickies = get_option('sticky_posts');

	if ( !is_array($stickies) )
		return;

	if ( ! in_array($post_id, $stickies) )
		return;

	$offset = array_search($post_id, $stickies);
	if ( false === $offset )
		return;

	array_splice($stickies, $offset, 1);

	update_option('sticky_posts', $stickies);
}

/**
 * Count number of posts of a post type and is user has permissions to view.
 *
 * This function provides an efficient method of finding the amount of post's
 * type a blog has. Another method is to count the amount of items in
 * get_posts(), but that method has a lot of overhead with doing so. Therefore,
 * when developing for 2.5+, use this function instead.
 *
 * The $perm parameter checks for 'readable' value and if the user can read
 * private posts, it will display that for the user that is signed in.
 *
 * @since 2.5.0
 * @link http://codex.wordpress.org/Template_Tags/wp_count_posts
 *
 * @param string $type Optional. Post type to retrieve count
 * @param string $perm Optional. 'readable' or empty.
 * @return object Number of posts for each status
 */
function wp_count_posts( $type = 'post', $perm = '' ) {
	global $wpdb;

	$user = wp_get_current_user();

	$cache_key = $type;

	$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = %s";
	if ( 'readable' == $perm && is_user_logged_in() ) {
		if ( !current_user_can("read_private_{$type}s") ) {
			$cache_key .= '_' . $perm . '_' . $user->ID;
			$query .= " AND (post_status != 'private' OR ( post_author = '$user->ID' AND post_status = 'private' ))";
		}
	}
	$query .= ' GROUP BY post_status';

	$count = wp_cache_get($cache_key, 'counts');
	if ( false !== $count )
		return $count;

	$count = $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );

	$stats = array( );
	foreach( (array) $count as $row_num => $row ) {
		$stats[$row['post_status']] = $row['num_posts'];
	}

	$stats = (object) $stats;
	wp_cache_set($cache_key, $stats, 'counts');

	return $stats;
}


/**
 * Count number of attachments for the mime type(s).
 *
 * If you set the optional mime_type parameter, then an array will still be
 * returned, but will only have the item you are looking for. It does not give
 * you the number of attachments that are children of a post. You can get that
 * by counting the number of children that post has.
 *
 * @since 2.5.0
 *
 * @param string|array $mime_type Optional. Array or comma-separated list of MIME patterns.
 * @return array Number of posts for each mime type.
 */
function wp_count_attachments( $mime_type = '' ) {
	global $wpdb;

	$and = wp_post_mime_type_where( $mime_type );
	$count = $wpdb->get_results( "SELECT post_mime_type, COUNT( * ) AS num_posts FROM $wpdb->posts WHERE post_type = 'attachment' $and GROUP BY post_mime_type", ARRAY_A );

	$stats = array( );
	foreach( (array) $count as $row ) {
		$stats[$row['post_mime_type']] = $row['num_posts'];
	}

	return (object) $stats;
}

/**
 * Check a MIME-Type against a list.
 *
 * If the wildcard_mime_types parameter is a string, it must be comma separated
 * list. If the real_mime_types is a string, it is also comma separated to
 * create the list.
 *
 * @since 2.5.0
 *
 * @param string|array $wildcard_mime_types e.g. audio/mpeg or image (same as image/*) or flash (same as *flash*)
 * @param string|array $real_mime_types post_mime_type values
 * @return array array(wildcard=>array(real types))
 */
function wp_match_mime_types($wildcard_mime_types, $real_mime_types) {
	$matches = array();
	if ( is_string($wildcard_mime_types) )
		$wildcard_mime_types = array_map('trim', explode(',', $wildcard_mime_types));
	if ( is_string($real_mime_types) )
		$real_mime_types = array_map('trim', explode(',', $real_mime_types));
	$wild = '[-._a-z0-9]*';
	foreach ( (array) $wildcard_mime_types as $type ) {
		$type = str_replace('*', $wild, $type);
		$patternses[1][$type] = "^$type$";
		if ( false === strpos($type, '/') ) {
			$patternses[2][$type] = "^$type/";
			$patternses[3][$type] = $type;
		}
	}
	asort($patternses);
	foreach ( $patternses as $patterns )
		foreach ( $patterns as $type => $pattern )
			foreach ( (array) $real_mime_types as $real )
				if ( preg_match("#$pattern#", $real) && ( empty($matches[$type]) || false === array_search($real, $matches[$type]) ) )
					$matches[$type][] = $real;
	return $matches;
}

/**
 * Convert MIME types into SQL.
 *
 * @since 2.5.0
 *
 * @param string|array $mime_types List of mime types or comma separated string of mime types.
 * @return string The SQL AND clause for mime searching.
 */
function wp_post_mime_type_where($post_mime_types) {
	$where = '';
	$wildcards = array('', '%', '%/%');
	if ( is_string($post_mime_types) )
		$post_mime_types = array_map('trim', explode(',', $post_mime_types));
	foreach ( (array) $post_mime_types as $mime_type ) {
		$mime_type = preg_replace('/\s/', '', $mime_type);
		$slashpos = strpos($mime_type, '/');
		if ( false !== $slashpos ) {
			$mime_group = preg_replace('/[^-*.a-zA-Z0-9]/', '', substr($mime_type, 0, $slashpos));
			$mime_subgroup = preg_replace('/[^-*.a-zA-Z0-9]/', '', substr($mime_type, $slashpos + 1));
			if ( empty($mime_subgroup) )
				$mime_subgroup = '*';
			else
				$mime_subgroup = str_replace('/', '', $mime_subgroup);
			$mime_pattern = "$mime_group/$mime_subgroup";
		} else {
			$mime_pattern = preg_replace('/[^-*.a-zA-Z0-9]/', '', $mime_type);
			if ( false === strpos($mime_pattern, '*') )
				$mime_pattern .= '/*';
		}

		$mime_pattern = preg_replace('/\*+/', '%', $mime_pattern);

		if ( in_array( $mime_type, $wildcards ) )
			return '';

		if ( false !== strpos($mime_pattern, '%') )
			$wheres[] = "post_mime_type LIKE '$mime_pattern'";
		else
			$wheres[] = "post_mime_type = '$mime_pattern'";
	}
	if ( !empty($wheres) )
		$where = ' AND (' . join(' OR ', $wheres) . ') ';
	return $where;
}

/**
 * Removes a post, attachment, or page.
 *
 * When the post and page goes, everything that is tied to it is deleted also.
 * This includes comments, post meta fields, and terms associated with the post.
 *
 * @since 1.0.0
 * @uses do_action() Calls 'deleted_post' hook on post ID.
 *
 * @param int $postid Post ID.
 * @return mixed
 */
function wp_delete_post($postid = 0) {
	global $wpdb, $wp_rewrite;

	if ( !$post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $postid)) )
		return $post;

	if ( 'attachment' == $post->post_type )
		return wp_delete_attachment($postid);

	do_action('delete_post', $postid);

	/** @todo delete for pluggable post taxonomies too */
	wp_delete_object_term_relationships($postid, array('category', 'post_tag'));

	$parent_data = array( 'post_parent' => $post->post_parent );
	$parent_where = array( 'post_parent' => $postid );

	if ( 'page' == $post->post_type) {
	 	// if the page is defined in option page_on_front or post_for_posts,
		// adjust the corresponding options
		if ( get_option('page_on_front') == $postid ) {
			update_option('show_on_front', 'posts');
			delete_option('page_on_front');
		}
		if ( get_option('page_for_posts') == $postid ) {
			delete_option('page_for_posts');
		}

		// Point children of this page to its parent, also clean the cache of affected children
		$children_query = $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_parent = %d AND post_type='page'", $postid);
		$children = $wpdb->get_results($children_query);

		$wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => 'page' ) );
	}

	// Do raw query.  wp_get_post_revisions() is filtered
	$revision_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'revision'", $postid ) );
	// Use wp_delete_post (via wp_delete_post_revision) again.  Ensures any meta/misplaced data gets cleaned up.
	foreach ( $revision_ids as $revision_id )
		wp_delete_post_revision( $revision_id );

	// Point all attachments to this post up one level
	$wpdb->update( $wpdb->posts, $parent_data, $parent_where + array( 'post_type' => 'attachment' ) );

	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->posts WHERE ID = %d", $postid ));

	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->comments WHERE comment_post_ID = %d", $postid ));

	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d", $postid ));

	if ( 'page' == $post->post_type ) {
		clean_page_cache($postid);

		foreach ( (array) $children as $child )
			clean_page_cache($child->ID);

		$wp_rewrite->flush_rules();
	} else {
		clean_post_cache($postid);
	}

	do_action('deleted_post', $postid);

	return $post;
}

/**
 * Retrieve the list of categories for a post.
 *
 * Compatibility layer for themes and plugins. Also an easy layer of abstraction
 * away from the complexity of the taxonomy layer.
 *
 * @since 2.1.0
 *
 * @uses wp_get_object_terms() Retrieves the categories. Args details can be found here.
 *
 * @param int $post_id Optional. The Post ID.
 * @param array $args Optional. Overwrite the defaults.
 * @return array
 */
function wp_get_post_categories( $post_id = 0, $args = array() ) {
	$post_id = (int) $post_id;

	$defaults = array('fields' => 'ids');
	$args = wp_parse_args( $args, $defaults );

	$cats = wp_get_object_terms($post_id, 'category', $args);
	return $cats;
}

/**
 * Retrieve the tags for a post.
 *
 * There is only one default for this function, called 'fields' and by default
 * is set to 'all'. There are other defaults that can be override in
 * {@link wp_get_object_terms()}.
 *
 * @package WordPress
 * @subpackage Post
 * @since 2.3.0
 *
 * @uses wp_get_object_terms() Gets the tags for returning. Args can be found here
 *
 * @param int $post_id Optional. The Post ID
 * @param array $args Optional. Overwrite the defaults
 * @return array List of post tags.
 */
function wp_get_post_tags( $post_id = 0, $args = array() ) {
	$post_id = (int) $post_id;

	$defaults = array('fields' => 'all');
	$args = wp_parse_args( $args, $defaults );

	$tags = wp_get_object_terms($post_id, 'post_tag', $args);

	return $tags;
}

/**
 * Retrieve number of recent posts.
 *
 * @since 1.0.0
 * @uses $wpdb
 *
 * @param int $num Optional, default is 10. Number of posts to get.
 * @return array List of posts.
 */
function wp_get_recent_posts($num = 10) {
	global $wpdb;

	// Set the limit clause, if we got a limit
	$num = (int) $num;
	if ($num) {
		$limit = "LIMIT $num";
	}

	$sql = "SELECT * FROM $wpdb->posts WHERE post_type = 'post' ORDER BY post_date DESC $limit";
	$result = $wpdb->get_results($sql,ARRAY_A);

	return $result ? $result : array();
}

/**
 * Retrieve a single post, based on post ID.
 *
 * Has categories in 'post_category' property or key. Has tags in 'tags_input'
 * property or key.
 *
 * @since 1.0.0
 *
 * @param int $postid Post ID.
 * @param string $mode How to return result, either OBJECT, ARRAY_N, or ARRAY_A.
 * @return object|array Post object or array holding post contents and information
 */
function wp_get_single_post($postid = 0, $mode = OBJECT) {
	$postid = (int) $postid;

	$post = get_post($postid, $mode);

	// Set categories and tags
	if($mode == OBJECT) {
		$post->post_category = wp_get_post_categories($postid);
		$post->tags_input = wp_get_post_tags($postid, array('fields' => 'names'));
	}
	else {
		$post['post_category'] = wp_get_post_categories($postid);
		$post['tags_input'] = wp_get_post_tags($postid, array('fields' => 'names'));
	}

	return $post;
}

/**
 * Insert a post.
 *
 * If the $postarr parameter has 'ID' set to a value, then post will be updated.
 *
 * You can set the post date manually, but setting the values for 'post_date'
 * and 'post_date_gmt' keys. You can close the comments or open the comments by
 * setting the value for 'comment_status' key.
 *
 * The defaults for the parameter $postarr are:
 *     'post_status' - Default is 'draft'.
 *     'post_type' - Default is 'post'.
 *     'post_author' - Default is current user ID. The ID of the user, who added
 *         the post.
 *     'ping_status' - Default is the value in default ping status option.
 *         Whether the attachment can accept pings.
 *     'post_parent' - Default is 0. Set this for the post it belongs to, if
 *         any.
 *     'menu_order' - Default is 0. The order it is displayed.
 *     'to_ping' - Whether to ping.
 *     'pinged' - Default is empty string.
 *     'post_password' - Default is empty string. The password to access the
 *         attachment.
 *     'guid' - Global Unique ID for referencing the attachment.
 *     'post_content_filtered' - Post content filtered.
 *     'post_excerpt' - Post excerpt.
 *
 * @since 1.0.0
 * @uses $wpdb
 * @uses $wp_rewrite
 * @uses $user_ID
 *
 * @param array $postarr Optional. Override defaults.
 * @param bool $wp_error Optional. Allow return of WP_Error on failure.
 * @return int|WP_Error The value 0 or WP_Error on failure. The post ID on success.
 */
function wp_insert_post($postarr = array(), $wp_error = false) {
	global $wpdb, $wp_rewrite, $user_ID;

	$defaults = array('post_status' => 'draft', 'post_type' => 'post', 'post_author' => $user_ID,
		'ping_status' => get_option('default_ping_status'), 'post_parent' => 0,
		'menu_order' => 0, 'to_ping' =>  '', 'pinged' => '', 'post_password' => '',
		'guid' => '', 'post_content_filtered' => '', 'post_excerpt' => '', 'import_id' => 0);

	$postarr = wp_parse_args($postarr, $defaults);
	$postarr = sanitize_post($postarr, 'db');

	// export array as variables
	extract($postarr, EXTR_SKIP);

	// Are we updating or creating?
	$update = false;
	if ( !empty($ID) ) {
		$update = true;
		$previous_status = get_post_field('post_status', $ID);
	} else {
		$previous_status = 'new';
	}

	if ( ('' == $post_content) && ('' == $post_title) && ('' == $post_excerpt) ) {
		if ( $wp_error )
			return new WP_Error('empty_content', __('Content, title, and excerpt are empty.'));
		else
			return 0;
	}

	// Make sure we set a valid category
	if ( empty($post_category) || 0 == count($post_category) || !is_array($post_category) ) {
		$post_category = array(get_option('default_category'));
	}

	//Set the default tag list
	if ( !isset($tags_input) )
		$tags_input = array();

	if ( empty($post_author) )
		$post_author = $user_ID;

	if ( empty($post_status) )
		$post_status = 'draft';

	if ( empty($post_type) )
		$post_type = 'post';

	$post_ID = 0;

	// Get the post ID and GUID
	if ( $update ) {
		$post_ID = (int) $ID;
		$guid = get_post_field( 'guid', $post_ID );
	}

	// Don't allow contributors to set to set the post slug for pending review posts
	if ( 'pending' == $post_status && !current_user_can( 'publish_posts' ) )
		$post_name = '';

	// Create a valid post name.  Drafts and pending posts are allowed to have an empty
	// post name.
	if ( empty($post_name) ) {
		if ( !in_array( $post_status, array( 'draft', 'pending' ) ) )
			$post_name = sanitize_title($post_title);
	} else {
		$post_name = sanitize_title($post_name);
	}

	// If the post date is empty (due to having been new or a draft) and status is not 'draft' or 'pending', set date to now
	if ( empty($post_date) || '0000-00-00 00:00:00' == $post_date )
		$post_date = current_time('mysql');

	if ( empty($post_date_gmt) || '0000-00-00 00:00:00' == $post_date_gmt ) {
		if ( !in_array( $post_status, array( 'draft', 'pending' ) ) )
			$post_date_gmt = get_gmt_from_date($post_date);
		else
			$post_date_gmt = '0000-00-00 00:00:00';
	}

	if ( $update || '0000-00-00 00:00:00' == $post_date ) {
		$post_modified     = current_time( 'mysql' );
		$post_modified_gmt = current_time( 'mysql', 1 );
	} else {
		$post_modified     = $post_date;
		$post_modified_gmt = $post_date_gmt;
	}

	if ( 'publish' == $post_status ) {
		$now = gmdate('Y-m-d H:i:59');
		if ( mysql2date('U', $post_date_gmt) > mysql2date('U', $now) )
			$post_status = 'future';
	}

	if ( empty($comment_status) ) {
		if ( $update )
			$comment_status = 'closed';
		else
			$comment_status = get_option('default_comment_status');
	}
	if ( empty($ping_status) )
		$ping_status = get_option('default_ping_status');

	if ( isset($to_ping) )
		$to_ping = preg_replace('|\s+|', "\n", $to_ping);
	else
		$to_ping = '';

	if ( ! isset($pinged) )
		$pinged = '';

	if ( isset($post_parent) )
		$post_parent = (int) $post_parent;
	else
		$post_parent = 0;

	if ( !empty($post_ID) ) {
		if ( $post_parent == $post_ID ) {
			// Post can't be its own parent
			$post_parent = 0;
		} elseif ( !empty($post_parent) ) {
			$parent_post = get_post($post_parent);
			// Check for circular dependency
			if ( $parent_post->post_parent == $post_ID )
				$post_parent = 0;
		}
	}

	if ( isset($menu_order) )
		$menu_order = (int) $menu_order;
	else
		$menu_order = 0;

	if ( !isset($post_password) || 'private' == $post_status )
		$post_password = '';

	if ( !in_array( $post_status, array( 'draft', 'pending' ) ) ) {
		$post_name_check = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d AND post_parent = %d LIMIT 1", $post_name, $post_type, $post_ID, $post_parent));

		if ($post_name_check || in_array($post_name, $wp_rewrite->feeds) ) {
			$suffix = 2;
			do {
				$alt_post_name = substr($post_name, 0, 200-(strlen($suffix)+1)). "-$suffix";
				$post_name_check = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d AND post_parent = %d LIMIT 1", $alt_post_name, $post_type, $post_ID, $post_parent));
				$suffix++;
			} while ($post_name_check);
			$post_name = $alt_post_name;
		}
	}

	// expected_slashed (everything!)
	$data = compact( array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'guid' ) );
	$data = apply_filters('wp_insert_post_data', $data, $postarr);
	$data = stripslashes_deep( $data );
	$where = array( 'ID' => $post_ID );

	if ($update) {
		do_action( 'pre_post_update', $post_ID );
		if ( false === $wpdb->update( $wpdb->posts, $data, $where ) ) {
			if ( $wp_error )
				return new WP_Error('db_update_error', __('Could not update post in the database'), $wpdb->last_error);
			else
				return 0;
		}
	} else {
		if ( isset($post_mime_type) )
			$data['post_mime_type'] = stripslashes( $post_mime_type ); // This isn't in the update
		// If there is a suggested ID, use it if not already present
		if ( !empty($import_id) ) {
			$import_id = (int) $import_id;
			if ( ! $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID = %d", $import_id) ) ) {
				$data['ID'] = $import_id;
			}
		}
		if ( false === $wpdb->insert( $wpdb->posts, $data ) ) {
			if ( $wp_error )
				return new WP_Error('db_insert_error', __('Could not insert post into the database'), $wpdb->last_error);
			else
				return 0;
		}
		$post_ID = (int) $wpdb->insert_id;

		// use the newly generated $post_ID
		$where = array( 'ID' => $post_ID );
	}

	if ( empty($post_name) && !in_array( $post_status, array( 'draft', 'pending' ) ) ) {
		$post_name = sanitize_title($post_title, $post_ID);
		$wpdb->update( $wpdb->posts, compact( 'post_name' ), $where );
	}

	wp_set_post_categories( $post_ID, $post_category );
	wp_set_post_tags( $post_ID, $tags_input );

	$current_guid = get_post_field( 'guid', $post_ID );

	if ( 'page' == $post_type )
		clean_page_cache($post_ID);
	else
		clean_post_cache($post_ID);

	// Set GUID
	if ( !$update && '' == $current_guid )
		$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post_ID ) ), $where );

	$post = get_post($post_ID);

	if ( !empty($page_template) && 'page' == $post_type ) {
		$post->page_template = $page_template;
		$page_templates = get_page_templates();
		if ( 'default' != $page_template && !in_array($page_template, $page_templates) ) {
			if ( $wp_error )
				return new WP_Error('invalid_page_template', __('The page template is invalid.'));
			else
				return 0;
		}
		update_post_meta($post_ID, '_wp_page_template',  $page_template);
	}

	wp_transition_post_status($post_status, $previous_status, $post);

	if ( $update)
		do_action('edit_post', $post_ID, $post);

	do_action('save_post', $post_ID, $post);
	do_action('wp_insert_post', $post_ID, $post);

	return $post_ID;
}

/**
 * Update a post with new post data.
 *
 * The date does not have to be set for drafts. You can set the date and it will
 * not be overridden.
 *
 * @since 1.0.0
 *
 * @param array|object $postarr Post data.
 * @return int 0 on failure, Post ID on success.
 */
function wp_update_post($postarr = array()) {
	if ( is_object($postarr) )
		$postarr = get_object_vars($postarr);

	// First, get all of the original fields
	$post = wp_get_single_post($postarr['ID'], ARRAY_A);

	// Escape data pulled from DB.
	$post = add_magic_quotes($post);

	// Passed post category list overwrites existing category list if not empty.
	if ( isset($postarr['post_category']) && is_array($postarr['post_category'])
			 && 0 != count($postarr['post_category']) )
		$post_cats = $postarr['post_category'];
	else
		$post_cats = $post['post_category'];

	// Drafts shouldn't be assigned a date unless explicitly done so by the user
	if ( in_array($post['post_status'], array('draft', 'pending')) && empty($postarr['edit_date']) &&
			 ('0000-00-00 00:00:00' == $post['post_date_gmt']) )
		$clear_date = true;
	else
		$clear_date = false;

	// Merge old and new fields with new fields overwriting old ones.
	$postarr = array_merge($post, $postarr);
	$postarr['post_category'] = $post_cats;
	if ( $clear_date ) {
		$postarr['post_date'] = current_time('mysql');
		$postarr['post_date_gmt'] = '';
	}

	if ($postarr['post_type'] == 'attachment')
		return wp_insert_attachment($postarr);

	return wp_insert_post($postarr);
}

/**
 * Publish a post by transitioning the post status.
 *
 * @since 2.1.0
 * @uses $wpdb
 * @uses do_action() Calls 'edit_post', 'save_post', and 'wp_insert_post' on post_id and post data.
 *
 * @param int $post_id Post ID.
 * @return null
 */
function wp_publish_post($post_id) {
	global $wpdb;

	$post = get_post($post_id);

	if ( empty($post) )
		return;

	if ( 'publish' == $post->post_status )
		return;

	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post_id ) );

	$old_status = $post->post_status;
	$post->post_status = 'publish';
	wp_transition_post_status('publish', $old_status, $post);

	// Update counts for the post's terms.
	foreach ( (array) get_object_taxonomies('post') as $taxonomy ) {
		$tt_ids = wp_get_object_terms($post_id, $taxonomy, 'fields=tt_ids');
		wp_update_term_count($tt_ids, $taxonomy);
	}

	do_action('edit_post', $post_id, $post);
	do_action('save_post', $post_id, $post);
	do_action('wp_insert_post', $post_id, $post);
}

/**
 * Publish future post and make sure post ID has future post status.
 *
 * Invoked by cron 'publish_future_post' event. This safeguard prevents cron
 * from publishing drafts, etc.
 *
 * @since 2.5.0
 *
 * @param int $post_id Post ID.
 * @return null Nothing is returned. Which can mean that no action is required or post was published.
 */
function check_and_publish_future_post($post_id) {

	$post = get_post($post_id);

	if ( empty($post) )
		return;

	if ( 'future' != $post->post_status )
		return;

	$time = strtotime( $post->post_date_gmt . ' GMT' );

	if ( $time > time() ) { // Uh oh, someone jumped the gun!
		wp_clear_scheduled_hook( 'publish_future_post', $post_id ); // clear anything else in the system
		wp_schedule_single_event( $time, 'publish_future_post', array( $post_id ) );
		return;
	}

	return wp_publish_post($post_id);
}

/**
 * Adds tags to a post.
 *
 * @uses wp_set_post_tags() Same first two parameters, but the last parameter is always set to true.
 *
 * @package WordPress
 * @subpackage Post
 * @since 2.3.0
 *
 * @param int $post_id Post ID
 * @param string $tags The tags to set for the post, separated by commas.
 * @return bool|null Will return false if $post_id is not an integer or is 0. Will return null otherwise
 */
function wp_add_post_tags($post_id = 0, $tags = '') {
	return wp_set_post_tags($post_id, $tags, true);
}


/**
 * Set the tags for a post.
 *
 * @since 2.3.0
 * @uses wp_set_object_terms() Sets the tags for the post.
 *
 * @param int $post_id Post ID.
 * @param string $tags The tags to set for the post, separated by commas.
 * @param bool $append If true, don't delete existing tags, just add on. If false, replace the tags with the new tags.
 * @return bool|null Will return false if $post_id is not an integer or is 0. Will return null otherwise
 */
function wp_set_post_tags( $post_id = 0, $tags = '', $append = false ) {

	$post_id = (int) $post_id;

	if ( !$post_id )
		return false;

	if ( empty($tags) )
		$tags = array();
	$tags = (is_array($tags)) ? $tags : explode( ',', trim($tags, " \n\t\r\0\x0B,") );
	wp_set_object_terms($post_id, $tags, 'post_tag', $append);
}

/**
 * Set categories for a post.
 *
 * If the post categories parameter is not set, then the default category is
 * going used.
 *
 * @since 2.1.0
 *
 * @param int $post_ID Post ID.
 * @param array $post_categories Optional. List of categories.
 * @return bool|mixed
 */
function wp_set_post_categories($post_ID = 0, $post_categories = array()) {
	$post_ID = (int) $post_ID;
	// If $post_categories isn't already an array, make it one:
	if (!is_array($post_categories) || 0 == count($post_categories) || empty($post_categories))
		$post_categories = array(get_option('default_category'));
	else if ( 1 == count($post_categories) && '' == $post_categories[0] )
		return true;

	$post_categories = array_map('intval', $post_categories);
	$post_categories = array_unique($post_categories);

	return wp_set_object_terms($post_ID, $post_categories, 'category');
}

/**
 * Transition the post status of a post.
 *
 * Calls hooks to transition post status. If the new post status is not the same
 * as the previous post status, then two hooks will be ran, the first is
 * 'transition_post_status' with new status, old status, and post data. The
 * next action called is 'OLDSTATUS_to_NEWSTATUS' the NEWSTATUS is the
 * $new_status parameter and the OLDSTATUS is $old_status parameter; it has the
 * post data.
 *
 * The final action will run whether or not the post statuses are the same. The
 * action is named 'NEWSTATUS_POSTTYPE', NEWSTATUS is from the $new_status
 * parameter and POSTTYPE is post_type post data.
 *
 * @since 2.3.0
 *
 * @param string $new_status Transition to this post status.
 * @param string $old_status Previous post status.
 * @param object $post Post data.
 */
function wp_transition_post_status($new_status, $old_status, $post) {
	if ( $new_status != $old_status ) {
		do_action('transition_post_status', $new_status, $old_status, $post);
		do_action("${old_status}_to_$new_status", $post);
	}
	do_action("${new_status}_$post->post_type", $post->ID, $post);
}

//
// Trackback and ping functions
//

/**
 * Add a URL to those already pung.
 *
 * @since 1.5.0
 * @uses $wpdb
 *
 * @param int $post_id Post ID.
 * @param string $uri Ping URI.
 * @return int How many rows were updated.
 */
function add_ping($post_id, $uri) {
	global $wpdb;
	$pung = $wpdb->get_var( $wpdb->prepare( "SELECT pinged FROM $wpdb->posts WHERE ID = %d", $post_id ));
	$pung = trim($pung);
	$pung = preg_split('/\s/', $pung);
	$pung[] = $uri;
	$new = implode("\n", $pung);
	$new = apply_filters('add_ping', $new);
	// expected_slashed ($new)
	$new = stripslashes($new);
	return $wpdb->update( $wpdb->posts, array( 'pinged' => $new ), array( 'ID' => $post_id ) );
}

/**
 * Retrieve enclosures already enclosed for a post.
 *
 * @since 1.5.0
 * @uses $wpdb
 *
 * @param int $post_id Post ID.
 * @return array List of enclosures
 */
function get_enclosed($post_id) {
	$custom_fields = get_post_custom( $post_id );
	$pung = array();
	if ( !is_array( $custom_fields ) )
		return $pung;

	foreach ( $custom_fields as $key => $val ) {
		if ( 'enclosure' != $key || !is_array( $val ) )
			continue;
		foreach( $val as $enc ) {
			$enclosure = split( "\n", $enc );
			$pung[] = trim( $enclosure[ 0 ] );
		}
	}
	$pung = apply_filters('get_enclosed', $pung);
	return $pung;
}

/**
 * Retrieve URLs already pinged for a post.
 *
 * @since 1.5.0
 * @uses $wpdb
 *
 * @param int $post_id Post ID.
 * @return array
 */
function get_pung($post_id) {
	global $wpdb;
	$pung = $wpdb->get_var( $wpdb->prepare( "SELECT pinged FROM $wpdb->posts WHERE ID = %d", $post_id ));
	$pung = trim($pung);
	$pung = preg_split('/\s/', $pung);
	$pung = apply_filters('get_pung', $pung);
	return $pung;
}

/**
 * Retrieve URLs that need to be pinged.
 *
 * @since 1.5.0
 * @uses $wpdb
 *
 * @param int $post_id Post ID
 * @return array
 */
function get_to_ping($post_id) {
	global $wpdb;
	$to_ping = $wpdb->get_var( $wpdb->prepare( "SELECT to_ping FROM $wpdb->posts WHERE ID = %d", $post_id ));
	$to_ping = trim($to_ping);
	$to_ping = preg_split('/\s/', $to_ping, -1, PREG_SPLIT_NO_EMPTY);
	$to_ping = apply_filters('get_to_ping',  $to_ping);
	return $to_ping;
}

/**
 * Do trackbacks for a list of URLs.
 *
 * @since 1.0.0
 *
 * @param string $tb_list Comma separated list of URLs
 * @param int $post_id Post ID
 */
function trackback_url_list($tb_list, $post_id) {
	if ( ! empty( $tb_list ) ) {
		// get post data
		$postdata = wp_get_single_post($post_id, ARRAY_A);

		// import postdata as variables
		extract($postdata, EXTR_SKIP);

		// form an excerpt
		$excerpt = strip_tags($post_excerpt ? $post_excerpt : $post_content);

		if (strlen($excerpt) > 255) {
			$excerpt = substr($excerpt,0,252) . '...';
		}

		$trackback_urls = explode(',', $tb_list);
		foreach( (array) $trackback_urls as $tb_url) {
			$tb_url = trim($tb_url);
			trackback($tb_url, stripslashes($post_title), $excerpt, $post_id);
		}
	}
}

//
// Page functions
//

/**
 * Get a list of page IDs.
 *
 * @since 2.0.0
 * @uses $wpdb
 *
 * @return array List of page IDs.
 */
function get_all_page_ids() {
	global $wpdb;

	if ( ! $page_ids = wp_cache_get('all_page_ids', 'posts') ) {
		$page_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'page'");
		wp_cache_add('all_page_ids', $page_ids, 'posts');
	}

	return $page_ids;
}

/**
 * Retrieves page data given a page ID or page object.
 *
 * @since 1.5.1
 *
 * @param mixed $page Page object or page ID. Passed by reference.
 * @param string $output What to output. OBJECT, ARRAY_A, or ARRAY_N.
 * @param string $filter How the return value should be filtered.
 * @return mixed Page data.
 */
function &get_page(&$page, $output = OBJECT, $filter = 'raw') {
	if ( empty($page) ) {
		if ( isset( $GLOBALS['page'] ) && isset( $GLOBALS['page']->ID ) ) {
			return get_post($GLOBALS['page'], $output, $filter);
		} else {
			$page = null;
			return $page;
		}
	}

	$the_page = get_post($page, $output, $filter);
	return $the_page;
}

/**
 * Retrieves a page given its path.
 *
 * @since 2.1.0
 * @uses $wpdb
 *
 * @param string $page_path Page path
 * @param string $output Optional. Output type. OBJECT, ARRAY_N, or ARRAY_A.
 * @return mixed Null when complete.
 */
function get_page_by_path($page_path, $output = OBJECT) {
	global $wpdb;
	$page_path = rawurlencode(urldecode($page_path));
	$page_path = str_replace('%2F', '/', $page_path);
	$page_path = str_replace('%20', ' ', $page_path);
	$page_paths = '/' . trim($page_path, '/');
	$leaf_path  = sanitize_title(basename($page_paths));
	$page_paths = explode('/', $page_paths);
	$full_path = '';
	foreach( (array) $page_paths as $pathdir)
		$full_path .= ($pathdir!=''?'/':'') . sanitize_title($pathdir);

	$pages = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_name = %s AND (post_type = 'page' OR post_type = 'attachment')", $leaf_path ));

	if ( empty($pages) )
		return null;

	foreach ($pages as $page) {
		$path = '/' . $leaf_path;
		$curpage = $page;
		while ($curpage->post_parent != 0) {
			$curpage = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE ID = %d and post_type='page'", $curpage->post_parent ));
			$path = '/' . $curpage->post_name . $path;
		}

		if ( $path == $full_path )
			return get_page($page->ID, $output);
	}

	return null;
}

/**
 * Retrieve a page given its title.
 *
 * @since 2.1.0
 * @uses $wpdb
 *
 * @param string $page_title Page title
 * @param string $output Optional. Output type. OBJECT, ARRAY_N, or ARRAY_A.
 * @return mixed
 */
function get_page_by_title($page_title, $output = OBJECT) {
	global $wpdb;
	$page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='page'", $page_title ));
	if ( $page )
		return get_page($page, $output);

	return null;
}

/**
 * Retrieve child pages from list of pages matching page ID.
 *
 * Matches against the pages parameter against the page ID. Also matches all
 * children for the same to retrieve all children of a page. Does not make any
 * SQL queries to get the children.
 *
 * @since 1.5.1
 *
 * @param int $page_id Page ID.
 * @param array $pages List of pages' objects.
 * @return array
 */
function &get_page_children($page_id, $pages) {
	$page_list = array();
	foreach ( (array) $pages as $page ) {
		if ( $page->post_parent == $page_id ) {
			$page_list[] = $page;
			if ( $children = get_page_children($page->ID, $pages) )
				$page_list = array_merge($page_list, $children);
		}
	}
	return $page_list;
}

/**
 * Order the pages with children under parents in a flat list.
 *
 * Fetches the pages returned as a FLAT list, but arranged in order of their
 * hierarchy, i.e., child parents immediately follow their parents.
 *
 * @since 2.0.0
 *
 * @param array $posts Posts array.
 * @param int $parent Parent page ID.
 * @return array
 */
function get_page_hierarchy($posts, $parent = 0) {
	$result = array ( );
	if ($posts) { foreach ( (array) $posts as $post) {
		if ($post->post_parent == $parent) {
			$result[$post->ID] = $post->post_name;
			$children = get_page_hierarchy($posts, $post->ID);
			$result += $children; //append $children to $result
		}
	} }
	return $result;
}

/**
 * Builds URI for a page.
 *
 * Sub pages will be in the "directory" under the parent page post name.
 *
 * @since 1.5.0
 *
 * @param int $page_id Page ID.
 * @return string Page URI.
 */
function get_page_uri($page_id) {
	$page = get_page($page_id);
	$uri = $page->post_name;

	// A page cannot be it's own parent.
	if ( $page->post_parent == $page->ID )
		return $uri;

	while ($page->post_parent != 0) {
		$page = get_page($page->post_parent);
		$uri = $page->post_name . "/" . $uri;
	}

	return $uri;
}

/**
 * Retrieve a list of pages.
 *
 * The defaults that can be overridden are the following: 'child_of',
 * 'sort_order', 'sort_column', 'post_title', 'hierarchical', 'exclude',
 * 'include', 'meta_key', 'meta_value', and 'authors'.
 *
 * @since 1.5.0
 * @uses $wpdb
 *
 * @param mixed $args Optional. Array or string of options that overrides defaults.
 * @return array List of pages matching defaults or $args
 */
function &get_pages($args = '') {
	global $wpdb;

	$defaults = array(
		'child_of' => 0, 'sort_order' => 'ASC',
		'sort_column' => 'post_title', 'hierarchical' => 1,
		'exclude' => '', 'include' => '',
		'meta_key' => '', 'meta_value' => '',
		'authors' => '', 'parent' => -1, 'exclude_tree' => ''
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$key = md5( serialize( compact(array_keys($defaults)) ) );
	if ( $cache = wp_cache_get( 'get_pages', 'posts' ) ) {
		if ( isset( $cache[ $key ] ) ) {
			$pages = apply_filters('get_pages', $cache[ $key ], $r );
			return $pages;
		}
	}

	$inclusions = '';
	if ( !empty($include) ) {
		$child_of = 0; //ignore child_of, parent, exclude, meta_key, and meta_value params if using include
		$parent = -1;
		$exclude = '';
		$meta_key = '';
		$meta_value = '';
		$hierarchical = false;
		$incpages = preg_split('/[\s,]+/',$include);
		if ( count($incpages) ) {
			foreach ( $incpages as $incpage ) {
				if (empty($inclusions))
					$inclusions = $wpdb->prepare(' AND ( ID = %d ', $incpage);
				else
					$inclusions .= $wpdb->prepare(' OR ID = %d ', $incpage);
			}
		}
	}
	if (!empty($inclusions))
		$inclusions .= ')';

	$exclusions = '';
	if ( !empty($exclude) ) {
		$expages = preg_split('/[\s,]+/',$exclude);
		if ( count($expages) ) {
			foreach ( $expages as $expage ) {
				if (empty($exclusions))
					$exclusions = $wpdb->prepare(' AND ( ID <> %d ', $expage);
				else
					$exclusions .= $wpdb->prepare(' AND ID <> %d ', $expage);
			}
		}
	}
	if (!empty($exclusions))
		$exclusions .= ')';

	$author_query = '';
	if (!empty($authors)) {
		$post_authors = preg_split('/[\s,]+/',$authors);

		if ( count($post_authors) ) {
			foreach ( $post_authors as $post_author ) {
				//Do we have an author id or an author login?
				if ( 0 == intval($post_author) ) {
					$post_author = get_userdatabylogin($post_author);
					if ( empty($post_author) )
						continue;
					if ( empty($post_author->ID) )
						continue;
					$post_author = $post_author->ID;
				}

				if ( '' == $author_query )
					$author_query = $wpdb->prepare(' post_author = %d ', $post_author);
				else
					$author_query .= $wpdb->prepare(' OR post_author = %d ', $post_author);
			}
			if ( '' != $author_query )
				$author_query = " AND ($author_query)";
		}
	}

	$join = '';
	$where = "$exclusions $inclusions ";
	if ( ! empty( $meta_key ) || ! empty( $meta_value ) ) {
		$join = " LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )";

		// meta_key and meta_value might be slashed
		$meta_key = stripslashes($meta_key);
		$meta_value = stripslashes($meta_value);
		if ( ! empty( $meta_key ) )
			$where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_key = %s", $meta_key);
		if ( ! empty( $meta_value ) )
			$where .= $wpdb->prepare(" AND $wpdb->postmeta.meta_value = %s", $meta_value);

	}

	if ( $parent >= 0 )
		$where .= $wpdb->prepare(' AND post_parent = %d ', $parent);

	$query = "SELECT * FROM $wpdb->posts $join WHERE (post_type = 'page' AND post_status = 'publish') $where ";
	$query .= $author_query;
	$query .= " ORDER BY " . $sort_column . " " . $sort_order ;

	$pages = $wpdb->get_results($query);

	if ( empty($pages) ) {
		$pages = apply_filters('get_pages', array(), $r);
		return $pages;
	}

	// Update cache.
	update_page_cache($pages);

	if ( $child_of || $hierarchical )
		$pages = & get_page_children($child_of, $pages);

	if ( !empty($exclude_tree) ) {
		$exclude = array();

		$exclude = (int) $exclude_tree;
		$children = get_page_children($exclude, $pages);
		$excludes = array();
		foreach ( $children as $child )
			$excludes[] = $child->ID;
		$excludes[] = $exclude;
		$total = count($pages);
		for ( $i = 0; $i < $total; $i++ ) {
			if ( in_array($pages[$i]->ID, $excludes) )
				unset($pages[$i]);
		}
	}

	$cache[ $key ] = $pages;
	wp_cache_set( 'get_pages', $cache, 'posts' );

	$pages = apply_filters('get_pages', $pages, $r);

	return $pages;
}

//
// Attachment functions
//

/**
 * Check if the attachment URI is local one and is really an attachment.
 *
 * @since 2.0.0
 *
 * @param string $url URL to check
 * @return bool True on success, false on failure.
 */
function is_local_attachment($url) {
	if (strpos($url, get_bloginfo('url')) === false)
		return false;
	if (strpos($url, get_bloginfo('url') . '/?attachment_id=') !== false)
		return true;
	if ( $id = url_to_postid($url) ) {
		$post = & get_post($id);
		if ( 'attachment' == $post->post_type )
			return true;
	}
	return false;
}

/**
 * Insert an attachment.
 *
 * If you set the 'ID' in the $object parameter, it will mean that you are
 * updating and attempt to update the attachment. You can also set the
 * attachment name or title by setting the key 'post_name' or 'post_title'.
 *
 * You can set the dates for the attachment manually by setting the 'post_date'
 * and 'post_date_gmt' keys' values.
 *
 * By default, the comments will use the default settings for whether the
 * comments are allowed. You can close them manually or keep them open by
 * setting the value for the 'comment_status' key.
 *
 * The $object parameter can have the following:
 *     'post_status' - Default is 'draft'. Can not be override, set the same as
 *         parent post.
 *     'post_type' - Default is 'post', will be set to attachment. Can not
 *         override.
 *     'post_author' - Default is current user ID. The ID of the user, who added
 *         the attachment.
 *     'ping_status' - Default is the value in default ping status option.
 *         Whether the attachment can accept pings.
 *     'post_parent' - Default is 0. Can use $parent parameter or set this for
 *         the post it belongs to, if any.
 *     'menu_order' - Default is 0. The order it is displayed.
 *     'to_ping' - Whether to ping.
 *     'pinged' - Default is empty string.
 *     'post_password' - Default is empty string. The password to access the
 *         attachment.
 *     'guid' - Global Unique ID for referencing the attachment.
 *     'post_content_filtered' - Attachment post content filtered.
 *     'post_excerpt' - Attachment excerpt.
 *
 * @since 2.0.0
 * @uses $wpdb
 * @uses $user_ID
 *
 * @param string|array $object Arguments to override defaults.
 * @param string $file Optional filename.
 * @param int $post_parent Parent post ID.
 * @return int Attachment ID.
 */
function wp_insert_attachment($object, $file = false, $parent = 0) {
	global $wpdb, $user_ID;

	$defaults = array('post_status' => 'draft', 'post_type' => 'post', 'post_author' => $user_ID,
		'ping_status' => get_option('default_ping_status'), 'post_parent' => 0,
		'menu_order' => 0, 'to_ping' =>  '', 'pinged' => '', 'post_password' => '',
		'guid' => '', 'post_content_filtered' => '', 'post_excerpt' => '', 'import_id' => 0);

	$object = wp_parse_args($object, $defaults);
	if ( !empty($parent) )
		$object['post_parent'] = $parent;

	$object = sanitize_post($object, 'db');

	// export array as variables
	extract($object, EXTR_SKIP);

	// Make sure we set a valid category
	if ( !isset($post_category) || 0 == count($post_category) || !is_array($post_category)) {
		$post_category = array(get_option('default_category'));
	}

	if ( empty($post_author) )
		$post_author = $user_ID;

	$post_type = 'attachment';
	$post_status = 'inherit';

	// Are we updating or creating?
	if ( !empty($ID) ) {
		$update = true;
		$post_ID = (int) $ID;
	} else {
		$update = false;
		$post_ID = 0;
	}

	// Create a valid post name.
	if ( empty($post_name) )
		$post_name = sanitize_title($post_title);
	else
		$post_name = sanitize_title($post_name);

	// expected_slashed ($post_name)
	$post_name_check = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_status = 'inherit' AND ID != %d LIMIT 1", $post_name, $post_ID));

	if ($post_name_check) {
		$suffix = 2;
		while ($post_name_check) {
			$alt_post_name = $post_name . "-$suffix";
			// expected_slashed ($alt_post_name, $post_name)
			$post_name_check = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_status = 'inherit' AND ID != %d AND post_parent = %d LIMIT 1", $alt_post_name, $post_ID, $post_parent));
			$suffix++;
		}
		$post_name = $alt_post_name;
	}

	if ( empty($post_date) )
		$post_date = current_time('mysql');
	if ( empty($post_date_gmt) )
		$post_date_gmt = current_time('mysql', 1);

	if ( empty($post_modified) )
		$post_modified = $post_date;
	if ( empty($post_modified_gmt) )
		$post_modified_gmt = $post_date_gmt;

	if ( empty($comment_status) ) {
		if ( $update )
			$comment_status = 'closed';
		else
			$comment_status = get_option('default_comment_status');
	}
	if ( empty($ping_status) )
		$ping_status = get_option('default_ping_status');

	if ( isset($to_ping) )
		$to_ping = preg_replace('|\s+|', "\n", $to_ping);
	else
		$to_ping = '';

	if ( isset($post_parent) )
		$post_parent = (int) $post_parent;
	else
		$post_parent = 0;

	if ( isset($menu_order) )
		$menu_order = (int) $menu_order;
	else
		$menu_order = 0;

	if ( !isset($post_password) )
		$post_password = '';

	if ( ! isset($pinged) )
		$pinged = '';

	// expected_slashed (everything!)
	$data = compact( array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'post_mime_type', 'guid' ) );
	$data = stripslashes_deep( $data );

	if ( $update ) {
		$wpdb->update( $wpdb->posts, $data, array( 'ID' => $post_ID ) );
	} else {
		// If there is a suggested ID, use it if not already present
		if ( !empty($import_id) ) {
			$import_id = (int) $import_id;
			if ( ! $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID = %d", $import_id) ) ) {
				$data['ID'] = $import_id;
			}
		}

		$wpdb->insert( $wpdb->posts, $data );
		$post_ID = (int) $wpdb->insert_id;
	}

	if ( empty($post_name) ) {
		$post_name = sanitize_title($post_title, $post_ID);
		$wpdb->update( $wpdb->posts, compact("post_name"), array( 'ID' => $post_ID ) );
	}

	wp_set_post_categories($post_ID, $post_category);

	if ( $file )
		update_attached_file( $post_ID, $file );

	clean_post_cache($post_ID);

	if ( $update) {
		do_action('edit_attachment', $post_ID);
	} else {
		do_action('add_attachment', $post_ID);
	}

	return $post_ID;
}

/**
 * Delete an attachment.
 *
 * Will remove the file also, when the attachment is removed. Removes all post
 * meta fields, taxonomy, comments, etc associated with the attachment (except
 * the main post).
 *
 * @since 2.0.0
 * @uses $wpdb
 * @uses do_action() Calls 'delete_attachment' hook on Attachment ID.
 *
 * @param int $postid Attachment ID.
 * @return mixed False on failure. Post data on success.
 */
function wp_delete_attachment($postid) {
	global $wpdb;

	if ( !$post = $wpdb->get_row(  $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $postid)) )
		return $post;

	if ( 'attachment' != $post->post_type )
		return false;

	$meta = wp_get_attachment_metadata( $postid );
	$file = get_attached_file( $postid );

	/** @todo Delete for pluggable post taxonomies too */
	wp_delete_object_term_relationships($postid, array('category', 'post_tag'));

	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->posts WHERE ID = %d", $postid ));

	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->comments WHERE comment_post_ID = %d", $postid ));

	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE post_id = %d ", $postid ));

	$uploadPath = wp_upload_dir();

	if ( ! empty($meta['thumb']) ) {
		// Don't delete the thumb if another attachment uses it
		if (! $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND post_id <> %d", '%'.$meta['thumb'].'%', $postid)) ) {
			$thumbfile = str_replace(basename($file), $meta['thumb'], $file);
			$thumbfile = apply_filters('wp_delete_file', $thumbfile);
			@ unlink( path_join($uploadPath['basedir'], $thumbfile) );
		}
	}

	// remove intermediate images if there are any
	$sizes = apply_filters('intermediate_image_sizes', array('thumbnail', 'medium', 'large'));
	foreach ( $sizes as $size ) {
		if ( $intermediate = image_get_intermediate_size($postid, $size) ) {
			$intermediate_file = apply_filters('wp_delete_file', $intermediate['path']);
			@ unlink( path_join($uploadPath['basedir'], $intermediate_file) );
		}
	}

	$file = apply_filters('wp_delete_file', $file);

	if ( ! empty($file) )
		@ unlink($file);

	clean_post_cache($postid);

	do_action('delete_attachment', $postid);

	return $post;
}

/**
 * Retrieve attachment meta field for attachment ID.
 *
 * @since 2.1.0
 *
 * @param int $post_id Attachment ID
 * @param bool $unfiltered Optional, default is false. If true, filters are not run.
 * @return string|bool Attachment meta field. False on failure.
 */
function wp_get_attachment_metadata( $post_id, $unfiltered = false ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;

	$data = get_post_meta( $post->ID, '_wp_attachment_metadata', true );
	if ( $unfiltered )
		return $data;
	return apply_filters( 'wp_get_attachment_metadata', $data, $post->ID );
}

/**
 * Update metadata for an attachment.
 *
 * @since 2.1.0
 *
 * @param int $post_id Attachment ID.
 * @param array $data Attachment data.
 * @return int
 */
function wp_update_attachment_metadata( $post_id, $data ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;

	$data = apply_filters( 'wp_update_attachment_metadata', $data, $post->ID );

	return update_post_meta( $post->ID, '_wp_attachment_metadata', $data);
}

/**
 * Retrieve the URL for an attachment.
 *
 * @since 2.1.0
 *
 * @param int $post_id Attachment ID.
 * @return string
 */
function wp_get_attachment_url( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;

	$url = '';
	if ( $file = get_post_meta( $post->ID, '_wp_attached_file', true) ) { //Get attached file
		if ( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) { //Get upload directory
			if ( 0 === strpos($file, $uploads['basedir']) ) //Check that the upload base exists in the file location
				$url = str_replace($uploads['basedir'], $uploads['baseurl'], $file); //replace file location with url location
			elseif ( false !== strpos($file, 'wp-content/uploads') )
				$url = $uploads['baseurl'] . substr( $file, strpos($file, 'wp-content/uploads') + 18 );
			else
				$url = $uploads['baseurl'] . "/$file"; //Its a newly uploaded file, therefor $file is relative to the basedir.
		}
	}

	if ( empty($url) ) //If any of the above options failed, Fallback on the GUID as used pre-2.7, not recomended to rely upon this.
		$url = get_the_guid( $post->ID );

	if ( 'attachment' != $post->post_type || empty($url) )
		return false;

	return apply_filters( 'wp_get_attachment_url', $url, $post->ID );
}

/**
 * Retrieve thumbnail for an attachment.
 *
 * @since 2.1.0
 *
 * @param int $post_id Attachment ID.
 * @return mixed False on failure. Thumbnail file path on success.
 */
function wp_get_attachment_thumb_file( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;
	if ( !is_array( $imagedata = wp_get_attachment_metadata( $post->ID ) ) )
		return false;

	$file = get_attached_file( $post->ID );

	if ( !empty($imagedata['thumb']) && ($thumbfile = str_replace(basename($file), $imagedata['thumb'], $file)) && file_exists($thumbfile) )
		return apply_filters( 'wp_get_attachment_thumb_file', $thumbfile, $post->ID );
	return false;
}

/**
 * Retrieve URL for an attachment thumbnail.
 *
 * @since 2.1.0
 *
 * @param int $post_id Attachment ID
 * @return string|bool False on failure. Thumbnail URL on success.
 */
function wp_get_attachment_thumb_url( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;
	if ( !$url = wp_get_attachment_url( $post->ID ) )
		return false;

	$sized = image_downsize( $post_id, 'thumbnail' );
	if ( $sized )
		return $sized[0];

	if ( !$thumb = wp_get_attachment_thumb_file( $post->ID ) )
		return false;

	$url = str_replace(basename($url), basename($thumb), $url);

	return apply_filters( 'wp_get_attachment_thumb_url', $url, $post->ID );
}

/**
 * Check if the attachment is an image.
 *
 * @since 2.1.0
 *
 * @param int $post_id Attachment ID
 * @return bool
 */
function wp_attachment_is_image( $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;

	if ( !$file = get_attached_file( $post->ID ) )
		return false;

	$ext = preg_match('/\.([^.]+)$/', $file, $matches) ? strtolower($matches[1]) : false;

	$image_exts = array('jpg', 'jpeg', 'gif', 'png');

	if ( 'image/' == substr($post->post_mime_type, 0, 6) || $ext && 'import' == $post->post_mime_type && in_array($ext, $image_exts) )
		return true;
	return false;
}

/**
 * Retrieve the icon for a MIME type.
 *
 * @since 2.1.0
 *
 * @param string $mime MIME type
 * @return string|bool
 */
function wp_mime_type_icon( $mime = 0 ) {
	if ( !is_numeric($mime) )
		$icon = wp_cache_get("mime_type_icon_$mime");
	if ( empty($icon) ) {
		$post_id = 0;
		$post_mimes = array();
		if ( is_numeric($mime) ) {
			$mime = (int) $mime;
			if ( $post =& get_post( $mime ) ) {
				$post_id = (int) $post->ID;
				$ext = preg_replace('/^.+?\.([^.]+)$/', '$1', $post->guid);
				if ( !empty($ext) ) {
					$post_mimes[] = $ext;
					if ( $ext_type = wp_ext2type( $ext ) )
						$post_mimes[] = $ext_type;
				}
				$mime = $post->post_mime_type;
			} else {
				$mime = 0;
			}
		} else {
			$post_mimes[] = $mime;
		}

		$icon_files = wp_cache_get('icon_files');

		if ( !is_array($icon_files) ) {
			$icon_dir = apply_filters( 'icon_dir', ABSPATH . WPINC . '/images/crystal' );
			$icon_dir_uri = apply_filters( 'icon_dir_uri', includes_url('images/crystal') );
			$dirs = apply_filters( 'icon_dirs', array($icon_dir => $icon_dir_uri) );
			$icon_files = array();
			while ( $dirs ) {
				$dir = array_shift($keys = array_keys($dirs));
				$uri = array_shift($dirs);
				if ( $dh = opendir($dir) ) {
					while ( false !== $file = readdir($dh) ) {
						$file = basename($file);
						if ( substr($file, 0, 1) == '.' )
							continue;
						if ( !in_array(strtolower(substr($file, -4)), array('.png', '.gif', '.jpg') ) ) {
							if ( is_dir("$dir/$file") )
								$dirs["$dir/$file"] = "$uri/$file";
							continue;
						}
						$icon_files["$dir/$file"] = "$uri/$file";
					}
					closedir($dh);
				}
			}
			wp_cache_set('icon_files', $icon_files, 600);
		}

		// Icon basename - extension = MIME wildcard
		foreach ( $icon_files as $file => $uri )
			$types[ preg_replace('/^([^.]*).*$/', '$1', basename($file)) ] =& $icon_files[$file];

		if ( ! empty($mime) ) {
			$post_mimes[] = substr($mime, 0, strpos($mime, '/'));
			$post_mimes[] = substr($mime, strpos($mime, '/') + 1);
			$post_mimes[] = str_replace('/', '_', $mime);
		}

		$matches = wp_match_mime_types(array_keys($types), $post_mimes);
		$matches['default'] = array('default');

		foreach ( $matches as $match => $wilds ) {
			if ( isset($types[$wilds[0]])) {
				$icon = $types[$wilds[0]];
				if ( !is_numeric($mime) )
					wp_cache_set("mime_type_icon_$mime", $icon);
				break;
			}
		}
	}

	return apply_filters( 'wp_mime_type_icon', $icon, $mime, $post_id ); // Last arg is 0 if function pass mime type.
}

/**
 * Checked for changed slugs for published posts and save old slug.
 *
 * The function is used along with form POST data. It checks for the wp-old-slug
 * POST field. Will only be concerned with published posts and the slug actually
 * changing.
 *
 * If the slug was changed and not already part of the old slugs then it will be
 * added to the post meta field ('_wp_old_slug') for storing old slugs for that
 * post.
 *
 * The most logically usage of this function is redirecting changed posts, so
 * that those that linked to an changed post will be redirected to the new post.
 *
 * @since 2.1.0
 *
 * @param int $post_id Post ID.
 * @return int Same as $post_id
 */
function wp_check_for_changed_slugs($post_id) {
	if ( !isset($_POST['wp-old-slug']) || !strlen($_POST['wp-old-slug']) )
		return $post_id;

	$post = &get_post($post_id);

	// we're only concerned with published posts
	if ( $post->post_status != 'publish' || $post->post_type != 'post' )
		return $post_id;

	// only bother if the slug has changed
	if ( $post->post_name == $_POST['wp-old-slug'] )
		return $post_id;

	$old_slugs = (array) get_post_meta($post_id, '_wp_old_slug');

	// if we haven't added this old slug before, add it now
	if ( !count($old_slugs) || !in_array($_POST['wp-old-slug'], $old_slugs) )
		add_post_meta($post_id, '_wp_old_slug', $_POST['wp-old-slug']);

	// if the new slug was used previously, delete it from the list
	if ( in_array($post->post_name, $old_slugs) )
		delete_post_meta($post_id, '_wp_old_slug', $post->post_name);

	return $post_id;
}

/**
 * Retrieve the private post SQL based on capability.
 *
 * This function provides a standardized way to appropriately select on the
 * post_status of posts/pages. The function will return a piece of SQL code that
 * can be added to a WHERE clause; this SQL is constructed to allow all
 * published posts, and all private posts to which the user has access.
 *
 * It also allows plugins that define their own post type to control the cap by
 * using the hook 'pub_priv_sql_capability'. The plugin is expected to return
 * the capability the user must have to read the private post type.
 *
 * @since 2.2.0
 *
 * @uses $user_ID
 * @uses apply_filters() Call 'pub_priv_sql_capability' filter for plugins with different post types.
 *
 * @param string $post_type currently only supports 'post' or 'page'.
 * @return string SQL code that can be added to a where clause.
 */
function get_private_posts_cap_sql($post_type) {
	global $user_ID;
	$cap = '';

	// Private posts
	if ($post_type == 'post') {
		$cap = 'read_private_posts';
	// Private pages
	} elseif ($post_type == 'page') {
		$cap = 'read_private_pages';
	// Dunno what it is, maybe plugins have their own post type?
	} else {
		$cap = apply_filters('pub_priv_sql_capability', $cap);

		if (empty($cap)) {
			// We don't know what it is, filters don't change anything,
			// so set the SQL up to return nothing.
			return '1 = 0';
		}
	}

	$sql = '(post_status = \'publish\'';

	if (current_user_can($cap)) {
		// Does the user have the capability to view private posts? Guess so.
		$sql .= ' OR post_status = \'private\'';
	} elseif (is_user_logged_in()) {
		// Users can view their own private posts.
		$sql .= ' OR post_status = \'private\' AND post_author = \'' . $user_ID . '\'';
	}

	$sql .= ')';

	return $sql;
}

/**
 * Retrieve the date the the last post was published.
 *
 * The server timezone is the default and is the difference between GMT and
 * server time. The 'blog' value is the date when the last post was posted. The
 * 'gmt' is when the last post was posted in GMT formatted date.
 *
 * @since 0.71
 *
 * @uses $wpdb
 * @uses $blog_id
 * @uses apply_filters() Calls 'get_lastpostdate' filter
 *
 * @global mixed $cache_lastpostdate Stores the last post date
 * @global mixed $pagenow The current page being viewed
 *
 * @param string $timezone The location to get the time. Can be 'gmt', 'blog', or 'server'.
 * @return string The date of the last post.
 */
function get_lastpostdate($timezone = 'server') {
	global $cache_lastpostdate, $wpdb, $blog_id;
	$add_seconds_server = date('Z');
	if ( !isset($cache_lastpostdate[$blog_id][$timezone]) ) {
		switch(strtolower($timezone)) {
			case 'gmt':
				$lastpostdate = $wpdb->get_var("SELECT post_date_gmt FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date_gmt DESC LIMIT 1");
				break;
			case 'blog':
				$lastpostdate = $wpdb->get_var("SELECT post_date FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date_gmt DESC LIMIT 1");
				break;
			case 'server':
				$lastpostdate = $wpdb->get_var("SELECT DATE_ADD(post_date_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date_gmt DESC LIMIT 1");
				break;
		}
		$cache_lastpostdate[$blog_id][$timezone] = $lastpostdate;
	} else {
		$lastpostdate = $cache_lastpostdate[$blog_id][$timezone];
	}
	return apply_filters( 'get_lastpostdate', $lastpostdate, $timezone );
}

/**
 * Retrieve last post modified date depending on timezone.
 *
 * The server timezone is the default and is the difference between GMT and
 * server time. The 'blog' value is just when the last post was modified. The
 * 'gmt' is when the last post was modified in GMT time.
 *
 * @since 1.2.0
 * @uses $wpdb
 * @uses $blog_id
 * @uses apply_filters() Calls 'get_lastpostmodified' filter
 *
 * @global mixed $cache_lastpostmodified Stores the date the last post was modified
 *
 * @param string $timezone The location to get the time. Can be 'gmt', 'blog', or 'server'.
 * @return string The date the post was last modified.
 */
function get_lastpostmodified($timezone = 'server') {
	global $cache_lastpostmodified, $wpdb, $blog_id;
	$add_seconds_server = date('Z');
	if ( !isset($cache_lastpostmodified[$blog_id][$timezone]) ) {
		switch(strtolower($timezone)) {
			case 'gmt':
				$lastpostmodified = $wpdb->get_var("SELECT post_modified_gmt FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_modified_gmt DESC LIMIT 1");
				break;
			case 'blog':
				$lastpostmodified = $wpdb->get_var("SELECT post_modified FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_modified_gmt DESC LIMIT 1");
				break;
			case 'server':
				$lastpostmodified = $wpdb->get_var("SELECT DATE_ADD(post_modified_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_modified_gmt DESC LIMIT 1");
				break;
		}
		$lastpostdate = get_lastpostdate($timezone);
		if ( $lastpostdate > $lastpostmodified ) {
			$lastpostmodified = $lastpostdate;
		}
		$cache_lastpostmodified[$blog_id][$timezone] = $lastpostmodified;
	} else {
		$lastpostmodified = $cache_lastpostmodified[$blog_id][$timezone];
	}
	return apply_filters( 'get_lastpostmodified', $lastpostmodified, $timezone );
}

/**
 * Updates posts in cache.
 *
 * @usedby update_page_cache() Aliased by this function.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 1.5.1
 *
 * @param array $posts Array of post objects
 */
function update_post_cache(&$posts) {
	if ( !$posts )
		return;

	foreach ( $posts as $post )
		wp_cache_add($post->ID, $post, 'posts');
}

/**
 * Will clean the post in the cache.
 *
 * Cleaning means delete from the cache of the post. Will call to clean the term
 * object cache associated with the post ID.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 2.0.0
 *
 * @uses do_action() Will call the 'clean_post_cache' hook action.
 *
 * @param int $id The Post ID in the cache to clean
 */
function clean_post_cache($id) {
	global $_wp_suspend_cache_invalidation, $wpdb;

	if ( !empty($_wp_suspend_cache_invalidation) )
		return;

	$id = (int) $id;

	wp_cache_delete($id, 'posts');
	wp_cache_delete($id, 'post_meta');

	clean_object_term_cache($id, 'post');

	wp_cache_delete( 'wp_get_archives', 'general' );

	do_action('clean_post_cache', $id);

	if ( $children = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $id) ) ) {
		foreach( $children as $cid )
			clean_post_cache( $cid );
	}
}

/**
 * Alias of update_post_cache().
 *
 * @see update_post_cache() Posts and pages are the same, alias is intentional
 *
 * @package WordPress
 * @subpackage Cache
 * @since 1.5.1
 *
 * @param array $pages list of page objects
 */
function update_page_cache(&$pages) {
	update_post_cache($pages);
}

/**
 * Will clean the page in the cache.
 *
 * Clean (read: delete) page from cache that matches $id. Will also clean cache
 * associated with 'all_page_ids' and 'get_pages'.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 2.0.0
 *
 * @uses do_action() Will call the 'clean_page_cache' hook action.
 *
 * @param int $id Page ID to clean
 */
function clean_page_cache($id) {
	clean_post_cache($id);

	wp_cache_delete( 'all_page_ids', 'posts' );
	wp_cache_delete( 'get_pages', 'posts' );

	do_action('clean_page_cache', $id);
}

/**
 * Call major cache updating functions for list of Post objects.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 1.5.0
 *
 * @uses $wpdb
 * @uses update_post_cache()
 * @uses update_object_term_cache()
 * @uses update_postmeta_cache()
 *
 * @param array $posts Array of Post objects
 */
function update_post_caches(&$posts) {
	// No point in doing all this work if we didn't match any posts.
	if ( !$posts )
		return;

	update_post_cache($posts);

	$post_ids = array();

	for ($i = 0; $i < count($posts); $i++)
		$post_ids[] = $posts[$i]->ID;

	update_object_term_cache($post_ids, 'post');

	update_postmeta_cache($post_ids);
}

/**
 * Updates metadata cache for list of post IDs.
 *
 * Performs SQL query to retrieve the metadata for the post IDs and updates the
 * metadata cache for the posts. Therefore, the functions, which call this
 * function, do not need to perform SQL queries on their own.
 *
 * @package WordPress
 * @subpackage Cache
 * @since 2.1.0
 *
 * @uses $wpdb
 *
 * @param array $post_ids List of post IDs.
 * @return bool|array Returns false if there is nothing to update or an array of metadata.
 */
function update_postmeta_cache($post_ids) {
	global $wpdb;

	if ( empty( $post_ids ) )
		return false;

	if ( !is_array($post_ids) ) {
		$post_ids = preg_replace('|[^0-9,]|', '', $post_ids);
		$post_ids = explode(',', $post_ids);
	}

	$post_ids = array_map('intval', $post_ids);

	$ids = array();
	foreach ( (array) $post_ids as $id ) {
		if ( false === wp_cache_get($id, 'post_meta') )
			$ids[] = $id;
	}

	if ( empty( $ids ) )
		return false;

	// Get post-meta info
	$id_list = join(',', $ids);
	$cache = array();
	if ( $meta_list = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE post_id IN ($id_list)", ARRAY_A) ) {
		foreach ( (array) $meta_list as $metarow) {
			$mpid = (int) $metarow['post_id'];
			$mkey = $metarow['meta_key'];
			$mval = $metarow['meta_value'];

			// Force subkeys to be array type:
			if ( !isset($cache[$mpid]) || !is_array($cache[$mpid]) )
				$cache[$mpid] = array();
			if ( !isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]) )
				$cache[$mpid][$mkey] = array();

			// Add a value to the current pid/key:
			$cache[$mpid][$mkey][] = $mval;
		}
	}

	foreach ( (array) $ids as $id ) {
		if ( ! isset($cache[$id]) )
			$cache[$id] = array();
	}

	foreach ( (array) array_keys($cache) as $post)
		wp_cache_set($post, $cache[$post], 'post_meta');

	return $cache;
}

//
// Hooks
//

/**
 * Hook for managing future post transitions to published.
 *
 * @since 2.3.0
 * @access private
 * @uses $wpdb
 *
 * @param string $new_status New post status
 * @param string $old_status Previous post status
 * @param object $post Object type containing the post information
 */
function _transition_post_status($new_status, $old_status, $post) {
	global $wpdb;

	if ( $old_status != 'publish' && $new_status == 'publish' ) {
		// Reset GUID if transitioning to publish and it is empty
		if ( '' == get_the_guid($post->ID) )
			$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post->ID ) ), array( 'ID' => $post->ID ) );
		do_action('private_to_published', $post->ID);  // Deprecated, use private_to_publish
	}

	// Always clears the hook in case the post status bounced from future to draft.
	wp_clear_scheduled_hook('publish_future_post', $post->ID);
}

/**
 * Hook used to schedule publication for a post marked for the future.
 *
 * The $post properties used and must exist are 'ID' and 'post_date_gmt'.
 *
 * @since 2.3.0
 *
 * @param int $deprecated Not Used. Can be set to null.
 * @param object $post Object type containing the post information
 */
function _future_post_hook($deprecated = '', $post) {
	wp_clear_scheduled_hook( 'publish_future_post', $post->ID );
	wp_schedule_single_event(strtotime($post->post_date_gmt. ' GMT'), 'publish_future_post', array($post->ID));
}

/**
 * Hook to schedule pings and enclosures when a post is published.
 *
 * @since 2.3.0
 * @uses $wpdb
 * @uses XMLRPC_REQUEST
 * @uses APP_REQUEST
 * @uses do_action Calls 'xmlprc_publish_post' action if XMLRPC_REQUEST is defined. Calls 'app_publish_post'
 *	action if APP_REQUEST is defined.
 *
 * @param int $post_id The ID in the database table of the post being published
 */
function _publish_post_hook($post_id) {
	global $wpdb;

	if ( defined('XMLRPC_REQUEST') )
		do_action('xmlrpc_publish_post', $post_id);
	if ( defined('APP_REQUEST') )
		do_action('app_publish_post', $post_id);

	if ( defined('WP_IMPORTING') )
		return;

	$data = array( 'post_id' => $post_id, 'meta_value' => '1' );
	if ( get_option('default_pingback_flag') )
		$wpdb->insert( $wpdb->postmeta, $data + array( 'meta_key' => '_pingme' ) );
	$wpdb->insert( $wpdb->postmeta, $data + array( 'meta_key' => '_encloseme' ) );
	wp_schedule_single_event(time(), 'do_pings');
}

/**
 * Hook used to prevent page/post cache and rewrite rules from staying dirty.
 *
 * Does two things. If the post is a page and has a template then it will
 * update/add that template to the meta. For both pages and posts, it will clean
 * the post cache to make sure that the cache updates to the changes done
 * recently. For pages, the rewrite rules of WordPress are flushed to allow for
 * any changes.
 *
 * The $post parameter, only uses 'post_type' property and 'page_template'
 * property.
 *
 * @since 2.3.0
 * @uses $wp_rewrite Flushes Rewrite Rules.
 *
 * @param int $post_id The ID in the database table for the $post
 * @param object $post Object type containing the post information
 */
function _save_post_hook($post_id, $post) {
	if ( $post->post_type == 'page' ) {
		clean_page_cache($post_id);
		// Avoid flushing rules for every post during import.
		if ( !defined('WP_IMPORTING') ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	} else {
		clean_post_cache($post_id);
	}
}

/**
 * Retrieve post ancestors and append to post ancestors property.
 *
 * Will only retrieve ancestors once, if property is already set, then nothing
 * will be done. If there is not a parent post, or post ID and post parent ID
 * are the same then nothing will be done.
 *
 * The parameter is passed by reference, so nothing needs to be returned. The
 * property will be updated and can be referenced after the function is
 * complete. The post parent will be an ancestor and the parent of the post
 * parent will be an ancestor. There will only be two ancestors at the most.
 *
 * @access private
 * @since unknown
 * @uses $wpdb
 *
 * @param object $_post Post data.
 * @return null When nothing needs to be done.
 */
function _get_post_ancestors(&$_post) {
	global $wpdb;

	if ( isset($_post->ancestors) )
		return;

	$_post->ancestors = array();

	if ( empty($_post->post_parent) || $_post->ID == $_post->post_parent )
		return;

	$id = $_post->ancestors[] = $_post->post_parent;
	while ( $ancestor = $wpdb->get_var( $wpdb->prepare("SELECT `post_parent` FROM $wpdb->posts WHERE ID = %d LIMIT 1", $id) ) ) {
		if ( $id == $ancestor )
			break;
		$id = $_post->ancestors[] = $ancestor;
	}
}

/**
 * Determines which fields of posts are to be saved in revisions.
 *
 * Does two things. If passed a post *array*, it will return a post array ready
 * to be insterted into the posts table as a post revision. Otherwise, returns
 * an array whose keys are the post fields to be saved for post revisions.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 * @access private
 *
 * @param array $post Optional a post array to be processed for insertion as a post revision.
 * @param bool $autosave optional Is the revision an autosave?
 * @return array Post array ready to be inserted as a post revision or array of fields that can be versioned.
 */
function _wp_post_revision_fields( $post = null, $autosave = false ) {
	static $fields = false;

	if ( !$fields ) {
		// Allow these to be versioned
		$fields = array(
			'post_title' => __( 'Title' ),
			'post_content' => __( 'Content' ),
			'post_excerpt' => __( 'Excerpt' ),
		);

		// Runs only once
		$fields = apply_filters( '_wp_post_revision_fields', $fields );

		// WP uses these internally either in versioning or elsewhere - they cannot be versioned
		foreach ( array( 'ID', 'post_name', 'post_parent', 'post_date', 'post_date_gmt', 'post_status', 'post_type', 'comment_count', 'post_author' ) as $protect )
			unset( $fields[$protect] );
	}

	if ( !is_array($post) )
		return $fields;

	$return = array();
	foreach ( array_intersect( array_keys( $post ), array_keys( $fields ) ) as $field )
		$return[$field] = $post[$field];

	$return['post_parent']   = $post['ID'];
	$return['post_status']   = 'inherit';
	$return['post_type']     = 'revision';
	$return['post_name']     = $autosave ? "$post[ID]-autosave" : "$post[ID]-revision";
	$return['post_date']     = isset($post['post_modified']) ? $post['post_modified'] : '';
	$return['post_date_gmt'] = isset($post['post_modified_gmt']) ? $post['post_modified_gmt'] : '';

	return $return;
}

/**
 * Saves an already existing post as a post revision.
 *
 * Typically used immediately prior to post updates.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses _wp_put_post_revision()
 *
 * @param int $post_id The ID of the post to save as a revision.
 * @return mixed Null or 0 if error, new revision ID, if success.
 */
function wp_save_post_revision( $post_id ) {
	// We do autosaves manually with wp_create_post_autosave()
	if ( @constant( 'DOING_AUTOSAVE' ) )
		return;

	// WP_POST_REVISIONS = 0, false
	if ( !constant('WP_POST_REVISIONS') )
		return;

	if ( !$post = get_post( $post_id, ARRAY_A ) )
		return;

	if ( !in_array( $post['post_type'], array( 'post', 'page' ) ) )
		return;

	$return = _wp_put_post_revision( $post );

	// WP_POST_REVISIONS = true (default), -1
	if ( !is_numeric( WP_POST_REVISIONS ) || WP_POST_REVISIONS < 0 )
		return $return;

	// all revisions and (possibly) one autosave
	$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) );

	// WP_POST_REVISIONS = (int) (# of autasaves to save)
	$delete = count($revisions) - WP_POST_REVISIONS;

	if ( $delete < 1 )
		return $return;

	$revisions = array_slice( $revisions, 0, $delete );

	for ( $i = 0; isset($revisions[$i]); $i++ ) {
		if ( false !== strpos( $revisions[$i]->post_name, 'autosave' ) )
			continue;
		wp_delete_post_revision( $revisions[$i]->ID );
	}

	return $return;
}

/**
 * Retrieve the autosaved data of the specified post.
 *
 * Returns a post object containing the information that was autosaved for the
 * specified post.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @param int $post_id The post ID.
 * @return object|bool The autosaved data or false on failure or when no autosave exists.
 */
function wp_get_post_autosave( $post_id ) {

	if ( !$post = get_post( $post_id ) )
		return false;

	$q = array(
		'name' => "{$post->ID}-autosave",
		'post_parent' => $post->ID,
		'post_type' => 'revision',
		'post_status' => 'inherit'
	);

	// Use WP_Query so that the result gets cached
	$autosave_query = new WP_Query;

	add_action( 'parse_query', '_wp_get_post_autosave_hack' );
	$autosave = $autosave_query->query( $q );
	remove_action( 'parse_query', '_wp_get_post_autosave_hack' );

	if ( $autosave && is_array($autosave) && is_object($autosave[0]) )
		return $autosave[0];

	return false;
}

/**
 * Internally used to hack WP_Query into submission.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @param object $query WP_Query object
 */
function _wp_get_post_autosave_hack( $query ) {
	$query->is_single = false;
}

/**
 * Determines if the specified post is a revision.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @param int|object $post Post ID or post object.
 * @return bool|int False if not a revision, ID of revision's parent otherwise.
 */
function wp_is_post_revision( $post ) {
	if ( !$post = wp_get_post_revision( $post ) )
		return false;
	return (int) $post->post_parent;
}

/**
 * Determines if the specified post is an autosave.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @param int|object $post Post ID or post object.
 * @return bool|int False if not a revision, ID of autosave's parent otherwise
 */
function wp_is_post_autosave( $post ) {
	if ( !$post = wp_get_post_revision( $post ) )
		return false;
	if ( "{$post->post_parent}-autosave" !== $post->post_name )
		return false;
	return (int) $post->post_parent;
}

/**
 * Inserts post data into the posts table as a post revision.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses wp_insert_post()
 *
 * @param int|object|array $post Post ID, post object OR post array.
 * @param bool $autosave Optional. Is the revision an autosave?
 * @return mixed Null or 0 if error, new revision ID if success.
 */
function _wp_put_post_revision( $post = null, $autosave = false ) {
	if ( is_object($post) )
		$post = get_object_vars( $post );
	elseif ( !is_array($post) )
		$post = get_post($post, ARRAY_A);
	if ( !$post || empty($post['ID']) )
		return;

	if ( isset($post['post_type']) && 'revision' == $post['post_type'] )
		return new WP_Error( 'post_type', __( 'Cannot create a revision of a revision' ) );

	$post = _wp_post_revision_fields( $post, $autosave );
	$post = add_magic_quotes($post); //since data is from db
	
	$revision_id = wp_insert_post( $post );
	if ( is_wp_error($revision_id) )
		return $revision_id;

	if ( $revision_id )
		do_action( '_wp_put_post_revision', $revision_id );
	return $revision_id;
}

/**
 * Gets a post revision.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses get_post()
 *
 * @param int|object $post Post ID or post object
 * @param string $output Optional. OBJECT, ARRAY_A, or ARRAY_N.
 * @param string $filter Optional sanitation filter.  @see sanitize_post()
 * @return mixed Null if error or post object if success
 */
function &wp_get_post_revision(&$post, $output = OBJECT, $filter = 'raw') {
	$null = null;
	if ( !$revision = get_post( $post, OBJECT, $filter ) )
		return $revision;
	if ( 'revision' !== $revision->post_type )
		return $null;

	if ( $output == OBJECT ) {
		return $revision;
	} elseif ( $output == ARRAY_A ) {
		$_revision = get_object_vars($revision);
		return $_revision;
	} elseif ( $output == ARRAY_N ) {
		$_revision = array_values(get_object_vars($revision));
		return $_revision;
	}

	return $revision;
}

/**
 * Restores a post to the specified revision.
 *
 * Can restore a past using all fields of the post revision, or only selected
 * fields.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses wp_get_post_revision()
 * @uses wp_update_post()
 *
 * @param int|object $revision_id Revision ID or revision object.
 * @param array $fields Optional. What fields to restore from.  Defaults to all.
 * @return mixed Null if error, false if no fields to restore, (int) post ID if success.
 */
function wp_restore_post_revision( $revision_id, $fields = null ) {
	if ( !$revision = wp_get_post_revision( $revision_id, ARRAY_A ) )
		return $revision;

	if ( !is_array( $fields ) )
		$fields = array_keys( _wp_post_revision_fields() );

	$update = array();
	foreach( array_intersect( array_keys( $revision ), $fields ) as $field )
		$update[$field] = $revision[$field];

	if ( !$update )
		return false;

	$update['ID'] = $revision['post_parent'];
	
	$update = add_magic_quotes( $update ); //since data is from db

	$post_id = wp_update_post( $update );
	if ( is_wp_error( $post_id ) )
		return $post_id;

	if ( $post_id )
		do_action( 'wp_restore_post_revision', $post_id, $revision['ID'] );

	return $post_id;
}

/**
 * Deletes a revision.
 *
 * Deletes the row from the posts table corresponding to the specified revision.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses wp_get_post_revision()
 * @uses wp_delete_post()
 *
 * @param int|object $revision_id Revision ID or revision object.
 * @param array $fields Optional. What fields to restore from.  Defaults to all.
 * @return mixed Null if error, false if no fields to restore, (int) post ID if success.
 */
function wp_delete_post_revision( $revision_id ) {
	if ( !$revision = wp_get_post_revision( $revision_id ) )
		return $revision;

	$delete = wp_delete_post( $revision->ID );
	if ( is_wp_error( $delete ) )
		return $delete;

	if ( $delete )
		do_action( 'wp_delete_post_revision', $revision->ID, $revision );

	return $delete;
}

/**
 * Returns all revisions of specified post.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses get_children()
 *
 * @param int|object $post_id Post ID or post object
 * @return array empty if no revisions
 */
function wp_get_post_revisions( $post_id = 0, $args = null ) {
	if ( !constant('WP_POST_REVISIONS') )
		return array();
	if ( ( !$post = get_post( $post_id ) ) || empty( $post->ID ) )
		return array();

	$defaults = array( 'order' => 'DESC', 'orderby' => 'date' );
	$args = wp_parse_args( $args, $defaults );
	$args = array_merge( $args, array( 'post_parent' => $post->ID, 'post_type' => 'revision', 'post_status' => 'inherit' ) );

	if ( !$revisions = get_children( $args ) )
		return array();
	return $revisions;
}

function _set_preview($post) {

	if ( ! is_object($post) )
		return $post;

	$preview = wp_get_post_autosave($post->ID);

	if ( ! is_object($preview) )
		return $post;

	$preview = sanitize_post($preview);

	$post->post_content = $preview->post_content;
	$post->post_title = $preview->post_title;
	$post->post_excerpt = $preview->post_excerpt;

	return $post;
}

function _show_post_preview() {

	if ( isset($_GET['preview_id']) && isset($_GET['preview_nonce']) ) {
		$id = (int) $_GET['preview_id'];

		if ( false == wp_verify_nonce( $_GET['preview_nonce'], 'post_preview_' . $id ) )
			wp_die( __('You do not have permission to preview drafts.') );

		add_filter('the_preview', '_set_preview');
	}
}
