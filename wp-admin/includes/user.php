<?php
/**
 * WordPress user administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Creates a new user from the "Users" form using $_POST information.
 *
 * {@internal Missing Long Description}}
 *
 * @since unknown
 *
 * @param int $user_id Optional. User ID.
 * @return null|WP_Error|int Null when adding user, WP_Error or User ID integer when no parameters.
 */
function add_user() {
	if ( func_num_args() ) { // The hackiest hack that ever did hack
		global $current_user, $wp_roles;
		$user_id = (int) func_get_arg( 0 );

		if ( isset( $_POST['role'] ) ) {
			if( $user_id != $current_user->id || $wp_roles->role_objects[$_POST['role']]->has_cap( 'edit_users' ) ) {
				$user = new WP_User( $user_id );
				$user->set_role( $_POST['role'] );
			}
		}
	} else {
		add_action( 'user_register', 'add_user' ); // See above
		return edit_user();
	}
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since unknown
 *
 * @param int $user_id Optional. User ID.
 * @return unknown
 */
function edit_user( $user_id = 0 ) {
	global $current_user, $wp_roles, $wpdb;
	if ( $user_id != 0 ) {
		$update = true;
		$user->ID = (int) $user_id;
		$userdata = get_userdata( $user_id );
		$user->user_login = $wpdb->escape( $userdata->user_login );
	} else {
		$update = false;
		$user = '';
	}

	if ( isset( $_POST['user_login'] ))
		$user->user_login = wp_specialchars( trim( $_POST['user_login'] ));

	$pass1 = $pass2 = '';
	if ( isset( $_POST['pass1'] ))
		$pass1 = $_POST['pass1'];
	if ( isset( $_POST['pass2'] ))
		$pass2 = $_POST['pass2'];

	if ( isset( $_POST['role'] ) && current_user_can( 'edit_users' ) ) {
		if( $user_id != $current_user->id || $wp_roles->role_objects[$_POST['role']]->has_cap( 'edit_users' ))
			$user->role = $_POST['role'];
	}

	if ( isset( $_POST['email'] ))
		$user->user_email = wp_specialchars( trim( $_POST['email'] ));
	if ( isset( $_POST['url'] ) ) {
		$user->user_url = clean_url( trim( $_POST['url'] ));
		$user->user_url = preg_match('/^(https?|ftps?|mailto|news|irc|gopher|nntp|feed|telnet):/is', $user->user_url) ? $user->user_url : 'http://'.$user->user_url;
	}
	if ( isset( $_POST['first_name'] ))
		$user->first_name = wp_specialchars( trim( $_POST['first_name'] ));
	if ( isset( $_POST['last_name'] ))
		$user->last_name = wp_specialchars( trim( $_POST['last_name'] ));
	if ( isset( $_POST['nickname'] ))
		$user->nickname = wp_specialchars( trim( $_POST['nickname'] ));
	if ( isset( $_POST['display_name'] ))
		$user->display_name = wp_specialchars( trim( $_POST['display_name'] ));
	if ( isset( $_POST['description'] ))
		$user->description = trim( $_POST['description'] );
	if ( isset( $_POST['jabber'] ))
		$user->jabber = wp_specialchars( trim( $_POST['jabber'] ));
	if ( isset( $_POST['aim'] ))
		$user->aim = wp_specialchars( trim( $_POST['aim'] ));
	if ( isset( $_POST['yim'] ))
		$user->yim = wp_specialchars( trim( $_POST['yim'] ));
	if ( !$update )
		$user->rich_editing = 'true';  // Default to true for new users.
	else if ( isset( $_POST['rich_editing'] ) )
		$user->rich_editing = $_POST['rich_editing'];
	else
		$user->rich_editing = 'true';

	$user->comment_shortcuts = isset( $_POST['comment_shortcuts'] )? $_POST['comment_shortcuts'] : '';

	$user->use_ssl = 0;
	if ( !empty($_POST['use_ssl']) )
		$user->use_ssl = 1;

	if ( !$update )
		$user->admin_color = 'fresh';  // Default to fresh for new users.
	else if ( isset( $_POST['admin_color'] ) )
		$user->admin_color = $_POST['admin_color'];
	else
		$user->admin_color = 'fresh';

	$errors = new WP_Error();

	/* checking that username has been typed */
	if ( $user->user_login == '' )
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: Please enter a username.' ));

	/* checking the password has been typed twice */
	do_action_ref_array( 'check_passwords', array ( $user->user_login, & $pass1, & $pass2 ));

	if ( $update ) {
		if ( empty($pass1) && !empty($pass2) )
			$errors->add( 'pass', __( '<strong>ERROR</strong>: You entered your new password only once.' ), array( 'form-field' => 'pass1' ) );
		elseif ( !empty($pass1) && empty($pass2) )
			$errors->add( 'pass', __( '<strong>ERROR</strong>: You entered your new password only once.' ), array( 'form-field' => 'pass2' ) );
	} else {
		if ( empty($pass1) )
			$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter your password.' ), array( 'form-field' => 'pass1' ) );
		elseif ( empty($pass2) )
			$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter your password twice.' ), array( 'form-field' => 'pass2' ) );
	}

	/* Check for "\" in password */
	if( strpos( " ".$pass1, "\\" ) )
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Passwords may not contain the character "\\".' ), array( 'form-field' => 'pass1' ) );

	/* checking the password has been typed twice the same */
	if ( $pass1 != $pass2 )
		$errors->add( 'pass', __( '<strong>ERROR</strong>: Please enter the same password in the two password fields.' ), array( 'form-field' => 'pass1' ) );

	if (!empty ( $pass1 ))
		$user->user_pass = $pass1;

	if ( !$update && !validate_username( $user->user_login ) )
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: This username is invalid. Please enter a valid username.' ));

	if (!$update && username_exists( $user->user_login ))
		$errors->add( 'user_login', __( '<strong>ERROR</strong>: This username is already registered. Please choose another one.' ));

	/* checking e-mail address */
	if ( empty ( $user->user_email ) ) {
		$errors->add( 'user_email', __( '<strong>ERROR</strong>: Please enter an e-mail address.' ), array( 'form-field' => 'email' ) );
	} else
		if (!is_email( $user->user_email ) ) {
			$errors->add( 'user_email', __( "<strong>ERROR</strong>: The e-mail address isn't correct." ), array( 'form-field' => 'email' ) );
		}

	if ( $errors->get_error_codes() )
		return $errors;

	if ( $update ) {
		$user_id = wp_update_user( get_object_vars( $user ));
	} else {
		$user_id = wp_insert_user( get_object_vars( $user ));
		wp_new_user_notification( $user_id );
	}
	return $user_id;
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since unknown
 *
 * @return array List of user IDs.
 */
function get_author_user_ids() {
	global $wpdb;
	$level_key = $wpdb->prefix . 'user_level';
	return $wpdb->get_col( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value != '0'", $level_key) );
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since unknown
 *
 * @param int $user_id User ID.
 * @return array|bool List of editable authors. False if no editable users.
 */
function get_editable_authors( $user_id ) {
	global $wpdb;

	$editable = get_editable_user_ids( $user_id );

	if( !$editable ) {
		return false;
	} else {
		$editable = join(',', $editable);
		$authors = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE ID IN ($editable) ORDER BY display_name" );
	}

	return apply_filters('get_editable_authors', $authors);
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since unknown
 *
 * @param int $user_id User ID.
 * @param bool $exclude_zeros Optional, default is true. Whether to exclude zeros.
 * @return unknown
 */
function get_editable_user_ids( $user_id, $exclude_zeros = true, $post_type = 'post' ) {
	global $wpdb;

	$user = new WP_User( $user_id );

	if ( ! $user->has_cap("edit_others_{$post_type}s") ) {
		if ( $user->has_cap("edit_{$post_type}s") || $exclude_zeros == false )
			return array($user->id);
		else
			return false;
	}

	$level_key = $wpdb->prefix . 'user_level';

	$query = $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s", $level_key);
	if ( $exclude_zeros )
		$query .= " AND meta_value != '0'";

	return $wpdb->get_col( $query );
}

/**
 * {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since unknown
 *
 * @return unknown
 */
function get_nonauthor_user_ids() {
	global $wpdb;
	$level_key = $wpdb->prefix . 'user_level';

	return $wpdb->get_col( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value = '0'", $level_key) );
}

/**
 * Retrieve editable posts from other users.
 *
 * @since unknown
 *
 * @param int $user_id User ID to not retrieve posts from.
 * @param string $type Optional, defaults to 'any'. Post type to retrieve, can be 'draft' or 'pending'.
 * @return array List of posts from others.
 */
function get_others_unpublished_posts($user_id, $type='any') {
	global $wpdb;

	$editable = get_editable_user_ids( $user_id );

	if ( in_array($type, array('draft', 'pending')) )
		$type_sql = " post_status = '$type' ";
	else
		$type_sql = " ( post_status = 'draft' OR post_status = 'pending' ) ";

	$dir = ( 'pending' == $type ) ? 'ASC' : 'DESC';

	if( !$editable ) {
		$other_unpubs = '';
	} else {
		$editable = join(',', $editable);
		$other_unpubs = $wpdb->get_results( $wpdb->prepare("SELECT ID, post_title, post_author FROM $wpdb->posts WHERE post_type = 'post' AND $type_sql AND post_author IN ($editable) AND post_author != %d ORDER BY post_modified $dir", $user_id) );
	}

	return apply_filters('get_others_drafts', $other_unpubs);
}

/**
 * Retrieve drafts from other users.
 *
 * @since unknown
 *
 * @param int $user_id User ID.
 * @return array List of drafts from other users.
 */
function get_others_drafts($user_id) {
	return get_others_unpublished_posts($user_id, 'draft');
}

/**
 * Retrieve pending review posts from other users.
 *
 * @since unknown
 *
 * @param int $user_id User ID.
 * @return array List of posts with pending review post type from other users.
 */
function get_others_pending($user_id) {
	return get_others_unpublished_posts($user_id, 'pending');
}

/**
 * Retrieve user data and filter it.
 *
 * @since unknown
 *
 * @param int $user_id User ID.
 * @return object WP_User object with user data.
 */
function get_user_to_edit( $user_id ) {
	$user = new WP_User( $user_id );
	$user->user_login   = attribute_escape($user->user_login);
	$user->user_email   = attribute_escape($user->user_email);
	$user->user_url     = clean_url($user->user_url);
	$user->first_name   = attribute_escape($user->first_name);
	$user->last_name    = attribute_escape($user->last_name);
	$user->display_name = attribute_escape($user->display_name);
	$user->nickname     = attribute_escape($user->nickname);
	$user->aim          = isset( $user->aim ) && !empty( $user->aim ) ? attribute_escape($user->aim) : '';
	$user->yim          = isset( $user->yim ) && !empty( $user->yim ) ? attribute_escape($user->yim) : '';
	$user->jabber       = isset( $user->jabber ) && !empty( $user->jabber ) ? attribute_escape($user->jabber) : '';
	$user->description  = isset( $user->description ) && !empty( $user->description ) ? wp_specialchars($user->description) : '';

	return $user;
}

/**
 * Retrieve the user's drafts.
 *
 * @since unknown
 *
 * @param int $user_id User ID.
 * @return array
 */
function get_users_drafts( $user_id ) {
	global $wpdb;
	$query = $wpdb->prepare("SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'draft' AND post_author = %d ORDER BY post_modified DESC", $user_id);
	$query = apply_filters('get_users_drafts', $query);
	return $wpdb->get_results( $query );
}

/**
 * Remove user and optionally reassign posts and links to another user.
 *
 * If the $reassign parameter is not assigned to an User ID, then all posts will
 * be deleted of that user. The action 'delete_user' that is passed the User ID
 * being deleted will be run after the posts are either reassigned or deleted.
 * The user meta will also be deleted that are for that User ID.
 *
 * @since unknown
 *
 * @param int $id User ID.
 * @param int $reassign Optional. Reassign posts and links to new User ID.
 * @return bool True when finished.
 */
function wp_delete_user($id, $reassign = 'novalue') {
	global $wpdb;

	$id = (int) $id;

	if ($reassign == 'novalue') {
		$post_ids = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id) );

		if ($post_ids) {
			foreach ($post_ids as $post_id)
				wp_delete_post($post_id);
		}

		// Clean links
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->links WHERE link_owner = %d", $id) );
	} else {
		$reassign = (int) $reassign;
		$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET post_author = %d WHERE post_author = %d", $reassign, $id) );
		$wpdb->query( $wpdb->prepare("UPDATE $wpdb->links SET link_owner = %d WHERE link_owner = %d", $reassign, $id) );
	}

	// FINALLY, delete user
	do_action('delete_user', $id);

	$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->users WHERE ID = %d", $id) );
	$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id = %d", $id) );

	$user = new WP_User($id);

	wp_cache_delete($id, 'users');
	wp_cache_delete($user->user_login, 'userlogins');
	wp_cache_delete($user->user_email, 'useremail');

	return true;
}

/**
 * Remove all capabilities from user.
 *
 * @since unknown
 *
 * @param int $id User ID.
 */
function wp_revoke_user($id) {
	$id = (int) $id;

	$user = new WP_User($id);
	$user->remove_all_caps();
}

if ( !class_exists('WP_User_Search') ) :
/**
 * WordPress User Search class.
 *
 * @since unknown
 * @author Mark Jaquith
 */
class WP_User_Search {

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $results;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $search_term;

	/**
	 * Page number.
	 *
	 * @since unknown
	 * @access private
	 * @var int
	 */
	var $page;

	/**
	 * Role name that users have.
	 *
	 * @since unknown
	 * @access private
	 * @var string
	 */
	var $role;

	/**
	 * Raw page number.
	 *
	 * @since unknown
	 * @access private
	 * @var int|bool
	 */
	var $raw_page;

	/**
	 * Amount of users to display per page.
	 *
	 * @since unknown
	 * @access public
	 * @var int
	 */
	var $users_per_page = 50;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $first_user;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var int
	 */
	var $last_user;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $query_limit;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $query_sort;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $query_from_where;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var int
	 */
	var $total_users_for_query = 0;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var bool
	 */
	var $too_many_total_users = false;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $search_errors;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since unknown
	 * @access private
	 * @var unknown_type
	 */
	var $paging_text;

	/**
	 * PHP4 Constructor - Sets up the object properties.
	 *
	 * @since unknown
	 *
	 * @param string $search_term Search terms string.
	 * @param int $page Optional. Page ID.
	 * @param string $role Role name.
	 * @return WP_User_Search
	 */
	function WP_User_Search ($search_term = '', $page = '', $role = '') {
		$this->search_term = $search_term;
		$this->raw_page = ( '' == $page ) ? false : (int) $page;
		$this->page = (int) ( '' == $page ) ? 1 : $page;
		$this->role = $role;

		$this->prepare_query();
		$this->query();
		$this->prepare_vars_for_template_usage();
		$this->do_paging();
	}

	/**
	 * {@internal Missing Short Description}}
	 *
	 * {@internal Missing Long Description}}
	 *
	 * @since unknown
	 * @access public
	 */
	function prepare_query() {
		global $wpdb;
		$this->first_user = ($this->page - 1) * $this->users_per_page;
		$this->query_limit = $wpdb->prepare(" LIMIT %d, %d", $this->first_user, $this->users_per_page);
		$this->query_sort = ' ORDER BY user_login';
		$search_sql = '';
		if ( $this->search_term ) {
			$searches = array();
			$search_sql = 'AND (';
			foreach ( array('user_login', 'user_nicename', 'user_email', 'user_url', 'display_name') as $col )
				$searches[] = $col . " LIKE '%$this->search_term%'";
			$search_sql .= implode(' OR ', $searches);
			$search_sql .= ')';
		}

		$this->query_from_where = "FROM $wpdb->users";
		if ( $this->role )
			$this->query_from_where .= $wpdb->prepare(" INNER JOIN $wpdb->usermeta ON $wpdb->users.ID = $wpdb->usermeta.user_id WHERE $wpdb->usermeta.meta_key = '{$wpdb->prefix}capabilities' AND $wpdb->usermeta.meta_value LIKE %s", '%' . $this->role . '%');
		else
			$this->query_from_where .= " WHERE 1=1";
		$this->query_from_where .= " $search_sql";

	}

	/**
	 * {@internal Missing Short Description}}
	 *
	 * {@internal Missing Long Description}}
	 *
	 * @since unknown
	 * @access public
	 */
	function query() {
		global $wpdb;
		$this->results = $wpdb->get_col('SELECT ID ' . $this->query_from_where . $this->query_sort . $this->query_limit);

		if ( $this->results )
			$this->total_users_for_query = $wpdb->get_var('SELECT COUNT(ID) ' . $this->query_from_where); // no limit
		else
			$this->search_errors = new WP_Error('no_matching_users_found', __('No matching users were found!'));
	}

	/**
	 * {@internal Missing Short Description}}
	 *
	 * {@internal Missing Long Description}}
	 *
	 * @since unknown
	 * @access public
	 */
	function prepare_vars_for_template_usage() {
		$this->search_term = stripslashes($this->search_term); // done with DB, from now on we want slashes gone
	}

	/**
	 * {@internal Missing Short Description}}
	 *
	 * {@internal Missing Long Description}}
	 *
	 * @since unknown
	 * @access public
	 */
	function do_paging() {
		if ( $this->total_users_for_query > $this->users_per_page ) { // have to page the results
			$args = array();
			if( ! empty($this->search_term) )
				$args['usersearch'] = urlencode($this->search_term);
			if( ! empty($this->role) )
				$args['role'] = urlencode($this->role);

			$this->paging_text = paginate_links( array(
				'total' => ceil($this->total_users_for_query / $this->users_per_page),
				'current' => $this->page,
				'base' => 'users.php?%_%',
				'format' => 'userspage=%#%',
				'add_args' => $args
			) );
			if ( $this->paging_text ) {
				$this->paging_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
					number_format_i18n( ( $this->page - 1 ) * $this->users_per_page + 1 ),
					number_format_i18n( min( $this->page * $this->users_per_page, $this->total_users_for_query ) ),
					number_format_i18n( $this->total_users_for_query ),
					$this->paging_text
				);
			}
		}
	}

	/**
	 * {@internal Missing Short Description}}
	 *
	 * {@internal Missing Long Description}}
	 *
	 * @since unknown
	 * @access public
	 *
	 * @return unknown
	 */
	function get_results() {
		return (array) $this->results;
	}

	/**
	 * Displaying paging text.
	 *
	 * @see do_paging() Builds paging text.
	 *
	 * @since unknown
	 * @access public
	 */
	function page_links() {
		echo $this->paging_text;
	}

	/**
	 * Whether paging is enabled.
	 *
	 * @see do_paging() Builds paging text.
	 *
	 * @since unknown
	 * @access public
	 *
	 * @return bool
	 */
	function results_are_paged() {
		if ( $this->paging_text )
			return true;
		return false;
	}

	/**
	 * Whether there are search terms.
	 *
	 * @since unknown
	 * @access public
	 *
	 * @return bool
	 */
	function is_search() {
		if ( $this->search_term )
			return true;
		return false;
	}
}
endif;

?>
