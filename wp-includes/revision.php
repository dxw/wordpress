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
function wp_save_post_revision( $post_id, $new_data = null ) {
	// We do autosaves manually with wp_create_post_autosave()
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return;

	if ( ! $post = get_post( $post_id, ARRAY_A ) )
		return;

	if ( ! wp_revisions_enabled( (object) $post ) )
		return;

	if ( 'auto-draft' == $post['post_status'] )
		return;

	if ( ! post_type_supports( $post['post_type'], 'revisions' ) )
		return;

	// if new data is supplied, check that it is different from last saved revision, unless a plugin tells us to always save regardless
	if ( apply_filters( 'wp_save_post_revision_check_for_changes', true, $post, $new_data ) && is_array( $new_data ) ) {
		$post_has_changed = false;
		foreach ( array_keys( _wp_post_revision_fields() ) as $field ) {
			if ( normalize_whitespace( $new_data[ $field ] ) != normalize_whitespace( $post[ $field ] ) ) {
				$post_has_changed = true;
				break;
			}
		}
		//don't save revision if post unchanged
		if( ! $post_has_changed )
			return;
	}

	$return = _wp_put_post_revision( $post );

	$revisions_to_keep = wp_revisions_to_keep( (object) $post );

	if ( $revisions_to_keep < 0 )
		return $return;

	// all revisions and (possibly) one autosave
	$revisions = wp_get_post_revisions( $post_id, array( 'order' => 'ASC' ) );

	$delete = count($revisions) - $revisions_to_keep;

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

	$post = _wp_post_revision_fields( $post, $autosave );
	$post = wp_slash($post); //since data is from db

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
 * Determines if the specified post's most recent revision matches the post (by checking post_modified).
 *
 * @package WordPress
 * @subpackage Post_Revisions
 * @since 3.6.0
 *
 * @param int|object $post Post ID or post object.
 * @return bool false if not a match, otherwise true.
 */
function wp_first_revision_matches_current_version( $post ) {

	if ( ! $post = get_post( $post ) )
		return false;

	if ( ! $revisions = wp_get_post_revisions( $post->ID ) )
		return false;

	$last_revision = array_shift( $revisions );

	if ( ! ($last_revision->post_modified == $post->post_modified ) )
		return false;

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

	if ( !class_exists( 'WP_Text_Diff_Renderer_Table' ) )
			require( ABSPATH . WPINC . '/wp-diff.php' );

	$left_string  = normalize_whitespace( $left_string );
	$right_string = normalize_whitespace( $right_string );

	$left_lines  = explode( "\n", $left_string );
	$right_lines = explode( "\n", $right_string) ;

	$text_diff = new Text_Diff($left_lines, $right_lines  );
	$linesadded = $text_diff->countAddedLines();
	$linesdeleted = $text_diff->countDeletedLines();

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

	return array( 'html' => $r, 'linesadded' => $linesadded, 'linesdeleted' => $linesdeleted );
	}
