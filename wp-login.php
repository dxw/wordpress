<?php
/**
 * WordPress User Page
 *
 * Handles authentication, registering, resetting passwords, forgot password,
 * and other user handling.
 *
 * @package WordPress
 */

/** Make sure that the WordPress bootstrap has ran before continuing. */
require( dirname(__FILE__) . '/wp-load.php' );

// Redirect to https login if forced to use SSL
if ( force_ssl_admin() && !is_ssl() ) {
	if ( 0 === strpos($_SERVER['REQUEST_URI'], 'http') ) {
		wp_redirect(preg_replace('|^http://|', 'https://', $_SERVER['REQUEST_URI']));
		exit();
	} else {
		wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		exit();			
	}
}

/**
 * login_header() - Outputs the header for the login page
 *
 * @package WordPress
 * @uses do_action() Calls the 'login_head' for outputting HTML in the Login
 *		header.
 * @uses apply_filters() Calls 'login_headerurl' for the top login link.
 * @uses apply_filters() Calls 'login_headertitle' for the top login title.
 * @uses apply_filters() Calls 'login_message' on the message to display in the
 *		header.
 * @uses $error The error global, which is checked for displaying errors.
 *
 * @param string $title Optional. WordPress Login Page title to display in
 *		<title/> element.
 * @param string $message Optional. Message to display in header.
 * @param WP_Error $wp_error Optional. WordPress Error Object
 */
function login_header($title = 'Login', $message = '', $wp_error = '') {
	global $error;

	if ( empty($wp_error) )
		$wp_error = new WP_Error();
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<title><?php bloginfo('name'); ?> &rsaquo; <?php echo $title; ?></title>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
	<?php
	wp_admin_css( 'login', true );
	wp_admin_css( 'colors-fresh', true );
	?>
	<script type="text/javascript">
		function focusit() {
			document.getElementById('user_login').focus();
		}
		window.onload = focusit;
	</script>
<?php do_action('login_head'); ?>
</head>
<body class="login">

<div id="login"><h1><a href="<?php echo apply_filters('login_headerurl', 'http://wordpress.org/'); ?>" title="<?php echo apply_filters('login_headertitle', __('Powered by WordPress')); ?>"><?php bloginfo('name'); ?></a></h1>
<?php
	if ( !empty( $message ) ) echo apply_filters('login_message', $message) . "\n";

	// Incase a plugin uses $error rather than the $errors object
	if ( !empty( $error ) ) {
		$wp_error->add('error', $error);
		unset($error);
	}

	if ( $wp_error->get_error_code() ) {
		$errors = '';
		$messages = '';
		foreach ( $wp_error->get_error_codes() as $code ) {
			$severity = $wp_error->get_error_data($code);
			foreach ( $wp_error->get_error_messages($code) as $error ) {
				if ( 'message' == $severity )
					$messages .= '	' . $error . "<br />\n";
				else
					$errors .= '	' . $error . "<br />\n";
			}
		}
		if ( !empty($errors) )
			echo '<div id="login_error">' . apply_filters('login_errors', $errors) . "</div>\n";
		if ( !empty($messages) )
			echo '<p class="message">' . apply_filters('login_messages', $messages) . "</p>\n";
	}
} // End of login_header()

/**
 * retrieve_password() - Handles sending password retrieval email to user
 *
 * {@internal Missing Long Description}}
 *
 * @uses $wpdb WordPress Database object
 *
 * @return bool|WP_Error True: when finish. WP_Error on error
 */
function retrieve_password() {
	global $wpdb;

	$errors = new WP_Error();

	if ( empty( $_POST['user_login'] ) && empty( $_POST['user_email'] ) )
		$errors->add('empty_username', __('<strong>ERROR</strong>: Enter a username or e-mail address.'));

	if ( strstr($_POST['user_login'], '@') ) {
		$user_data = get_user_by_email(trim($_POST['user_login']));
		if ( empty($user_data) )
			$errors->add('invalid_email', __('<strong>ERROR</strong>: There is no user registered with that email address.'));
	} else {
		$login = trim($_POST['user_login']);
		$user_data = get_userdatabylogin($login);
	}

	do_action('lostpassword_post');

	if ( $errors->get_error_code() )
		return $errors;

	if ( !$user_data ) {
		$errors->add('invalidcombo', __('<strong>ERROR</strong>: Invalid username or e-mail.'));
		return $errors;
	}

	// redefining user_login ensures we return the right case in the email
	$user_login = $user_data->user_login;
	$user_email = $user_data->user_email;

	do_action('retreive_password', $user_login);  // Misspelled and deprecated
	do_action('retrieve_password', $user_login);

	$key = $wpdb->get_var($wpdb->prepare("SELECT user_activation_key FROM $wpdb->users WHERE user_login = %s", $user_login));
	if ( empty($key) ) {
		// Generate something random for a key...
		$key = wp_generate_password(20, false);
		do_action('retrieve_password_key', $user_login, $key);
		// Now insert the new md5 key into the db
		$wpdb->query($wpdb->prepare("UPDATE $wpdb->users SET user_activation_key = %s WHERE user_login = %s", $key, $user_login));
	}
	$message = __('Someone has asked to reset the password for the following site and username.') . "\r\n\r\n";
	$message .= get_option('siteurl') . "\r\n\r\n";
	$message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
	$message .= __('To reset your password visit the following address, otherwise just ignore this email and nothing will happen.') . "\r\n\r\n";
	$message .= site_url("wp-login.php?action=rp&key=$key", 'login') . "\r\n";

	if ( !wp_mail($user_email, sprintf(__('[%s] Password Reset'), get_option('blogname')), $message) )
		die('<p>' . __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function...') . '</p>');

	return true;
}

/**
 * reset_password() - Handles resetting the user's password
 *
 * {@internal Missing Long Description}}
 *
 * @uses $wpdb WordPress Database object
 *
 * @param string $key Hash to validate sending user's password
 * @return bool|WP_Error
 */
function reset_password($key) {
	global $wpdb;

	$key = preg_replace('/[^a-z0-9]/i', '', $key);

	if ( empty( $key ) )
		return new WP_Error('invalid_key', __('Invalid key'));

	$user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->users WHERE user_activation_key = %s", $key));
	if ( empty( $user ) )
		return new WP_Error('invalid_key', __('Invalid key'));

	do_action('password_reset', $user);

	// Generate something random for a password...
	$new_pass = wp_generate_password();
	wp_set_password($new_pass, $user->ID);
	$message  = sprintf(__('Username: %s'), $user->user_login) . "\r\n";
	$message .= sprintf(__('Password: %s'), $new_pass) . "\r\n";
	$message .= site_url('wp-login.php', 'login') . "\r\n";

	if (  !wp_mail($user->user_email, sprintf(__('[%s] Your new password'), get_option('blogname')), $message) )
		die('<p>' . __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function...') . '</p>');

	// send a copy of password change notification to the admin
	// but check to see if it's the admin whose password we're changing, and skip this
	if ( $user->user_email != get_option('admin_email') ) {
		$message = sprintf(__('Password Lost and Changed for user: %s'), $user->user_login) . "\r\n";
		wp_mail(get_option('admin_email'), sprintf(__('[%s] Password Lost/Changed'), get_option('blogname')), $message);
	}

	return true;
}

/**
 * register_new_user() - Handles registering a new user
 *
 * {@internal Missing Long Description}}
 *
 * @param string $user_login User's username for logging in
 * @param string $user_email User's email address to send password and add
 * @return int|WP_Error Either user's ID or error on failure.
 */
function register_new_user($user_login, $user_email) {
	$errors = new WP_Error();

	$user_login = sanitize_user( $user_login );
	$user_email = apply_filters( 'user_registration_email', $user_email );

	// Check the username
	if ( $user_login == '' )
		$errors->add('empty_username', __('<strong>ERROR</strong>: Please enter a username.'));
	elseif ( !validate_username( $user_login ) ) {
		$errors->add('invalid_username', __('<strong>ERROR</strong>: This username is invalid.  Please enter a valid username.'));
		$user_login = '';
	} elseif ( username_exists( $user_login ) )
		$errors->add('username_exists', __('<strong>ERROR</strong>: This username is already registered, please choose another one.'));

	// Check the e-mail address
	if ($user_email == '') {
		$errors->add('empty_email', __('<strong>ERROR</strong>: Please type your e-mail address.'));
	} elseif ( !is_email( $user_email ) ) {
		$errors->add('invalid_email', __('<strong>ERROR</strong>: The email address isn&#8217;t correct.'));
		$user_email = '';
	} elseif ( email_exists( $user_email ) )
		$errors->add('email_exists', __('<strong>ERROR</strong>: This email is already registered, please choose another one.'));

	do_action('register_post', $user_login, $user_email, $errors);

	$errors = apply_filters( 'registration_errors', $errors );

	if ( $errors->get_error_code() )
		return $errors;

	$user_pass = wp_generate_password();
	$user_id = wp_create_user( $user_login, $user_pass, $user_email );
	if ( !$user_id ) {
		$errors->add('registerfail', sprintf(__('<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !'), get_option('admin_email')));
		return $errors;
	}

	wp_new_user_notification($user_id, $user_pass);

	return $user_id;
}

//
// Main
//

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$errors = new WP_Error();

if ( isset($_GET['key']) )
	$action = 'resetpass';

nocache_headers();

header('Content-Type: '.get_bloginfo('html_type').'; charset='.get_bloginfo('charset'));

if ( defined('RELOCATE') ) { // Move flag is set
	if ( isset( $_SERVER['PATH_INFO'] ) && ($_SERVER['PATH_INFO'] != $_SERVER['PHP_SELF']) )
		$_SERVER['PHP_SELF'] = str_replace( $_SERVER['PATH_INFO'], '', $_SERVER['PHP_SELF'] );

	$schema = ( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	if ( dirname($schema . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']) != get_option('siteurl') )
		update_option('siteurl', dirname($schema . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']) );
}

//Set a cookie now to see if they are supported by the browser.
setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
if ( SITECOOKIEPATH != COOKIEPATH )
	setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);

$http_post = ('POST' == $_SERVER['REQUEST_METHOD']);
switch ($action) {

case 'logout' :

	wp_logout();

	$redirect_to = 'wp-login.php?loggedout=true';
	if ( isset( $_REQUEST['redirect_to'] ) )
		$redirect_to = $_REQUEST['redirect_to'];

	wp_safe_redirect($redirect_to);
	exit();

break;

case 'lostpassword' :
case 'retrievepassword' :
	if ( $http_post ) {
		$errors = retrieve_password();
		if ( !is_wp_error($errors) ) {
			wp_redirect('wp-login.php?checkemail=confirm');
			exit();
		}
	}

	if ( 'invalidkey' == $_GET['error'] ) $errors->add('invalidkey', __('Sorry, that key does not appear to be valid.'));

	do_action('lost_password');
	login_header(__('Lost Password'), '<p class="message">' . __('Please enter your username or e-mail address. You will receive a new password via e-mail.') . '</p>', $errors);
?>

<form name="lostpasswordform" id="lostpasswordform" action="<?php echo site_url('wp-login.php?action=lostpassword', 'login_post') ?>" method="post">
	<p>
		<label><?php _e('Username or E-mail:') ?><br />
		<input type="text" name="user_login" id="user_login" class="input" value="<?php echo attribute_escape(stripslashes($_POST['user_login'])); ?>" size="20" tabindex="10" /></label>
	</p>
<?php do_action('lostpassword_form'); ?>
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" value="<?php _e('Get New Password'); ?>" tabindex="100" /></p>
</form>

<p id="nav">
<?php if (get_option('users_can_register')) : ?>
<a href="<?php echo site_url('wp-login.php', 'login') ?>"><?php _e('Log in') ?></a> |
<a href="<?php echo site_url('wp-login.php?action=register', 'login') ?>"><?php _e('Register') ?></a>
<?php else : ?>
<a href="<?php echo site_url('wp-login.php', 'login') ?>"><?php _e('Log in') ?></a>
<?php endif; ?>
</p>

</div>

<p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&laquo; Back to %s'), get_bloginfo('title', 'display' )); ?></a></p>

</body>
</html>
<?php
break;

case 'resetpass' :
case 'rp' :
	$errors = reset_password($_GET['key']);

	if ( ! is_wp_error($errors) ) {
		wp_redirect('wp-login.php?checkemail=newpass');
		exit();
	}

	wp_redirect('wp-login.php?action=lostpassword&error=invalidkey');
	exit();

break;

case 'register' :
	if ( !get_option('users_can_register') ) {
		wp_redirect('wp-login.php?registration=disabled');
		exit();
	}

	$user_login = '';
	$user_email = '';
	if ( $http_post ) {
		require_once( ABSPATH . WPINC . '/registration.php');

		$user_login = $_POST['user_login'];
		$user_email = $_POST['user_email'];
		$errors = register_new_user($user_login, $user_email);
		if ( !is_wp_error($errors) ) {
			wp_redirect('wp-login.php?checkemail=registered');
			exit();
		}
	}

	login_header(__('Registration Form'), '<p class="message register">' . __('Register For This Site') . '</p>', $errors);
?>

<form name="registerform" id="registerform" action="<?php echo site_url('wp-login.php?action=register', 'login_post') ?>" method="post">
	<p>
		<label><?php _e('Username') ?><br />
		<input type="text" name="user_login" id="user_login" class="input" value="<?php echo attribute_escape(stripslashes($user_login)); ?>" size="20" tabindex="10" /></label>
	</p>
	<p>
		<label><?php _e('E-mail') ?><br />
		<input type="text" name="user_email" id="user_email" class="input" value="<?php echo attribute_escape(stripslashes($user_email)); ?>" size="25" tabindex="20" /></label>
	</p>
<?php do_action('register_form'); ?>
	<p id="reg_passmail"><?php _e('A password will be e-mailed to you.') ?></p>
	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" value="<?php _e('Register'); ?>" tabindex="100" /></p>
</form>

<p id="nav">
<a href="<?php echo site_url('wp-login.php', 'login') ?>"><?php _e('Log in') ?></a> |
<a href="<?php echo site_url('wp-login.php?action=lostpassword', 'login') ?>" title="<?php _e('Password Lost and Found') ?>"><?php _e('Lost your password?') ?></a>
</p>

</div>

<p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&laquo; Back to %s'), get_bloginfo('title', 'display' )); ?></a></p>

</body>
</html>
<?php
break;

case 'login' :
default:
	if ( isset( $_REQUEST['redirect_to'] ) )
		$redirect_to = $_REQUEST['redirect_to'];
	else
		$redirect_to = admin_url();

	if ( is_ssl() && force_ssl_login() && !force_ssl_admin() && ( 0 !== strpos($redirect_to, 'https') ) && ( 0 === strpos($redirect_to, 'http') ) )
		$secure_cookie = false;
	else
		$secure_cookie = '';

	$user = wp_signon('', $secure_cookie);

	if ( !is_wp_error($user) ) {
		// If the user can't edit posts, send them to their profile.
		if ( !$user->has_cap('edit_posts') && ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' ) )
			$redirect_to = admin_url('profile.php');
		wp_safe_redirect($redirect_to);
		exit();
	}

	$errors = $user;
	// Clear errors if loggedout is set.
	if ( !empty($_GET['loggedout']) )
		$errors = new WP_Error();

	// If cookies are disabled we can't log in even with a valid user+pass
	if ( isset($_POST['testcookie']) && empty($_COOKIE[TEST_COOKIE]) )
		$errors->add('test_cookie', __("<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href='http://www.google.com/cookies.html'>enable cookies</a> to use WordPress."));

	// Some parts of this script use the main login form to display a message
	if		( isset($_GET['loggedout']) && TRUE == $_GET['loggedout'] )			$errors->add('loggedout', __('You are now logged out.'), 'message');
	elseif	( isset($_GET['registration']) && 'disabled' == $_GET['registration'] )	$errors->add('registerdiabled', __('User registration is currently not allowed.'));
	elseif	( isset($_GET['checkemail']) && 'confirm' == $_GET['checkemail'] )	$errors->add('confirm', __('Check your e-mail for the confirmation link.'), 'message');
	elseif	( isset($_GET['checkemail']) && 'newpass' == $_GET['checkemail'] )	$errors->add('newpass', __('Check your e-mail for your new password.'), 'message');
	elseif	( isset($_GET['checkemail']) && 'registered' == $_GET['checkemail'] )	$errors->add('registered', __('Registration complete. Please check your e-mail.'), 'message');

	login_header(__('Login'), '', $errors);
?>

<form name="loginform" id="loginform" action="<?php echo site_url('wp-login.php', 'login_post') ?>" method="post">
<?php if ( !isset($_GET['checkemail']) || !in_array( $_GET['checkemail'], array('confirm', 'newpass') ) ) : ?>
	<p>
		<label><?php _e('Username') ?><br />
		<input type="text" name="log" id="user_login" class="input" value="<?php echo attribute_escape(stripslashes($user_login)); ?>" size="20" tabindex="10" /></label>
	</p>
	<p>
		<label><?php _e('Password') ?><br />
		<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" tabindex="20" /></label>
	</p>
<?php do_action('login_form'); ?>
	<p class="forgetmenot"><label><input name="rememberme" type="checkbox" id="rememberme" value="forever" tabindex="90" /> <?php _e('Remember Me'); ?></label></p>
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" value="<?php _e('Log In'); ?>" tabindex="100" />
		<input type="hidden" name="redirect_to" value="<?php echo attribute_escape($redirect_to); ?>" />
		<input type="hidden" name="testcookie" value="1" />
	</p>
<?php else : ?>
	<p>&nbsp;</p>
<?php endif; ?>
</form>

<p id="nav">
<?php if ( isset($_GET['checkemail']) && in_array( $_GET['checkemail'], array('confirm', 'newpass') ) ) : ?>
<?php elseif (get_option('users_can_register')) : ?>
<a href="<?php echo site_url('wp-login.php?action=register', 'login') ?>"><?php _e('Register') ?></a> |
<a href="<?php echo site_url('wp-login.php?action=lostpassword', 'login') ?>" title="<?php _e('Password Lost and Found') ?>"><?php _e('Lost your password?') ?></a>
<?php else : ?>
<a href="<?php echo site_url('wp-login.php?action=lostpassword', 'login') ?>" title="<?php _e('Password Lost and Found') ?>"><?php _e('Lost your password?') ?></a>
<?php endif; ?>
</p>

</div>

<p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&laquo; Back to %s'), get_bloginfo('title', 'display' )); ?></a></p>

</body>
</html>
<?php

break;
} // end action switch
?>
