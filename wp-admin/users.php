<?php
/**
 * Users administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

/** WordPress Registration API */
require_once( ABSPATH . WPINC . '/registration.php');

if ( !current_user_can('edit_users') )
	wp_die(__('Cheatin&#8217; uh?'));

$title = __('Users');
$parent_file = 'users.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$update = '';

if ( empty($action) ) {
	if ( isset($_GET['changeit']) && !empty($_GET['new_role']) )
		$action = 'promote';
}

if ( empty($_REQUEST) ) {
	$referer = '<input type="hidden" name="wp_http_referer" value="'. attribute_escape(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
} elseif ( isset($_REQUEST['wp_http_referer']) ) {
	$redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($_REQUEST['wp_http_referer']));
	$referer = '<input type="hidden" name="wp_http_referer" value="' . attribute_escape($redirect) . '" />';
} else {
	$redirect = 'users.php';
	$referer = '';
}

switch ($action) {

case 'promote':
	check_admin_referer('bulk-users');

	if (empty($_REQUEST['users'])) {
		wp_redirect($redirect);
		exit();
	}

	if ( !current_user_can('edit_users') )
		wp_die(__('You can&#8217;t edit users.'));

	$userids = $_REQUEST['users'];
	$update = 'promote';
	foreach($userids as $id) {
		if ( ! current_user_can('edit_user', $id) )
			wp_die(__('You can&#8217;t edit that user.'));
		// The new role of the current user must also have edit_users caps
		if($id == $current_user->ID && !$wp_roles->role_objects[$_REQUEST['new_role']]->has_cap('edit_users')) {
			$update = 'err_admin_role';
			continue;
		}

		$user = new WP_User($id);
		$user->set_role($_REQUEST['new_role']);
	}

	wp_redirect(add_query_arg('update', $update, $redirect));
	exit();

break;

case 'dodelete':

	check_admin_referer('delete-users');

	if ( empty($_REQUEST['users']) ) {
		wp_redirect($redirect);
		exit();
	}

	if ( !current_user_can('delete_users') )
		wp_die(__('You can&#8217;t delete users.'));

	$userids = $_REQUEST['users'];
	$update = 'del';
	$delete_count = 0;

	foreach ( (array) $userids as $id) {
		if ( ! current_user_can('delete_user', $id) )
			wp_die(__('You can&#8217;t delete that user.'));

		if($id == $current_user->ID) {
			$update = 'err_admin_del';
			continue;
		}
		switch($_REQUEST['delete_option']) {
		case 'delete':
			wp_delete_user($id);
			break;
		case 'reassign':
			wp_delete_user($id, $_REQUEST['reassign_user']);
			break;
		}
		++$delete_count;
	}

	$redirect = add_query_arg( array('delete_count' => $delete_count, 'update' => $update), $redirect);
	wp_redirect($redirect);
	exit();

break;

case 'delete':

	check_admin_referer('bulk-users');

	if ( empty($_REQUEST['users']) ) {
		wp_redirect($redirect);
		exit();
	}

	if ( !current_user_can('delete_users') )
		$errors = new WP_Error('edit_users', __('You can&#8217;t delete users.'));

	$userids = $_REQUEST['users'];

	include ('admin-header.php');
?>
<form action="" method="post" name="updateusers" id="updateusers">
<?php wp_nonce_field('delete-users') ?>
<?php echo $referer; ?>

<div class="wrap">
<h2><?php _e('Delete Users'); ?></h2>
<p><?php _e('You have specified these users for deletion:'); ?></p>
<ul>
<?php
	$go_delete = false;
	foreach ( (array) $userids as $id ) {
		$user = new WP_User($id);
		if ( $id == $current_user->ID ) {
			echo "<li>" . sprintf(__('ID #%1s: %2s <strong>The current user will not be deleted.</strong>'), $id, $user->user_login) . "</li>\n";
		} else {
			echo "<li><input type=\"hidden\" name=\"users[]\" value=\"{$id}\" />" . sprintf(__('ID #%1s: %2s'), $id, $user->user_login) . "</li>\n";
			$go_delete = true;
		}
	}
	$all_logins = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users ORDER BY user_login");
	$user_dropdown = '<select name="reassign_user">';
	foreach ( (array) $all_logins as $login )
		if ( $login->ID == $current_user->ID || !in_array($login->ID, $userids) )
			$user_dropdown .= "<option value=\"{$login->ID}\">{$login->user_login}</option>";
	$user_dropdown .= '</select>';
	?>
	</ul>
<?php if ( $go_delete ) : ?>
	<fieldset><p><legend><?php _e('What should be done with posts and links owned by this user?'); ?></legend></p>
	<ul style="list-style:none;">
		<li><label><input type="radio" id="delete_option0" name="delete_option" value="delete" checked="checked" />
		<?php _e('Delete all posts and links.'); ?></label></li>
		<li><input type="radio" id="delete_option1" name="delete_option" value="reassign" />
		<?php echo '<label for="delete_option1">'.__('Attribute all posts and links to:')."</label> $user_dropdown"; ?></li>
	</ul></fieldset>
	<input type="hidden" name="action" value="dodelete" />
	<p class="submit"><input type="submit" name="submit" value="<?php _e('Confirm Deletion'); ?>" class="button-secondary" /></p>
<?php else : ?>
	<p><?php _e('There are no valid users selected for deletion.'); ?></p>
<?php endif; ?>
</div>
</form>
<?php

break;

case 'adduser':
	check_admin_referer('add-user');

	if ( ! current_user_can('create_users') )
		wp_die(__('You can&#8217;t create users.'));

	$user_id = add_user();
	$update = 'add';
	if ( is_wp_error( $user_id ) )
		$add_user_errors = $user_id;
	else {
		$new_user_login = apply_filters('pre_user_login', sanitize_user(stripslashes($_REQUEST['user_login']), true));
		$redirect = add_query_arg( array('usersearch' => urlencode($new_user_login), 'update' => $update), $redirect );
		wp_redirect( $redirect . '#user-' . $user_id );
		die();
	}

default:

	if ( !empty($_GET['_wp_http_referer']) ) {
		wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
		exit;
	}

	wp_enqueue_script('admin-users');
	wp_enqueue_script('admin-forms');

	include('admin-header.php');

	$usersearch = isset($_GET['usersearch']) ? $_GET['usersearch'] : null;
	$userspage = isset($_GET['userspage']) ? $_GET['userspage'] : null;
	$role = isset($_GET['role']) ? $_GET['role'] : null;
	
	// Query the users
	$wp_user_search = new WP_User_Search($usersearch, $userspage, $role);

	if ( isset($_GET['update']) ) :
		switch($_GET['update']) {
		case 'del':
		case 'del_many':
		?>
			<?php $delete_count = isset($_GET['delete_count']) ? (int) $_GET['delete_count'] : 0; ?>
			<div id="message" class="updated fade"><p><?php printf(__ngettext('%s user deleted', '%s users deleted', $delete_count), $delete_count); ?></p></div>
		<?php
			break;
		case 'add':
		?>
			<div id="message" class="updated fade"><p><?php _e('New user created.'); ?></p></div>
		<?php
			break;
		case 'promote':
		?>
			<div id="message" class="updated fade"><p><?php _e('Changed roles.'); ?></p></div>
		<?php
			break;
		case 'err_admin_role':
		?>
			<div id="message" class="error"><p><?php _e("The current user's role must have user editing capabilities."); ?></p></div>
			<div id="message" class="updated fade"><p><?php _e('Other user roles have been changed.'); ?></p></div>
		<?php
			break;
		case 'err_admin_del':
		?>
			<div id="message" class="error"><p><?php _e("You can't delete the current user."); ?></p></div>
			<div id="message" class="updated fade"><p><?php _e('Other users have been deleted.'); ?></p></div>
		<?php
			break;
		}
	endif; ?>

<?php if ( isset($errors) && is_wp_error( $errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
			foreach ( $errors->get_error_messages() as $message )
				echo "<li>$message</li>";
		?>
		</ul>
	</div>
<?php endif; ?>

<div class="wrap">
<form id="posts-filter" action="" method="get">
	<?php if ( $wp_user_search->is_search() ) : ?>
		<h2><?php printf( current_user_can('create_users') ? __('Users Matching "%2$s" (<a href="%1$s">Add New</a>)') : __('Add New'), '#add-new-user', wp_specialchars($wp_user_search->search_term) ); ?></h2>
	<?php else : ?>
		<h2><?php printf( current_user_can('create_users') ? __('Users (<a href="%s">Add New</a>)') : __('Add New'), '#add-new-user' ); ?></h2>
	<?php endif; ?>

<ul class="subsubsub">
<?php
$role_links = array();
$avail_roles = array();
$users_of_blog = get_users_of_blog();
//var_dump($users_of_blog);
foreach ( (array) $users_of_blog as $b_user ) {
	$b_roles = unserialize($b_user->meta_value);
	foreach ( (array) $b_roles as $b_role => $val ) {
		if ( !isset($avail_roles[$b_role]) )
			$avail_roles[$b_role] = 0;
		$avail_roles[$b_role]++;
	}
}
unset($users_of_blog);

$current_role = false;
$class = empty($role) ? ' class="current"' : '';
$role_links[] = "<li><a href=\"users.php\"$class>" . __('All Users') . "</a>";
foreach ( $wp_roles->get_names() as $this_role => $name ) {
	if ( !isset($avail_roles[$role]) )
		continue;

	$class = '';

	if ( $this_role == $role ) {
		$current_role = $role;
		$class = ' class="current"';
	}

	$name = translate_with_context($name);
	$name = sprintf(_c('%1$s (%2$s)|user role with count'), $name, $avail_roles[$this_role]);
	$role_links[] = "<li><a href=\"users.php?role=$this_role\"$class>" . $name . '</a>';
}
echo implode(' |</li>', $role_links) . '</li>';
unset($role_links);
?>
</ul>

	<p id="post-search">
	<label class="hidden" for="post-search-input"><?php _e( 'Search Users' ); ?>:</label>
	<input type="text" id="post-search-input" name="usersearch" value="<?php echo attribute_escape($wp_user_search->search_term); ?>" />
	<input type="submit" value="<?php _e( 'Search Users' ); ?>" class="button" />
	</p>

<div class="tablenav">

<?php if ( $wp_user_search->results_are_paged() ) : ?>
	<div class="tablenav-pages"><?php $wp_user_search->page_links(); ?></div>
<?php endif; ?>

<div class="alignleft">
<select name="action">
<option value="" selected><?php _e('Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction" class="button-secondary action" />
<label class="hidden" for="new_role"><?php _e('Change role to&hellip;') ?></label><select name="new_role" id="new_role"><option value=''><?php _e('Change role to&hellip;') ?></option>"<?php wp_dropdown_roles(); ?></select>
<input type="submit" value="<?php _e('Change'); ?>" name="changeit" class="button-secondary" />
<?php wp_nonce_field('bulk-users'); ?>
</div>

<br class="clear" />
</div>

<br class="clear" />

	<?php if ( is_wp_error( $wp_user_search->search_errors ) ) : ?>
		<div class="error">
			<ul>
			<?php
				foreach ( $wp_user_search->search_errors->get_error_messages() as $message )
					echo "<li>$message</li>";
			?>
			</ul>
		</div>
	<?php endif; ?>


<?php if ( $wp_user_search->get_results() ) : ?>

	<?php if ( $wp_user_search->is_search() ) : ?>
		<p><a href="users.php"><?php _e('&laquo; Back to All Users'); ?></a></p>
	<?php endif; ?>

<table class="widefat">
<thead>
<tr class="thead">
	<th scope="col" class="check-column"><input type="checkbox" /></th>
	<th><?php _e('Username') ?></th>
	<th><?php _e('Name') ?></th>
	<th><?php _e('E-mail') ?></th>
	<th><?php _e('Role') ?></th>
	<th class="num"><?php _e('Posts') ?></th>
</tr>
</thead>
<tbody id="users" class="list:user user-list">
<?php
$style = '';
foreach ( $wp_user_search->get_results() as $userid ) {
	$user_object = new WP_User($userid);
	$roles = $user_object->roles;
	$role = array_shift($roles);

	$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
	echo "\n\t" . user_row($user_object, $style, $role);
}
?>
</tbody>
</table>

<div class="tablenav">

<?php if ( $wp_user_search->results_are_paged() ) : ?>
	<div class="tablenav-pages"><?php $wp_user_search->page_links(); ?></div>
<?php endif; ?>

<br class="clear" />
</div>

<?php endif; ?>

</form>
</div>

<?php
	foreach ( array('user_login' => 'user_login', 'first_name' => 'user_firstname', 'last_name' => 'user_lastname', 'email' => 'user_email', 'url' => 'user_uri', 'role' => 'user_role') as $formpost => $var ) {
		$var = 'new_' . $var;
		$$var = isset($_REQUEST[$formpost]) ? attribute_escape(stripslashes($_REQUEST[$formpost])) : '';
	}
	unset($name);
?>

<br class="clear" />
<?php if ( current_user_can('create_users') ) { ?>

<div class="wrap">
<h2 id="add-new-user"><?php _e('Add New User') ?></h2>

<?php if ( isset($add_user_errors) && is_wp_error( $add_user_errors ) ) : ?>
	<div class="error">
		<?php
			foreach ( $add_user_errors->get_error_messages() as $message )
				echo "<p>$message</p>";
		?>
	</div>
<?php endif; ?>
<div id="ajax-response"></div>

<?php
	if ( get_option('users_can_register') )
		echo '<p>' . sprintf(__('Users can <a href="%1$s">register themselves</a> or you can manually create users here.'), site_url('wp-register.php')) . '</p>';
	else
		echo '<p>' . sprintf(__('Users cannot currently <a href="%1$s">register themselves</a>, but you can manually create users here.'), admin_url('options-general.php#users_can_register')) . '</p>';
?>
<form action="#add-new-user" method="post" name="adduser" id="adduser" class="add:users: validate">
<?php wp_nonce_field('add-user') ?>
<table class="form-table">
	<tr class="form-field form-required">
		<th scope="row"><label for="user_login"><?php _e('Username (required)') ?></label><input name="action" type="hidden" id="action" value="adduser" /></th>
		<td ><input name="user_login" type="text" id="user_login" value="<?php echo $new_user_login; ?>" aria-required="true" /></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="first_name"><?php _e('First Name') ?> </label></th>
		<td><input name="first_name" type="text" id="first_name" value="<?php echo $new_user_firstname; ?>" /></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="last_name"><?php _e('Last Name') ?> </label></th>
		<td><input name="last_name" type="text" id="last_name" value="<?php echo $new_user_lastname; ?>" /></td>
	</tr>
	<tr class="form-field form-required">
		<th scope="row"><label for="email"><?php _e('E-mail (required)') ?></label></th>
		<td><input name="email" type="text" id="email" value="<?php echo $new_user_email; ?>" /></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="url"><?php _e('Website') ?></label></th>
		<td><input name="url" type="text" id="url" value="<?php echo $new_user_uri; ?>" /></td>
	</tr>

<?php if ( apply_filters('show_password_fields', true) ) : ?>
	<tr class="form-field form-required">
		<th scope="row"><label for="pass1"><?php _e('Password (twice)') ?> </label></th>
		<td><input name="pass1" type="password" id="pass1" />
		<br />
		<input name="pass2" type="password" id="pass2" /></td>
	</tr>
<?php endif; ?>

	<tr class="form-field">
		<th scope="row"><label for="role"><?php _e('Role'); ?></label></th>
		<td><select name="role" id="role">
			<?php
			if ( !$new_user_role )
				$new_user_role = $current_role ? $current_role : get_option('default_role');
			wp_dropdown_roles($new_user_role);
			?>
			</select>
		</td>
	</tr>
</table>
<p class="submit">
	<?php echo $referer; ?>
	<input name="adduser" type="submit" id="addusersub" value="<?php _e('Add User') ?>" />
</p>
</form>

</div>

<?php
}
break;

} // end of the $action switch

include('admin-footer.php');
?>
