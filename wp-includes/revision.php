<?php
/**
 * Post revision functions.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 */

/**
 * Determines which fields of posts are to be saved in revisions.
 *
 * Does two things. If passed a post *array*, it will return a post array ready
 * to be inserted into the posts table as a post revision. Otherwise, returns
 * an array whose keys are the post fields to be saved for post revisions.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 * @access private
 * @uses apply_filters() Calls '_wp_post_revision_fields' on 'title', 'content' and 'excerpt' fields.
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
	$return['post_name']     = $autosave ? "$post[ID]-autosave-v1" : "$post[ID]-revision-v1"; // "1" is the revisioning system version
	$return['post_date']     = isset($post['post_modified']) ? $post['post_modified'] : '';
	$return['post_date_gmt'] = isset($post['post_modified_gmt']) ? $post['post_modified_gmt'] : '';
	$return['post_author']   = get_post_meta( $post['ID'], '_edit_last', true );

	return $return;
}

/**
 * Determines which post meta fields are revisioned.
 *
 * @since 3.6
 * @access private
 * @return array An array of meta keys that should be revisioned.
 */
function _wp_post_revision_meta_keys() {
	return array(
		'_wp_format_url',
		'_wp_format_quote',
		'_wp_format_quote_source',
		'_wp_format_image',
		'_wp_format_gallery',
		'_wp_format_audio',
		'_wp_format_video',
	);
}

/**
 * Saves an already existing post as a post revision.
 *
 * Typically used immediately after post updates.
 * Adds a copy of the current post as a revision, so latest revision always matches current post
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
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return;

	if ( ! $post = get_post( $post_id ) )
		return;

	if ( ! post_type_supports( $post->post_type, 'revisions' ) )
		return;

	if ( 'auto-draft' == $post->post_status )
		return;

	if ( ! wp_revisions_enabled( $post ) )
		return;

	// Compare the proposed update with the last stored revision verifying that
	// they are different, unless a plugin tells us to always save regardless.
	// If no previous revisions, save one
	if ( $revisions = wp_get_post_revisions( $post_id ) ) {
		// grab the last revision, but not an autosave
		foreach ( $revisions as $revision ) {
			if ( false !== strpos( $revision->post_name, "{$revision->post_parent}-revision" ) ) {
				$last_revision = $revision;
				break;
			}
		}

		if ( isset( $last_revision ) && apply_filters( 'wp_save_post_revision_check_for_changes', true, $last_revision, $post ) ) {
			$post_has_changed = false;

			foreach ( array_keys( _wp_post_revision_fields() ) as $field ) {
				if ( normalize_whitespace( $post->$field ) != normalize_whitespace( $last_revision->$field ) ) {
					$post_has_changed = true;
					break;
				}
			}

			// Check whether revisioned meta fields have changed.
			foreach ( _wp_post_revision_meta_keys() as $meta_key ) {
				if ( get_post_meta( $post->ID, $meta_key, true ) != get_post_meta( $last_revision->ID, $meta_key, true ) ) {
					$post_has_changed = true;
					break;
				}
			}

			// Check whether the post format has changed
			if ( get_post_format( $post->ID ) != get_post_meta( $last_revision->ID, '_revision_post_format', true ) )
				$post_has_changed = true;

			//don't save revision if post unchanged
			if( ! $post_has_changed )
				return;
		}
	}

	$return = _wp_put_post_revision( $post );

	$revisions_to_keep = wp_revisions_to_keep( $post );

	if ( $revisions_to_keep < 0 )
		return $return;

	// all revisions and autosaves
	$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) );

	$delete = count($revisions) - $revisions_to_keep;

	if ( $delete < 1 )
		return $return;

	$revisions = array_slice( $revisions, 0, $delete );

	for ( $i = 0; isset( $revisions[$i] ); $i++ ) {
		if ( false !== strpos( $revisions[ $i ]->post_name, 'autosave' ) )
			continue;

		wp_delete_post_revision( $revisions[ $i ]->ID );
	}

	return $return;
}

/**
 * Retrieve the autosaved data of the specified post.
 *
 * Returns a post object containing the information that was autosaved for the
 * specified post. If the optional $user_id is passed, returns the autosave for that user
 * otherwise returns the latest autosave.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 * @uses wp_get_post_revisions()
 *
 * @param int $post_id The post ID.
 * @param int $user_id optional The post author ID.
 * @return object|bool The autosaved data or false on failure or when no autosave exists.
 */
function wp_get_post_autosave( $post_id, $user_id = 0 ) {
	$revisions = wp_get_post_revisions($post_id);

	foreach ( $revisions as $revision ) {
		if ( false !== strpos( $revision->post_name, "{$post_id}-autosave" ) ) {
			if ( $user_id && $user_id != $revision->post_author )
				continue;

			return $revision;
			break;
		}
	}

	return false;
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

	if ( false !== strpos( $post->post_name, "{$post->post_parent}-autosave" ) )
		return (int) $post->post_parent;

	return false;
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

	$post_id = $post['ID'];
	$post = _wp_post_revision_fields( $post, $autosave );
	$post = wp_slash($post); //since data is from db

	$revision_id = wp_insert_post( $post );
	if ( is_wp_error($revision_id) )
		return $revision_id;

	if ( $revision_id )
		do_action( '_wp_put_post_revision', $revision_id );

	// Save revisioned meta fields.
	foreach ( _wp_post_revision_meta_keys() as $meta_key ) {
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		if ( empty( $meta_value ) )
			continue;

		// Use the underlying add_metadata vs add_post_meta to make sure
		// metadata is added to the revision post and not its parent.
		add_metadata( 'post', $revision_id, $meta_key, wp_slash( $meta_value ) );
	}

	// Save the post format
	if ( $post_format = get_post_format( $post_id ) )
		add_metadata( 'post', $revision_id, '_revision_post_format', $post_format );

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
 * @param string $filter Optional sanitation filter. @see sanitize_post()
 * @return mixed Null if error or post object if success
 */
function wp_get_post_revision(&$post, $output = OBJECT, $filter = 'raw') {
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
 * Can restore a past revision using all fields of the post revision, or only selected fields.
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 2.6.0
 *
 * @uses wp_get_post_revision()
 * @uses wp_update_post()
 * @uses do_action() Calls 'wp_restore_post_revision' on post ID and revision ID if wp_update_post()
 *  is successful.
 *
 * @param int|object $revision_id Revision ID or revision object.
 * @param array $fields Optional. What fields to restore from. Defaults to all.
 * @return mixed Null if error, false if no fields to restore, (int) post ID if success.
 */
function wp_restore_post_revision( $revision_id, $fields = null ) {
	if ( !$revision = wp_get_post_revision( $revision_id, ARRAY_A ) )
		return $revision;

	if ( !is_array( $fields ) )
		$fields = array_keys( _wp_post_revision_fields() );

	$update = array();
	foreach( array_intersect( array_keys( $revision ), $fields ) as $field ) {
		$update[$field] = $revision[$field];
	}

	if ( !$update )
		return false;

	$update['ID'] = $revision['post_parent'];

	$update = wp_slash( $update ); //since data is from db

	// Restore revisioned meta fields.
	foreach ( _wp_post_revision_meta_keys() as $meta_key ) {
		$meta_value = get_post_meta( $revision['ID'], $meta_key, true );
		if ( empty( $meta_value ) )
			$meta_value = '';
		// Add slashes to data pulled from the db
		update_post_meta( $update['ID'], $meta_key, wp_slash( $meta_value ) );
	}

	// Restore post format
	set_post_format( $update['ID'], get_post_meta( $revision['ID'], '_revision_post_format', true ) );

	$post_id = wp_update_post( $update );
	if ( is_wp_error( $post_id ) )
		return $post_id;

	if ( $post_id )
		do_action( 'wp_restore_post_revision', $post_id, $revision['ID'] );

	$restore_details = array(
		'restored_revision_id' => $revision_id,
		'restored_by_user' => get_current_user_id(),
		'restored_time' => time()
	);
	update_post_meta( $post_id, '_post_restored_from', $restore_details );

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
 * @return mixed Null or WP_Error if error, deleted post if success.
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
	$post = get_post( $post_id );
	if ( ! $post || empty( $post->ID ) || ! wp_revisions_enabled( $post ) )
		return array();

	$defaults = array( 'order' => 'DESC', 'orderby' => 'date' );
	$args = wp_parse_args( $args, $defaults );
	$args = array_merge( $args, array( 'post_parent' => $post->ID, 'post_type' => 'revision', 'post_status' => 'inherit' ) );

	if ( ! $revisions = get_children( $args ) )
		return array();

	return $revisions;
}

/**
 * Determine if revisions are enabled for a given post.
 *
 * @since 3.6.0
 *
 * @uses wp_revisions_to_keep()
 *
 * @param object $post
 * @return bool
 */
function wp_revisions_enabled( $post ) {
	return wp_revisions_to_keep( $post ) != 0;
}

/**
 * Determine how many revisions to retain for a given post.
 * By default, an infinite number of revisions are stored if a post type supports revisions.
 *
 * @since 3.6.0
 *
 * @uses post_type_supports()
 * @uses apply_filters() Calls 'wp_revisions_to_keep' hook on the number of revisions.
 *
 * @param object $post
 * @return int
 */
function wp_revisions_to_keep( $post ) {
	$num = WP_POST_REVISIONS;

	if ( true === $num )
		$num = -1;
	else
		$num = intval( $num );

	if ( ! post_type_supports( $post->post_type, 'revisions' ) )
		$num = 0;

	return (int) apply_filters( 'wp_revisions_to_keep', $num, $post );
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

	add_filter( 'get_post_metadata', '_wp_preview_meta_filter', 10, 4 );
	add_filter( 'get_the_terms', '_wp_preview_terms_filter', 10, 3 );

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

/**
 * Filters post meta retrieval to get values from the actual autosave post,
 * and not its parent. Filters revisioned meta keys only.
 *
 * @since 3.6
 * @access private
 */
function _wp_preview_meta_filter( $value, $object_id, $meta_key, $single ) {
	$post = get_post();

	if ( $post->ID != $object_id || ! in_array( $meta_key, _wp_post_revision_meta_keys() ) || 'revision' == $post->post_type )
		return $value;

	$preview = wp_get_post_autosave( $post->ID );
	if ( ! is_object( $preview ) )
		return $value;

	return get_post_meta( $preview->ID, $meta_key, $single );
}

/**
 * Filters terms lookup to get the post format saved with the preview revision.
 *
 * @since 2.6
 * @access private
 */
function _wp_preview_terms_filter( $terms, $post_id, $taxonomy ) {
	$post = get_post();

	if ( $post->ID != $post_id || 'post_format' != $taxonomy || 'revision' == $post->post_type )
		return $terms;

	if ( ! $preview = wp_get_post_autosave( $post->ID ) )
		return $terms;

	if ( $post_format = get_post_meta( $preview->ID, '_revision_post_format', true ) ) {
		if ( $term = get_term_by( 'slug', 'post-format-' . sanitize_key( $post_format ), 'post_format' ) )
			$terms = array( $term ); // Can only have one post format
	}

	return $terms;
}

function _wp_get_post_revision_version( $revision ) {
	if ( is_object( $revision ) )
		$revision = get_object_vars( $revision );
	elseif ( !is_array( $revision ) )
		return false;

	if ( preg_match( '/^\d+-(?:autosave|revision)-v(\d+)$/', $revision['post_name'], $matches ) )
		return (int) $matches[1];

	return 0;
}

/**
 * Upgrade the revisions author, add the current post as a revision and set the revisions version to 1
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 3.6.0
 *
 * @uses wp_get_post_revisions()
 *
 * @param object $post Post object
 * @param array $revisions Current revisions of the post
 * @return bool true if the revisions were upgraded, false if problems
 */
function _wp_upgrade_revisions_of_post( $post, $revisions ) {
	global $wpdb;

	// Add post option exclusively
	$lock = "revision-upgrade-{$post->ID}";
	$now = time();
	$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no') /* LOCK */", $lock, $now ) );
	if ( ! $result ) {
		// If we couldn't get a lock, see how old the previous lock is
		$locked = get_option( $lock );
		if ( ! $locked ) {
			// Can't write to the lock, and can't read the lock.
			// Something broken has happened
			return false;
		}

		if ( $locked > $now - 3600 ) {
			// Lock is not too old: some other process may be upgrading this post.  Bail.
			return false;
		}

		// Lock is too old - update it (below) and continue
	}

	// If we could get a lock, re-"add" the option to fire all the correct filters.
	update_option( $lock, $now );

	reset( $revisions );

	do {
		$this_revision = current( $revisions );
		$prev_revision = next( $revisions );

		$this_revision_version = _wp_get_post_revision_version( $this_revision );

		// Something terrible happened
		if ( false === $this_revision_version )
			continue;

		// 1 is the latest revision version, so we're already up to date
		if ( 0 < $this_revision_version )
			continue;

		// Always update the revision version
		$update = array(
			'post_name' => preg_replace( '/^(\d+-(?:autosave|revision))[\d-]*$/', '$1-v1', $this_revision->post_name ),
		);

		// If this revision is the oldest revision of the post, i.e. no $prev_revision,
		// the correct post_author is probably $post->post_author, but that's only a good guess.
		// Update the revision version only and Leave the author as-is.
		if ( $prev_revision ) {
			$prev_revision_version = _wp_get_post_revision_version( $prev_revision );

			// If the previous revision is already up to date, it no longer has the information we need :(
			if ( $prev_revision_version < 1 )
				$update['post_author'] = $prev_revision->post_author;
		}

		// Upgrade this revision
		$result = $wpdb->update( $wpdb->posts, $update, array( 'ID' => $this_revision->ID ) );

		if ( $result )
			wp_cache_delete( $this_revision->ID, 'posts' );

	} while ( $prev_revision );

	delete_option( $lock );

	// Add a copy of the post as latest revision.
	wp_save_post_revision( $post->ID );
	return true;
}

/**
 * Displays a human readable HTML representation of the difference between two strings.
 * similar to wp_text_diff, but tracks and returns could of lines added and removed
 *
 * @since 3.6
 * @see wp_parse_args() Used to change defaults to user defined settings.
 * @uses Text_Diff
 * @uses WP_Text_Diff_Renderer_Table
 *
 * @param string $left_string "old" (left) version of string
 * @param string $right_string "new" (right) version of string
 * @param string|array $args Optional. Change 'title', 'title_left', and 'title_right' defaults.
 * @return array contains html, linesadded & linesdeletd, empty string if strings are equivalent.
 */
function wp_text_diff_with_count( $left_string, $right_string, $args = null ) {
	$defaults = array( 'title' => '', 'title_left' => '', 'title_right' => '' );
	$args = wp_parse_args( $args, $defaults );

	if ( ! class_exists( 'WP_Text_Diff_Renderer_Table' ) )
			require( ABSPATH . WPINC . '/wp-diff.php' );

	$left_string  = normalize_whitespace( $left_string );
	$right_string = normalize_whitespace( $right_string );

	$left_lines  = explode( "\n", $left_string );
	$right_lines = explode( "\n", $right_string) ;

	$text_diff = new Text_Diff($left_lines, $right_lines  );
	$lines_added = $text_diff->countAddedLines();
	$lines_deleted = $text_diff->countDeletedLines();

	$renderer  = new WP_Text_Diff_Renderer_Table();
	$diff = $renderer->render( $text_diff );

	if ( !$diff )
			return '';

		$r  = "<table class='diff'>\n";

	if ( ! empty( $args[ 'show_split_view' ] ) ) {
		$r .= "<col class='content diffsplit left' /><col class='content diffsplit middle' /><col class='content diffsplit right' />";
	} else {
		$r .= "<col class='content' />";
	}

	if ( $args['title'] || $args['title_left'] || $args['title_right'] )
		$r .= "<thead>";
	if ( $args['title'] )
		$r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
	if ( $args['title_left'] || $args['title_right'] ) {
		$r .= "<tr class='diff-sub-title'>\n";
		$r .= "\t<td></td><th>$args[title_left]</th>\n";
		$r .= "\t<td></td><th>$args[title_right]</th>\n";
		$r .= "</tr>\n";
	}
	if ( $args['title'] || $args['title_left'] || $args['title_right'] )
		$r .= "</thead>\n";

	$r .= "<tbody>\n$diff\n</tbody>\n";
	$r .= "</table>";

	return array( 'html' => $r, 'lines_added' => $lines_added, 'lines_deleted' => $lines_deleted );
}
