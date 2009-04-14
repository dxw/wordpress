<?php
/**
 * XML-RPC protocol support for WordPress
 *
 * @license GPL v2 <./license.txt>
 * @package WordPress
 */

/**
 * Whether this is a XMLRPC Request
 *
 * @var bool
 */
define('XMLRPC_REQUEST', true);

// Some browser-embedded clients send cookies. We don't want them.
$_COOKIE = array();

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

// fix for mozBlog and other cases where '<?xml' isn't on the very first line
if ( isset($HTTP_RAW_POST_DATA) )
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);

/** Include the bootstrap for setting up WordPress environment */
include('./wp-load.php');

if ( isset( $_GET['rsd'] ) ) { // http://archipelago.phrasewise.com/rsd
header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
?>
<?php echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
  <service>
    <engineName>WordPress</engineName>
    <engineLink>http://wordpress.org/</engineLink>
    <homePageLink><?php bloginfo_rss('url') ?></homePageLink>
    <apis>
      <api name="WordPress" blogID="1" preferred="true" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Movable Type" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="MetaWeblog" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Blogger" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Atom" blogID="" preferred="false" apiLink="<?php echo apply_filters('atom_service_url', site_url('wp-app.php/service') ) ?>" />
    </apis>
  </service>
</rsd>
<?php
exit;
}

include_once(ABSPATH . 'wp-admin/includes/admin.php');
include_once(ABSPATH . WPINC . '/class-IXR.php');

// Turn off all warnings and errors.
// error_reporting(0);

/**
 * Posts submitted via the xmlrpc interface get that title
 * @name post_default_title
 * @var string
 */
$post_default_title = "";

/**
 * Whether to enable XMLRPC Logging.
 *
 * @name xmlrpc_logging
 * @var int|bool
 */
$xmlrpc_logging = 0;

/**
 * logIO() - Writes logging info to a file.
 *
 * @uses $xmlrpc_logging
 * @package WordPress
 * @subpackage Logging
 *
 * @param string $io Whether input or output
 * @param string $msg Information describing logging reason.
 * @return bool Always return true
 */
function logIO($io,$msg) {
	global $xmlrpc_logging;
	if ($xmlrpc_logging) {
		$fp = fopen("../xmlrpc.log","a+");
		$date = gmdate("Y-m-d H:i:s ");
		$iot = ($io == "I") ? " Input: " : " Output: ";
		fwrite($fp, "\n\n".$date.$iot.$msg);
		fclose($fp);
	}
	return true;
}

if ( isset($HTTP_RAW_POST_DATA) )
	logIO("I", $HTTP_RAW_POST_DATA);

/**
 * WordPress XMLRPC server implementation.
 *
 * Implements compatability for Blogger API, MetaWeblog API, MovableType, and
 * pingback. Additional WordPress API for managing comments, pages, posts,
 * options, etc.
 *
 * Since WordPress 2.6.0, WordPress XMLRPC server can be disabled in the
 * administration panels.
 *
 * @package WordPress
 * @subpackage Publishing
 * @since 1.5.0
 */
class wp_xmlrpc_server extends IXR_Server {

	/**
	 * Register all of the XMLRPC methods that XMLRPC server understands.
	 *
	 * PHP4 constructor and sets up server and method property. Passes XMLRPC
	 * methods through the 'xmlrpc_methods' filter to allow plugins to extend
	 * or replace XMLRPC methods.
	 *
	 * @since 1.5.0
	 *
	 * @return wp_xmlrpc_server
	 */
	function wp_xmlrpc_server() {
		$this->methods = array(
			// WordPress API
			'wp.getUsersBlogs'		=> 'this:wp_getUsersBlogs',
			'wp.getPage'			=> 'this:wp_getPage',
			'wp.getPages'			=> 'this:wp_getPages',
			'wp.newPage'			=> 'this:wp_newPage',
			'wp.deletePage'			=> 'this:wp_deletePage',
			'wp.editPage'			=> 'this:wp_editPage',
			'wp.getPageList'		=> 'this:wp_getPageList',
			'wp.getAuthors'			=> 'this:wp_getAuthors',
			'wp.getCategories'		=> 'this:mw_getCategories',		// Alias
			'wp.getTags'			=> 'this:wp_getTags',
			'wp.newCategory'		=> 'this:wp_newCategory',
			'wp.deleteCategory'		=> 'this:wp_deleteCategory',
			'wp.suggestCategories'	=> 'this:wp_suggestCategories',
			'wp.uploadFile'			=> 'this:mw_newMediaObject',	// Alias
			'wp.getCommentCount'	=> 'this:wp_getCommentCount',
			'wp.getPostStatusList'	=> 'this:wp_getPostStatusList',
			'wp.getPageStatusList'	=> 'this:wp_getPageStatusList',
			'wp.getPageTemplates'	=> 'this:wp_getPageTemplates',
			'wp.getOptions'			=> 'this:wp_getOptions',
			'wp.setOptions'			=> 'this:wp_setOptions',
			'wp.getComment'			=> 'this:wp_getComment',
			'wp.getComments'		=> 'this:wp_getComments',
			'wp.deleteComment'		=> 'this:wp_deleteComment',
			'wp.editComment'		=> 'this:wp_editComment',
			'wp.newComment'			=> 'this:wp_newComment',
			'wp.getCommentStatusList' => 'this:wp_getCommentStatusList',

			// Blogger API
			'blogger.getUsersBlogs' => 'this:blogger_getUsersBlogs',
			'blogger.getUserInfo' => 'this:blogger_getUserInfo',
			'blogger.getPost' => 'this:blogger_getPost',
			'blogger.getRecentPosts' => 'this:blogger_getRecentPosts',
			'blogger.getTemplate' => 'this:blogger_getTemplate',
			'blogger.setTemplate' => 'this:blogger_setTemplate',
			'blogger.newPost' => 'this:blogger_newPost',
			'blogger.editPost' => 'this:blogger_editPost',
			'blogger.deletePost' => 'this:blogger_deletePost',

			// MetaWeblog API (with MT extensions to structs)
			'metaWeblog.newPost' => 'this:mw_newPost',
			'metaWeblog.editPost' => 'this:mw_editPost',
			'metaWeblog.getPost' => 'this:mw_getPost',
			'metaWeblog.getRecentPosts' => 'this:mw_getRecentPosts',
			'metaWeblog.getCategories' => 'this:mw_getCategories',
			'metaWeblog.newMediaObject' => 'this:mw_newMediaObject',

			// MetaWeblog API aliases for Blogger API
			// see http://www.xmlrpc.com/stories/storyReader$2460
			'metaWeblog.deletePost' => 'this:blogger_deletePost',
			'metaWeblog.getTemplate' => 'this:blogger_getTemplate',
			'metaWeblog.setTemplate' => 'this:blogger_setTemplate',
			'metaWeblog.getUsersBlogs' => 'this:blogger_getUsersBlogs',

			// MovableType API
			'mt.getCategoryList' => 'this:mt_getCategoryList',
			'mt.getRecentPostTitles' => 'this:mt_getRecentPostTitles',
			'mt.getPostCategories' => 'this:mt_getPostCategories',
			'mt.setPostCategories' => 'this:mt_setPostCategories',
			'mt.supportedMethods' => 'this:mt_supportedMethods',
			'mt.supportedTextFilters' => 'this:mt_supportedTextFilters',
			'mt.getTrackbackPings' => 'this:mt_getTrackbackPings',
			'mt.publishPost' => 'this:mt_publishPost',

			// PingBack
			'pingback.ping' => 'this:pingback_ping',
			'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks',

			'demo.sayHello' => 'this:sayHello',
			'demo.addTwoNumbers' => 'this:addTwoNumbers'
		);

		$this->initialise_blog_option_info( );
		$this->methods = apply_filters('xmlrpc_methods', $this->methods);
		$this->IXR_Server($this->methods);
	}

	/**
	 * Test XMLRPC API by saying, "Hello!" to client.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method Parameters.
	 * @return string
	 */
	function sayHello($args) {
		return 'Hello!';
	}

	/**
	 * Test XMLRPC API by adding two numbers for client.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method Parameters.
	 * @return int
	 */
	function addTwoNumbers($args) {
		$number1 = $args[0];
		$number2 = $args[1];
		return $number1 + $number2;
	}

	/**
	 * Check user's credentials.
	 *
	 * @since 1.5.0
	 *
	 * @param string $user_login User's username.
	 * @param string $user_pass User's password.
	 * @return bool Whether authentication passed.
	 */
	function login_pass_ok($user_login, $user_pass) {
		if ( !get_option( 'enable_xmlrpc' ) ) {
			$this->error = new IXR_Error( 405, sprintf( __( 'XML-RPC services are disabled on this blog.  An admin user can enable them at %s'),  admin_url('options-writing.php') ) );
			return false;
		}

		if (!user_pass_ok($user_login, $user_pass)) {
			$this->error = new IXR_Error(403, __('Bad login/pass combination.'));
			return false;
		}
		return true;
	}

	/**
	 * Sanitize string or array of strings for database.
	 *
	 * @since 1.5.2
	 *
	 * @param string|array $array Sanitize single string or array of strings.
	 * @return string|array Type matches $array and sanitized for the database.
	 */
	function escape(&$array) {
		global $wpdb;

		if(!is_array($array)) {
			return($wpdb->escape($array));
		}
		else {
			foreach ( (array) $array as $k => $v ) {
				if (is_array($v)) {
					$this->escape($array[$k]);
				} else if (is_object($v)) {
					//skip
				} else {
					$array[$k] = $wpdb->escape($v);
				}
			}
		}
	}

	/**
	 * Retrieve custom fields for post.
	 *
	 * @since 2.5.0
	 *
	 * @param int $post_id Post ID.
	 * @return array Custom fields, if exist.
	 */
	function get_custom_fields($post_id) {
		$post_id = (int) $post_id;

		$custom_fields = array();

		foreach ( (array) has_meta($post_id) as $meta ) {
			// Don't expose protected fields.
			if ( strpos($meta['meta_key'], '_wp_') === 0 ) {
				continue;
			}

			$custom_fields[] = array(
				"id"    => $meta['meta_id'],
				"key"   => $meta['meta_key'],
				"value" => $meta['meta_value']
			);
		}

		return $custom_fields;
	}

	/**
	 * Set custom fields for post.
	 *
	 * @since 2.5.0
	 *
	 * @param int $post_id Post ID.
	 * @param array $fields Custom fields.
	 */
	function set_custom_fields($post_id, $fields) {
		$post_id = (int) $post_id;

		foreach ( (array) $fields as $meta ) {
			if ( isset($meta['id']) ) {
				$meta['id'] = (int) $meta['id'];

				if ( isset($meta['key']) ) {
					update_meta($meta['id'], $meta['key'], $meta['value']);
				}
				else {
					delete_meta($meta['id']);
				}
			}
			else {
				$_POST['metakeyinput'] = $meta['key'];
				$_POST['metavalue'] = $meta['value'];
				add_meta($post_id);
			}
		}
	}

	/**
	 * Setup blog options property.
	 *
	 * Passes property through 'xmlrpc_blog_options' filter.
	 *
	 * @since 2.6.0
	 */
	function initialise_blog_option_info( ) {
		global $wp_version;

		$this->blog_options = array(
			// Read only options
			'software_name'		=> array(
				'desc'			=> __( 'Software Name' ),
				'readonly'		=> true,
				'value'			=> 'WordPress'
			),
			'software_version'	=> array(
				'desc'			=> __( 'Software Version' ),
				'readonly'		=> true,
				'value'			=> $wp_version
			),
			'blog_url'			=> array(
				'desc'			=> __( 'Blog URL' ),
				'readonly'		=> true,
				'option'		=> 'siteurl'
			),

			// Updatable options
			'time_zone'			=> array(
				'desc'			=> __( 'Time Zone' ),
				'readonly'		=> false,
				'option'		=> 'gmt_offset'
			),
			'blog_title'		=> array(
				'desc'			=> __( 'Blog Title' ),
				'readonly'		=> false,
				'option'			=> 'blogname'
			),
			'blog_tagline'		=> array(
				'desc'			=> __( 'Blog Tagline' ),
				'readonly'		=> false,
				'option'		=> 'blogdescription'
			),
			'date_format'		=> array(
				'desc'			=> __( 'Date Format' ),
				'readonly'		=> false,
				'option'		=> 'date_format'
			),
			'time_format'		=> array(
				'desc'			=> __( 'Time Format' ),
				'readonly'		=> false,
				'option'		=> 'time_format'
			)
		);

		$this->blog_options = apply_filters( 'xmlrpc_blog_options', $this->blog_options );
	}

	/**
	 * Retrieve the blogs of the user.
	 *
	 * @since 2.6.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getUsersBlogs( $args ) {
		// If this isn't on WPMU then just use blogger_getUsersBlogs
		if( !function_exists( 'is_site_admin' ) ) {
			array_unshift( $args, 1 );
			return $this->blogger_getUsersBlogs( $args );
		}

		$this->escape( $args );

		$username = $args[0];
		$password = $args[1];

		if( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getUsersBlogs' );

		$user = set_current_user( 0, $username );

		$blogs = (array) get_blogs_of_user( $user->ID );
		$struct = array( );

		foreach( $blogs as $blog ) {
			// Don't include blogs that aren't hosted at this site
			if( $blog->site_id != $current_site->id )
				continue;

			$blog_id = $blog->userblog_id;
			switch_to_blog($blog_id);
			$is_admin = current_user_can('level_8');

			$struct[] = array(
				'isAdmin'		=> $is_admin,
				'url'			=> get_option( 'home' ) . '/',
				'blogid'		=> $blog_id,
				'blogName'		=> get_option( 'blogname' ),
				'xmlrpc'		=> site_url( 'xmlrpc.php' )
			);

			restore_current_blog( );
		}

		return $struct;
	}

	/**
	 * Retrieve page.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getPage($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$page_id	= (int) $args[1];
		$username	= $args[2];
		$password	= $args[3];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_page', $page_id ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit this page.' ) );

		do_action('xmlrpc_call', 'wp.getPage');

		// Lookup page info.
		$page = get_page($page_id);

		// If we found the page then format the data.
		if($page->ID && ($page->post_type == "page")) {
			// Get all of the page content and link.
			$full_page = get_extended($page->post_content);
			$link = post_permalink($page->ID);

			// Get info the page parent if there is one.
			$parent_title = "";
			if(!empty($page->post_parent)) {
				$parent = get_page($page->post_parent);
				$parent_title = $parent->post_title;
			}

			// Determine comment and ping settings.
			$allow_comments = ("open" == $page->comment_status) ? 1 : 0;
			$allow_pings = ("open" == $page->ping_status) ? 1 : 0;

			// Format page date.
			$page_date = mysql2date("Ymd\TH:i:s", $page->post_date);
			$page_date_gmt = mysql2date("Ymd\TH:i:s", $page->post_date_gmt);

			// Pull the categories info together.
			$categories = array();
			foreach(wp_get_post_categories($page->ID) as $cat_id) {
				$categories[] = get_cat_name($cat_id);
			}

			// Get the author info.
			$author = get_userdata($page->post_author);

			$page_template = get_post_meta( $page->ID, '_wp_page_template', true );
			if( empty( $page_template ) )
				$page_template = 'default';

			$page_struct = array(
				"dateCreated"			=> new IXR_Date($page_date),
				"userid"				=> $page->post_author,
				"page_id"				=> $page->ID,
				"page_status"			=> $page->post_status,
				"description"			=> $full_page["main"],
				"title"					=> $page->post_title,
				"link"					=> $link,
				"permaLink"				=> $link,
				"categories"			=> $categories,
				"excerpt"				=> $page->post_excerpt,
				"text_more"				=> $full_page["extended"],
				"mt_allow_comments"		=> $allow_comments,
				"mt_allow_pings"		=> $allow_pings,
				"wp_slug"				=> $page->post_name,
				"wp_password"			=> $page->post_password,
				"wp_author"				=> $author->display_name,
				"wp_page_parent_id"		=> $page->post_parent,
				"wp_page_parent_title"	=> $parent_title,
				"wp_page_order"			=> $page->menu_order,
				"wp_author_id"			=> $author->ID,
				"wp_author_display_name"	=> $author->display_name,
				"date_created_gmt"		=> new IXR_Date($page_date_gmt),
				"custom_fields"			=> $this->get_custom_fields($page_id),
				"wp_page_template"		=> $page_template
			);

			return($page_struct);
		}
		// If the page doesn't exist indicate that.
		else {
			return(new IXR_Error(404, __("Sorry, no such page.")));
		}
	}

	/**
	 * Retrieve Pages.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getPages($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$num_pages	= (int) $args[3];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_pages' ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit pages.' ) );

		do_action('xmlrpc_call', 'wp.getPages');

		$page_limit = 10;
		if( isset( $num_pages ) ) {
			$page_limit = $num_pages;
		}

		$pages = get_posts( "post_type=page&post_status=all&numberposts={$page_limit}" );
		$num_pages = count($pages);

		// If we have pages, put together their info.
		if($num_pages >= 1) {
			$pages_struct = array();

			for($i = 0; $i < $num_pages; $i++) {
				$page = wp_xmlrpc_server::wp_getPage(array(
					$blog_id, $pages[$i]->ID, $username, $password
				));
				$pages_struct[] = $page;
			}

			return($pages_struct);
		}
		// If no pages were found return an error.
		else {
			return(array());
		}
	}

	/**
	 * Create new page.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return unknown
	 */
	function wp_newPage($args) {
		// Items not escaped here will be escaped in newPost.
		$username	= $this->escape($args[1]);
		$password	= $this->escape($args[2]);
		$page		= $args[3];
		$publish	= $args[4];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		do_action('xmlrpc_call', 'wp.newPage');

		// Set the user context and check if they are allowed
		// to add new pages.
		$user = set_current_user(0, $username);
		if(!current_user_can("publish_pages")) {
			return(new IXR_Error(401, __("Sorry, you can not add new pages.")));
		}

		// Mark this as content for a page.
		$args[3]["post_type"] = "page";

		// Let mw_newPost do all of the heavy lifting.
		return($this->mw_newPost($args));
	}

	/**
	 * Delete page.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True, if success.
	 */
	function wp_deletePage($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$page_id	= (int) $args[3];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		do_action('xmlrpc_call', 'wp.deletePage');

		// Get the current page based on the page_id and
		// make sure it is a page and not a post.
		$actual_page = wp_get_single_post($page_id, ARRAY_A);
		if(
			!$actual_page
			|| ($actual_page["post_type"] != "page")
		) {
			return(new IXR_Error(404, __("Sorry, no such page.")));
		}

		// Set the user context and make sure they can delete pages.
		set_current_user(0, $username);
		if(!current_user_can("delete_page", $page_id)) {
			return(new IXR_Error(401, __("Sorry, you do not have the right to delete this page.")));
		}

		// Attempt to delete the page.
		$result = wp_delete_post($page_id);
		if(!$result) {
			return(new IXR_Error(500, __("Failed to delete the page.")));
		}

		return(true);
	}

	/**
	 * Edit page.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return unknown
	 */
	function wp_editPage($args) {
		// Items not escaped here will be escaped in editPost.
		$blog_id	= (int) $args[0];
		$page_id	= (int) $this->escape($args[1]);
		$username	= $this->escape($args[2]);
		$password	= $this->escape($args[3]);
		$content	= $args[4];
		$publish	= $args[5];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		do_action('xmlrpc_call', 'wp.editPage');

		// Get the page data and make sure it is a page.
		$actual_page = wp_get_single_post($page_id, ARRAY_A);
		if(
			!$actual_page
			|| ($actual_page["post_type"] != "page")
		) {
			return(new IXR_Error(404, __("Sorry, no such page.")));
		}

		// Set the user context and make sure they are allowed to edit pages.
		set_current_user(0, $username);
		if(!current_user_can("edit_page", $page_id)) {
			return(new IXR_Error(401, __("Sorry, you do not have the right to edit this page.")));
		}

		// Mark this as content for a page.
		$content["post_type"] = "page";

		// Arrange args in the way mw_editPost understands.
		$args = array(
			$page_id,
			$username,
			$password,
			$content,
			$publish
		);

		// Let mw_editPost do all of the heavy lifting.
		return($this->mw_editPost($args));
	}

	/**
	 * Retrieve page list.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return unknown
	 */
	function wp_getPageList($args) {
		global $wpdb;

		$this->escape($args);

		$blog_id				= (int) $args[0];
		$username				= $args[1];
		$password				= $args[2];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_pages' ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit pages.' ) );

		do_action('xmlrpc_call', 'wp.getPageList');

		// Get list of pages ids and titles
		$page_list = $wpdb->get_results("
			SELECT ID page_id,
				post_title page_title,
				post_parent page_parent_id,
				post_date_gmt,
				post_date
			FROM {$wpdb->posts}
			WHERE post_type = 'page'
			ORDER BY ID
		");

		// The date needs to be formated properly.
		$num_pages = count($page_list);
		for($i = 0; $i < $num_pages; $i++) {
			$post_date = mysql2date("Ymd\TH:i:s", $page_list[$i]->post_date);
			$post_date_gmt = mysql2date("Ymd\TH:i:s", $page_list[$i]->post_date_gmt);

			$page_list[$i]->dateCreated = new IXR_Date($post_date);
			$page_list[$i]->date_created_gmt = new IXR_Date($post_date_gmt);

			unset($page_list[$i]->post_date_gmt);
			unset($page_list[$i]->post_date);
		}

		return($page_list);
	}

	/**
	 * Retrieve authors list.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getAuthors($args) {

		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		set_current_user(0, $username);
		if(!current_user_can("edit_posts")) {
			return(new IXR_Error(401, __("Sorry, you can not edit posts on this blog.")));
		}

		do_action('xmlrpc_call', 'wp.getAuthors');

		$authors = array();
		foreach( (array) get_users_of_blog() as $row ) {
			$authors[] = array(
				"user_id"       => $row->user_id,
				"user_login"    => $row->user_login,
				"display_name"  => $row->display_name
			);
		}

		return($authors);
	}

	/**
	 * Get list of all tags
	 *
	 * @since 2.7
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getTags( $args ) {
		$this->escape( $args );

		$blog_id		= (int) $args[0];
		$username		= $args[1];
		$password		= $args[2];

		if( !$this->login_pass_ok( $username, $password ) ) {
			return $this->error;
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_posts' ) ) {
			return new IXR_Error( 401, __( 'Sorry, you must be able to edit posts on this blog in order to view tags.' ) );
		}

		do_action( 'xmlrpc_call', 'wp.getKeywords' );

		$tags = array( );

		if( $all_tags = get_tags( ) ) {
			foreach( (array) $all_tags as $tag ) {
				$struct['tag_id']			= $tag->term_id;
				$struct['name']				= $tag->name;
				$struct['count']			= $tag->count;
				$struct['slug']				= $tag->slug;
				$struct['html_url']			= wp_specialchars( get_tag_link( $tag->term_id ) );
				$struct['rss_url']			= wp_specialchars( get_tag_feed_link( $tag->term_id ) );

				$tags[] = $struct;
			}
		}

		return $tags;
	}

	/**
	 * Create new category.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return int Category ID.
	 */
	function wp_newCategory($args) {
		$this->escape($args);

		$blog_id				= (int) $args[0];
		$username				= $args[1];
		$password				= $args[2];
		$category				= $args[3];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		do_action('xmlrpc_call', 'wp.newCategory');

		// Set the user context and make sure they are
		// allowed to add a category.
		set_current_user(0, $username);
		if(!current_user_can("manage_categories")) {
			return(new IXR_Error(401, __("Sorry, you do not have the right to add a category.")));
		}

		// If no slug was provided make it empty so that
		// WordPress will generate one.
		if(empty($category["slug"])) {
			$category["slug"] = "";
		}

		// If no parent_id was provided make it empty
		// so that it will be a top level page (no parent).
		if ( !isset($category["parent_id"]) )
			$category["parent_id"] = "";

		// If no description was provided make it empty.
		if(empty($category["description"])) {
			$category["description"] = "";
		}

		$new_category = array(
			"cat_name"				=> $category["name"],
			"category_nicename"		=> $category["slug"],
			"category_parent"		=> $category["parent_id"],
			"category_description"	=> $category["description"]
		);

		$cat_id = wp_insert_category($new_category);
		if(!$cat_id) {
			return(new IXR_Error(500, __("Sorry, the new category failed.")));
		}

		return($cat_id);
	}

	/**
	 * Remove category.
	 *
	 * @since 2.5.0
	 *
	 * @param array $args Method parameters.
	 * @return mixed See {@link wp_delete_category()} for return info.
	 */
	function wp_deleteCategory($args) {
		$this->escape($args);

		$blog_id		= (int) $args[0];
		$username		= $args[1];
		$password		= $args[2];
		$category_id	= (int) $args[3];

		if( !$this->login_pass_ok( $username, $password ) ) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'wp.deleteCategory');

		set_current_user(0, $username);
		if( !current_user_can("manage_categories") ) {
			return new IXR_Error( 401, __( "Sorry, you do not have the right to delete a category." ) );
		}

		return wp_delete_category( $category_id );
	}

	/**
	 * Retrieve category list.
	 *
	 * @since 2.2.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_suggestCategories($args) {
		$this->escape($args);

		$blog_id				= (int) $args[0];
		$username				= $args[1];
		$password				= $args[2];
		$category				= $args[3];
		$max_results			= (int) $args[4];

		if(!$this->login_pass_ok($username, $password)) {
			return($this->error);
		}

		set_current_user(0, $username);
		if( !current_user_can( 'edit_posts' ) )
			return new IXR_Error( 401, __( 'Sorry, you must be able to edit posts to this blog in order to view categories.' ) );

		do_action('xmlrpc_call', 'wp.suggestCategories');

		$category_suggestions = array();
		$args = array('get' => 'all', 'number' => $max_results, 'name__like' => $category);
		foreach ( (array) get_categories($args) as $cat ) {
			$category_suggestions[] = array(
				"category_id"	=> $cat->cat_ID,
				"category_name"	=> $cat->cat_name
			);
		}

		return($category_suggestions);
	}

	/**
	 * Retrieve comment.
	 *
	 * @since 2.7.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getComment($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$comment_id	= (int) $args[3];

		if ( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		set_current_user( 0, $username );
		if ( !current_user_can( 'moderate_comments' ) )
			return new IXR_Error( 403, __( 'You are not allowed to moderate comments on this blog.' ) );

		do_action('xmlrpc_call', 'wp.getComment');

		if ( ! $comment = get_comment($comment_id) )
			return new IXR_Error( 404, __( 'Invalid comment ID.' ) );

		// Format page date.
		$comment_date = mysql2date("Ymd\TH:i:s", $comment->comment_date);
		$comment_date_gmt = mysql2date("Ymd\TH:i:s", $comment->comment_date_gmt);

		if ( 0 == $comment->comment_approved )
			$comment_status = 'hold';
		else if ( 'spam' == $comment->comment_approved )
			$comment_status = 'spam';
		else if ( 1 == $comment->comment_approved )
			$comment_status = 'approve';
		else
			$comment_status = $comment->comment_approved;

		$link = get_comment_link($comment);

		$comment_struct = array(
			"date_created_gmt"		=> new IXR_Date($comment_date_gmt),
			"user_id"				=> $comment->user_id,
			"comment_id"			=> $comment->comment_ID,
			"parent"				=> $comment->comment_parent,
			"status"				=> $comment_status,
			"content"				=> $comment->comment_content,
			"link"					=> $link,
			"post_id"				=> $comment->comment_post_ID,
			"post_title"			=> get_the_title($comment->comment_post_ID),
			"author"				=> $comment->comment_author,
			"author_url"			=> $comment->comment_author_url,
			"author_email"			=> $comment->comment_author_email,
			"author_ip"				=> $comment->comment_author_IP,
			"type"					=> $comment->comment_type,
		);

		return $comment_struct;
	}

	/**
	 * Retrieve comments.
	 *
	 * @since 2.7.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getComments($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$struct		= $args[3];

		if ( !$this->login_pass_ok($username, $password) )
			return($this->error);

		set_current_user( 0, $username );
		if ( !current_user_can( 'moderate_comments' ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit comments.' ) );

		do_action('xmlrpc_call', 'wp.getComments');

		if ( isset($struct['status']) )
			$status = $struct['status'];
		else
			$status = '';

		$post_id = '';
		if ( isset($struct['post_id']) )
			$post_id = absint($struct['post_id']);

		$offset = 0;
		if ( isset($struct['offset']) )
			$offset = absint($struct['offset']);

		$number = 10;
		if ( isset($struct['number']) )
			$number = absint($struct['number']);

		$comments = get_comments( array('status' => $status, 'post_id' => $post_id, 'offset' => $offset, 'number' => $number ) );
		$num_comments = count($comments);

		if ( ! $num_comments )
			return array();

		$comments_struct = array();

		for ( $i = 0; $i < $num_comments; $i++ ) {
			$comment = wp_xmlrpc_server::wp_getComment(array(
				$blog_id, $username, $password, $comments[$i]->comment_ID,
			));
			$comments_struct[] = $comment;
		}

		return $comments_struct;
	}

	/**
	 * Remove comment.
	 *
	 * @since 2.7.0
	 *
	 * @param array $args Method parameters.
	 * @return mixed {@link wp_delete_comment()}
	 */
	function wp_deleteComment($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$comment_ID	= (int) $args[3];

		if ( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		set_current_user( 0, $username );
		if ( !current_user_can( 'moderate_comments' ) )
			return new IXR_Error( 403, __( 'You are not allowed to moderate comments on this blog.' ) );

		do_action('xmlrpc_call', 'wp.deleteComment');

		if ( ! get_comment($comment_ID) )
			return new IXR_Error( 404, __( 'Invalid comment ID.' ) );

		return wp_delete_comment($comment_ID);
	}

	/**
	 * Edit comment.
	 *
	 * @since 2.7.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True, on success.
	 */
	function wp_editComment($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$comment_ID	= (int) $args[3];
		$content_struct = $args[4];

		if ( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		set_current_user( 0, $username );
		if ( !current_user_can( 'moderate_comments' ) )
			return new IXR_Error( 403, __( 'You are not allowed to moderate comments on this blog.' ) );

		do_action('xmlrpc_call', 'wp.editComment');

		if ( ! get_comment($comment_ID) )
			return new IXR_Error( 404, __( 'Invalid comment ID.' ) );

		if ( isset($content_struct['status']) ) {
			$statuses = get_comment_statuses();
			$statuses = array_keys($statuses);

			if ( ! in_array($content_struct['status'], $statuses) )
				return new IXR_Error( 401, __( 'Invalid comment status.' ) );
			$comment_approved = $content_struct['status'];
		}

		// Do some timestamp voodoo
		if ( !empty( $content_struct['date_created_gmt'] ) ) {
			$dateCreated = str_replace( 'Z', '', $content_struct['date_created_gmt']->getIso() ) . 'Z'; // We know this is supposed to be GMT, so we're going to slap that Z on there by force
			$comment_date = get_date_from_gmt(iso8601_to_datetime($dateCreated));
			$comment_date_gmt = iso8601_to_datetime($dateCreated, GMT);
		}

		if ( isset($content_struct['content']) )
			$comment_content = $content_struct['content'];

		if ( isset($content_struct['author']) )
			$comment_author = $content_struct['author'];

		if ( isset($content_struct['author_url']) )
			$comment_author_url = $content_struct['author_url'];

		if ( isset($content_struct['author_email']) )
			$comment_author_email = $content_struct['author_email'];

		// We've got all the data -- post it:
		$comment = compact('comment_ID', 'comment_content', 'comment_approved', 'comment_date', 'comment_date_gmt', 'comment_author', 'comment_author_email', 'comment_author_url');

		$result = wp_update_comment($comment);
		if ( is_wp_error( $result ) )
			return new IXR_Error(500, $result->get_error_message());

		if ( !$result )
			return new IXR_Error(500, __('Sorry, the comment could not be edited. Something wrong happened.'));

		return true;
	}

	/**
	 * Create new comment.
	 *
	 * @since 2.7.0
	 *
	 * @param array $args Method parameters.
	 * @return mixed {@link wp_new_comment()}
	 */
	function wp_newComment($args) {
		global $wpdb;

		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$post		= $args[3];
		$content_struct = $args[4];

		$allow_anon = apply_filters('xmlrpc_allow_anonymous_comments', false);

		if ( !$this->login_pass_ok( $username, $password ) ) {
			$logged_in = false;
			if ( $allow_anon && get_option('comment_registration') )
				return new IXR_Error( 403, __( 'You must be registered to comment' ) );
			else if ( !$allow_anon )
				return $this->error;
		} else {
			$logged_in = true;
			set_current_user( 0, $username );
		}

		if ( is_numeric($post) )
			$post_id = absint($post);
		else
			$post_id = url_to_postid($post);

		if ( ! $post_id )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		if ( ! get_post($post_id) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$comment['comment_post_ID'] = $post_id;

		if ( $logged_in ) {
			$user = wp_get_current_user();
			$comment['comment_author'] = $wpdb->escape( $user->display_name );
			$comment['comment_author_email'] = $wpdb->escape( $user->user_email );
			$comment['comment_author_url'] = $wpdb->escape( $user->user_url );
			$comment['user_ID'] = $user->ID;
		} else {
			$comment['comment_author'] = '';
			if ( isset($content_struct['author']) )
				$comment['comment_author'] = $content_struct['author'];

			$comment['comment_author_email'] = '';
			if ( isset($content_struct['author_email']) )
				$comment['comment_author_email'] = $content_struct['author_email'];

			$comment['comment_author_url'] = '';
			if ( isset($content_struct['author_url']) )
				$comment['comment_author_url'] = $content_struct['author_url'];

			$comment['user_ID'] = 0;

			if ( get_option('require_name_email') ) {
				if ( 6 > strlen($comment['comment_author_email']) || '' == $comment['comment_author'] )
					return new IXR_Error( 403, __( 'Comment author name and email are required' ) );
				elseif ( !is_email($comment['comment_author_email']) )
					return new IXR_Error( 403, __( 'A valid email address is required' ) );
			}
		}

		$comment['comment_parent'] = isset($content_struct['comment_parent']) ? absint($content_struct['comment_parent']) : 0;

		$comment['comment_content'] = $content_struct['content'];

		do_action('xmlrpc_call', 'wp.newComment');

		return wp_new_comment($comment);
	}

	/**
	 * Retrieve all of the comment status.
	 *
	 * @since 2.7.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getCommentStatusList($args) {
		$this->escape( $args );

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];

		if ( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		set_current_user( 0, $username );
		if ( !current_user_can( 'moderate_comments' ) )
			return new IXR_Error( 403, __( 'You are not allowed access to details about this blog.' ) );

		do_action('xmlrpc_call', 'wp.getCommentStatusList');

		return get_comment_statuses( );
	}

	/**
	 * Retrieve comment count.
	 *
	 * @since 2.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getCommentCount( $args ) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$post_id	= (int) $args[3];

		if( !$this->login_pass_ok( $username, $password ) ) {
			return $this->error;
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_posts' ) ) {
			return new IXR_Error( 403, __( 'You are not allowed access to details about comments.' ) );
		}

		do_action('xmlrpc_call', 'wp.getCommentCount');

		$count = wp_count_comments( $post_id );
		return array(
			"approved" => $count->approved,
			"awaiting_moderation" => $count->moderated,
			"spam" => $count->spam,
			"total_comments" => $count->total_comments
		);
	}

	/**
	 * Retrieve post statuses.
	 *
	 * @since 2.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getPostStatusList( $args ) {
		$this->escape( $args );

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];

		if( !$this->login_pass_ok( $username, $password ) ) {
			return $this->error;
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_posts' ) ) {
			return new IXR_Error( 403, __( 'You are not allowed access to details about this blog.' ) );
		}

		do_action('xmlrpc_call', 'wp.getPostStatusList');

		return get_post_statuses( );
	}

	/**
	 * Retrieve page statuses.
	 *
	 * @since 2.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getPageStatusList( $args ) {
		$this->escape( $args );

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];

		if( !$this->login_pass_ok( $username, $password ) ) {
			return $this->error;
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_posts' ) ) {
			return new IXR_Error( 403, __( 'You are not allowed access to details about this blog.' ) );
		}

		do_action('xmlrpc_call', 'wp.getPageStatusList');

		return get_page_statuses( );
	}

	/**
	 * Retrieve page templates.
	 *
	 * @since 2.6.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getPageTemplates( $args ) {
		$this->escape( $args );

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];

		if( !$this->login_pass_ok( $username, $password ) ) {
			return $this->error;
		}

		set_current_user( 0, $username );
		if( !current_user_can( 'edit_pages' ) ) {
			return new IXR_Error( 403, __( 'You are not allowed access to details about this blog.' ) );
		}

		$templates = get_page_templates( );
		$templates['Default'] = 'default';

		return $templates;
	}

	/**
	 * Retrieve blog options.
	 *
	 * @since 2.6.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wp_getOptions( $args ) {
		$this->escape( $args );

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$options	= (array) $args[3];

		if( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		$user = set_current_user( 0, $username );

		// If no specific options where asked for, return all of them
		if (count( $options ) == 0 ) {
			$options = array_keys($this->blog_options);
		}

		return $this->_getOptions($options);
	}

	/**
	 * Retrieve blog options value from list.
	 *
	 * @since 2.6.0
	 *
	 * @param array $options Options to retrieve.
	 * @return array
	 */
	function _getOptions($options)
	{
		$data = array( );
		foreach( $options as $option ) {
			if( array_key_exists( $option, $this->blog_options ) )
			{
				$data[$option] = $this->blog_options[$option];
				//Is the value static or dynamic?
				if( isset( $data[$option]['option'] ) ) {
					$data[$option]['value'] = get_option( $data[$option]['option'] );
					unset($data[$option]['option']);
				}
			}
		}

		return $data;
	}

	/**
	 * Update blog options.
	 *
	 * @since 2.6.0
	 *
	 * @param array $args Method parameters.
	 * @return unknown
	 */
	function wp_setOptions( $args ) {
		$this->escape( $args );

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$options	= (array) $args[3];

		if( !$this->login_pass_ok( $username, $password ) )
			return $this->error;

		$user = set_current_user( 0, $username );
		if( !current_user_can( 'manage_options' ) )
			return new IXR_Error( 403, __( 'You are not allowed to update options.' ) );

		foreach( $options as $o_name => $o_value ) {
			$option_names[] = $o_name;
			if( empty( $o_value ) )
				continue;

			if( !array_key_exists( $o_name, $this->blog_options ) )
				continue;

			if( $this->blog_options[$o_name]['readonly'] == true )
				continue;

			update_option( $this->blog_options[$o_name]['option'], $o_value );
		}

		//Now return the updated values
		return $this->_getOptions($option_names);
	}

	/* Blogger API functions.
	 * specs on http://plant.blogger.com/api and http://groups.yahoo.com/group/bloggerDev/
	 */

	/**
	 * Retrieve blogs that user owns.
	 *
	 * Will make more sense once we support multiple blogs.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function blogger_getUsersBlogs($args) {

		$this->escape($args);

		$user_login = $args[1];
		$user_pass  = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.getUsersBlogs');

		set_current_user(0, $user_login);
		$is_admin = current_user_can('manage_options');

		$struct = array(
			'isAdmin'  => $is_admin,
			'url'      => get_option('home') . '/',
			'blogid'   => '1',
			'blogName' => get_option('blogname'),
			'xmlrpc'   => site_url( 'xmlrpc.php' )
		);

		return array($struct);
	}

	/**
	 * Retrieve user's data.
	 *
	 * Gives your client some info about you, so you don't have to.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function blogger_getUserInfo($args) {

		$this->escape($args);

		$user_login = $args[1];
		$user_pass  = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_posts' ) )
			return new IXR_Error( 401, __( 'Sorry, you do not have access to user data on this blog.' ) );

		do_action('xmlrpc_call', 'blogger.getUserInfo');

		$user_data = get_userdatabylogin($user_login);

		$struct = array(
			'nickname'  => $user_data->nickname,
			'userid'    => $user_data->ID,
			'url'       => $user_data->user_url,
			'lastname'  => $user_data->last_name,
			'firstname' => $user_data->first_name
		);

		return $struct;
	}

	/**
	 * Retrieve post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function blogger_getPost($args) {

		$this->escape($args);

		$post_ID    = (int) $args[1];
		$user_login = $args[2];
		$user_pass  = $args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_post', $post_ID ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit this post.' ) );

		do_action('xmlrpc_call', 'blogger.getPost');

		$post_data = wp_get_single_post($post_ID, ARRAY_A);

		$categories = implode(',', wp_get_post_categories($post_ID));

		$content  = '<title>'.stripslashes($post_data['post_title']).'</title>';
		$content .= '<category>'.$categories.'</category>';
		$content .= stripslashes($post_data['post_content']);

		$struct = array(
			'userid'    => $post_data['post_author'],
			'dateCreated' => new IXR_Date(mysql2date('Ymd\TH:i:s', $post_data['post_date'])),
			'content'     => $content,
			'postid'  => $post_data['ID']
		);

		return $struct;
	}

	/**
	 * Retrieve list of recent posts.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function blogger_getRecentPosts($args) {

		$this->escape($args);

		$blog_ID    = (int) $args[1]; /* though we don't use it yet */
		$user_login = $args[2];
		$user_pass  = $args[3];
		$num_posts  = $args[4];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.getRecentPosts');

		$posts_list = wp_get_recent_posts($num_posts);

		set_current_user( 0, $user_login );

		if (!$posts_list) {
			$this->error = new IXR_Error(500, __('Either there are no posts, or something went wrong.'));
			return $this->error;
		}

		foreach ($posts_list as $entry) {
			if( !current_user_can( 'edit_post', $entry['ID'] ) )
				continue;

			$post_date = mysql2date('Ymd\TH:i:s', $entry['post_date']);
			$categories = implode(',', wp_get_post_categories($entry['ID']));

			$content  = '<title>'.stripslashes($entry['post_title']).'</title>';
			$content .= '<category>'.$categories.'</category>';
			$content .= stripslashes($entry['post_content']);

			$struct[] = array(
				'userid' => $entry['post_author'],
				'dateCreated' => new IXR_Date($post_date),
				'content' => $content,
				'postid' => $entry['ID'],
			);

		}

		$recent_posts = array();
		for ($j=0; $j<count($struct); $j++) {
			array_push($recent_posts, $struct[$j]);
		}

		return $recent_posts;
	}

	/**
	 * Retrieve blog_filename content.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return string
	 */
	function blogger_getTemplate($args) {

		$this->escape($args);

		$blog_ID    = (int) $args[1];
		$user_login = $args[2];
		$user_pass  = $args[3];
		$template   = $args[4]; /* could be 'main' or 'archiveIndex', but we don't use it */

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.getTemplate');

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_themes') ) {
			return new IXR_Error(401, __('Sorry, this user can not edit the template.'));
		}

		/* warning: here we make the assumption that the blog's URL is on the same server */
		$filename = get_option('home') . '/';
		$filename = preg_replace('#https?://.+?/#', $_SERVER['DOCUMENT_ROOT'].'/', $filename);

		$f = fopen($filename, 'r');
		$content = fread($f, filesize($filename));
		fclose($f);

		/* so it is actually editable with a windows/mac client */
		// FIXME: (or delete me) do we really want to cater to bad clients at the expense of good ones by BEEPing up their line breaks? commented.     $content = str_replace("\n", "\r\n", $content);

		return $content;
	}

	/**
	 * Updates the content of blog_filename.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True when done.
	 */
	function blogger_setTemplate($args) {

		$this->escape($args);

		$blog_ID    = (int) $args[1];
		$user_login = $args[2];
		$user_pass  = $args[3];
		$content    = $args[4];
		$template   = $args[5]; /* could be 'main' or 'archiveIndex', but we don't use it */

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.setTemplate');

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_themes') ) {
			return new IXR_Error(401, __('Sorry, this user can not edit the template.'));
		}

		/* warning: here we make the assumption that the blog's URL is on the same server */
		$filename = get_option('home') . '/';
		$filename = preg_replace('#https?://.+?/#', $_SERVER['DOCUMENT_ROOT'].'/', $filename);

		if ($f = fopen($filename, 'w+')) {
			fwrite($f, $content);
			fclose($f);
		} else {
			return new IXR_Error(500, __('Either the file is not writable, or something wrong happened. The file has not been updated.'));
		}

		return true;
	}

	/**
	 * Create new post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return int
	 */
	function blogger_newPost($args) {

		$this->escape($args);

		$blog_ID    = (int) $args[1]; /* though we don't use it yet */
		$user_login = $args[2];
		$user_pass  = $args[3];
		$content    = $args[4];
		$publish    = $args[5];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.newPost');

		$cap = ($publish) ? 'publish_posts' : 'edit_posts';
		$user = set_current_user(0, $user_login);
		if ( !current_user_can($cap) )
			return new IXR_Error(401, __('Sorry, you are not allowed to post on this blog.'));

		$post_status = ($publish) ? 'publish' : 'draft';

		$post_author = $user->ID;

		$post_title = xmlrpc_getposttitle($content);
		$post_category = xmlrpc_getpostcategory($content);
		$post_content = xmlrpc_removepostdata($content);

		$post_date = current_time('mysql');
		$post_date_gmt = current_time('mysql', 1);

		$post_data = compact('blog_ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_category', 'post_status');

		$post_ID = wp_insert_post($post_data);
		if ( is_wp_error( $post_ID ) )
			return new IXR_Error(500, $post_ID->get_error_message());

		if (!$post_ID)
			return new IXR_Error(500, __('Sorry, your entry could not be posted. Something wrong happened.'));

		$this->attach_uploads( $post_ID, $post_content );

		logIO('O', "Posted ! ID: $post_ID");

		return $post_ID;
	}

	/**
	 * Edit a post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool true when done.
	 */
	function blogger_editPost($args) {

		$this->escape($args);

		$post_ID     = (int) $args[1];
		$user_login  = $args[2];
		$user_pass   = $args[3];
		$content     = $args[4];
		$publish     = $args[5];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.editPost');

		$actual_post = wp_get_single_post($post_ID,ARRAY_A);

		if (!$actual_post || $actual_post['post_type'] != 'post') {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}

		$this->escape($actual_post);

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_post', $post_ID) )
			return new IXR_Error(401, __('Sorry, you do not have the right to edit this post.'));

		extract($actual_post, EXTR_SKIP);

		if ( ('publish' == $post_status) && !current_user_can('publish_posts') )
			return new IXR_Error(401, __('Sorry, you do not have the right to publish this post.'));

		$post_title = xmlrpc_getposttitle($content);
		$post_category = xmlrpc_getpostcategory($content);
		$post_content = xmlrpc_removepostdata($content);

		$postdata = compact('ID', 'post_content', 'post_title', 'post_category', 'post_status', 'post_excerpt');

		$result = wp_update_post($postdata);

		if (!$result) {
			return new IXR_Error(500, __('For some strange yet very annoying reason, this post could not be edited.'));
		}
		$this->attach_uploads( $ID, $post_content );

		return true;
	}

	/**
	 * Remove a post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True when post is deleted.
	 */
	function blogger_deletePost($args) {
		$this->escape($args);

		$post_ID     = (int) $args[1];
		$user_login  = $args[2];
		$user_pass   = $args[3];
		$publish     = $args[4];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'blogger.deletePost');

		$actual_post = wp_get_single_post($post_ID,ARRAY_A);

		if (!$actual_post || $actual_post['post_type'] != 'post') {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_post', $post_ID) )
			return new IXR_Error(401, __('Sorry, you do not have the right to delete this post.'));

		$result = wp_delete_post($post_ID);

		if (!$result) {
			return new IXR_Error(500, __('For some strange yet very annoying reason, this post could not be deleted.'));
		}

		return true;
	}

	/* MetaWeblog API functions
	 * specs on wherever Dave Winer wants them to be
	 */

	/**
	 * Create a new post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return int
	 */
	function mw_newPost($args) {
		$this->escape($args);

		$blog_ID     = (int) $args[0]; // we will support this in the near future
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$content_struct = $args[3];
		$publish     = $args[4];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}
		$user = set_current_user(0, $user_login);

		do_action('xmlrpc_call', 'metaWeblog.newPost');

		$cap = ( $publish ) ? 'publish_posts' : 'edit_posts';
		$error_message = __( 'Sorry, you are not allowed to publish posts on this blog.' );
		$post_type = 'post';
		$page_template = '';
		if( !empty( $content_struct['post_type'] ) ) {
			if( $content_struct['post_type'] == 'page' ) {
				$cap = ( $publish ) ? 'publish_pages' : 'edit_pages';
				$error_message = __( 'Sorry, you are not allowed to publish pages on this blog.' );
				$post_type = 'page';
				if( !empty( $content_struct['wp_page_template'] ) )
					$page_template = $content_struct['wp_page_template'];
			}
			elseif( $content_struct['post_type'] == 'post' ) {
				// This is the default, no changes needed
			}
			else {
				// No other post_type values are allowed here
				return new IXR_Error( 401, __( 'Invalid post type.' ) );
			}
		}

		if( !current_user_can( $cap ) ) {
			return new IXR_Error( 401, $error_message );
		}

		// Let WordPress generate the post_name (slug) unless
		// one has been provided.
		$post_name = "";
		if(isset($content_struct["wp_slug"])) {
			$post_name = $content_struct["wp_slug"];
		}

		// Only use a password if one was given.
		if(isset($content_struct["wp_password"])) {
			$post_password = $content_struct["wp_password"];
		}

		// Only set a post parent if one was provided.
		if(isset($content_struct["wp_page_parent_id"])) {
			$post_parent = $content_struct["wp_page_parent_id"];
		}

		// Only set the menu_order if it was provided.
		if(isset($content_struct["wp_page_order"])) {
			$menu_order = $content_struct["wp_page_order"];
		}

		$post_author = $user->ID;

		// If an author id was provided then use it instead.
		if(
			isset($content_struct["wp_author_id"])
			&& ($user->ID != $content_struct["wp_author_id"])
		) {
			switch($post_type) {
				case "post":
					if(!current_user_can("edit_others_posts")) {
						return(new IXR_Error(401, __("You are not allowed to post as this user")));
					}
					break;
				case "page":
					if(!current_user_can("edit_others_pages")) {
						return(new IXR_Error(401, __("You are not allowed to create pages as this user")));
					}
					break;
				default:
					return(new IXR_Error(401, __("Invalid post type.")));
					break;
			}
			$post_author = $content_struct["wp_author_id"];
		}

		$post_title = $content_struct['title'];
		$post_content = apply_filters( 'content_save_pre', $content_struct['description'] );

		$post_status = $publish ? 'publish' : 'draft';

		if( isset( $content_struct["{$post_type}_status"] ) ) {
			switch( $content_struct["{$post_type}_status"] ) {
				case 'draft':
				case 'private':
				case 'publish':
					$post_status = $content_struct["{$post_type}_status"];
					break;
				case 'pending':
					// Pending is only valid for posts, not pages.
					if( $post_type === 'post' ) {
						$post_status = $content_struct["{$post_type}_status"];
					}
					break;
				default:
					$post_status = $publish ? 'publish' : 'draft';
					break;
			}
		}

		$post_excerpt = $content_struct['mt_excerpt'];
		$post_more = $content_struct['mt_text_more'];

		$tags_input = $content_struct['mt_keywords'];

		if(isset($content_struct["mt_allow_comments"])) {
			if(!is_numeric($content_struct["mt_allow_comments"])) {
				switch($content_struct["mt_allow_comments"]) {
					case "closed":
						$comment_status = "closed";
						break;
					case "open":
						$comment_status = "open";
						break;
					default:
						$comment_status = get_option("default_comment_status");
						break;
				}
			}
			else {
				switch((int) $content_struct["mt_allow_comments"]) {
					case 0:
					case 2:
						$comment_status = "closed";
						break;
					case 1:
						$comment_status = "open";
						break;
					default:
						$comment_status = get_option("default_comment_status");
						break;
				}
			}
		}
		else {
			$comment_status = get_option("default_comment_status");
		}

		if(isset($content_struct["mt_allow_pings"])) {
			if(!is_numeric($content_struct["mt_allow_pings"])) {
				switch($content_struct['mt_allow_pings']) {
					case "closed":
						$ping_status = "closed";
						break;
					case "open":
						$ping_status = "open";
						break;
					default:
						$ping_status = get_option("default_ping_status");
						break;
				}
			}
			else {
				switch((int) $content_struct["mt_allow_pings"]) {
					case 0:
						$ping_status = "closed";
						break;
					case 1:
						$ping_status = "open";
						break;
					default:
						$ping_status = get_option("default_ping_status");
						break;
				}
			}
		}
		else {
			$ping_status = get_option("default_ping_status");
		}

		if ($post_more) {
			$post_content = $post_content . "<!--more-->" . $post_more;
		}

		$to_ping = $content_struct['mt_tb_ping_urls'];
		if ( is_array($to_ping) )
			$to_ping = implode(' ', $to_ping);

		// Do some timestamp voodoo
		if ( !empty( $content_struct['date_created_gmt'] ) )
			$dateCreated = str_replace( 'Z', '', $content_struct['date_created_gmt']->getIso() ) . 'Z'; // We know this is supposed to be GMT, so we're going to slap that Z on there by force
		elseif ( !empty( $content_struct['dateCreated']) )
			$dateCreated = $content_struct['dateCreated']->getIso();

		if ( !empty( $dateCreated ) ) {
			$post_date = get_date_from_gmt(iso8601_to_datetime($dateCreated));
			$post_date_gmt = iso8601_to_datetime($dateCreated, GMT);
		} else {
			$post_date = current_time('mysql');
			$post_date_gmt = current_time('mysql', 1);
		}

		$catnames = $content_struct['categories'];
		logIO('O', 'Post cats: ' . var_export($catnames,true));
		$post_category = array();

		if (is_array($catnames)) {
			foreach ($catnames as $cat) {
				$post_category[] = get_cat_ID($cat);
			}
		}

		// We've got all the data -- post it:
		$postdata = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_category', 'post_status', 'post_excerpt', 'comment_status', 'ping_status', 'to_ping', 'post_type', 'post_name', 'post_password', 'post_parent', 'menu_order', 'tags_input', 'page_template');

		$post_ID = wp_insert_post($postdata, true);
		if ( is_wp_error( $post_ID ) )
			return new IXR_Error(500, $post_ID->get_error_message());

		if (!$post_ID) {
			return new IXR_Error(500, __('Sorry, your entry could not be posted. Something wrong happened.'));
		}

		if ( isset($content_struct['custom_fields']) ) {
			$this->set_custom_fields($post_ID, $content_struct['custom_fields']);
		}

		// Handle enclosures
		$enclosure = $content_struct['enclosure'];
		if( is_array( $enclosure ) && isset( $enclosure['url'] ) && isset( $enclosure['length'] ) && isset( $enclosure['type'] ) ) {
			add_post_meta( $post_ID, 'enclosure', $enclosure['url'] . "\n" . $enclosure['length'] . "\n" . $enclosure['type'] );
		}

		$this->attach_uploads( $post_ID, $post_content );

		logIO('O', "Posted ! ID: $post_ID");

		return strval($post_ID);
	}

	/**
	 * Attach upload to a post.
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_ID Post ID.
	 * @param string $post_content Post Content for attachment.
	 */
	function attach_uploads( $post_ID, $post_content ) {
		global $wpdb;

		// find any unattached files
		$attachments = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts} WHERE post_parent = '-1' AND post_type = 'attachment'" );
		if( is_array( $attachments ) ) {
			foreach( $attachments as $file ) {
				if( strpos( $post_content, $file->guid ) !== false ) {
					$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_parent = %d WHERE ID = %d", $post_ID, $file->ID) );
				}
			}
		}
	}

	/**
	 * Edit a post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True on success.
	 */
	function mw_editPost($args) {

		$this->escape($args);

		$post_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$content_struct = $args[3];
		$publish     = $args[4];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}
		$user = set_current_user(0, $user_login);

		do_action('xmlrpc_call', 'metaWeblog.editPost');

		$cap = ( $publish ) ? 'publish_posts' : 'edit_posts';
		$error_message = __( 'Sorry, you are not allowed to publish posts on this blog.' );
		$post_type = 'post';
		$page_template = '';
		if( !empty( $content_struct['post_type'] ) ) {
			if( $content_struct['post_type'] == 'page' ) {
				$cap = ( $publish ) ? 'publish_pages' : 'edit_pages';
				$error_message = __( 'Sorry, you are not allowed to publish pages on this blog.' );
				$post_type = 'page';
				if( !empty( $content_struct['wp_page_template'] ) )
					$page_template = $content_struct['wp_page_template'];
			}
			elseif( $content_struct['post_type'] == 'post' ) {
				// This is the default, no changes needed
			}
			else {
				// No other post_type values are allowed here
				return new IXR_Error( 401, __( 'Invalid post type.' ) );
			}
		}

		if( !current_user_can( $cap ) ) {
			return new IXR_Error( 401, $error_message );
		}

		$postdata = wp_get_single_post($post_ID, ARRAY_A);

		// If there is no post data for the give post id, stop
		// now and return an error.  Other wise a new post will be
		// created (which was the old behavior).
		if(empty($postdata["ID"])) {
			return(new IXR_Error(404, __("Invalid post id.")));
		}

		$this->escape($postdata);
		extract($postdata, EXTR_SKIP);

		// Let WordPress manage slug if none was provided.
		$post_name = "";
		if(isset($content_struct["wp_slug"])) {
			$post_name = $content_struct["wp_slug"];
		}

		// Only use a password if one was given.
		if(isset($content_struct["wp_password"])) {
			$post_password = $content_struct["wp_password"];
		}

		// Only set a post parent if one was given.
		if(isset($content_struct["wp_page_parent_id"])) {
			$post_parent = $content_struct["wp_page_parent_id"];
		}

		// Only set the menu_order if it was given.
		if(isset($content_struct["wp_page_order"])) {
			$menu_order = $content_struct["wp_page_order"];
		}

		$post_author = $postdata["post_author"];

		// Only set the post_author if one is set.
		if(
			isset($content_struct["wp_author_id"])
			&& ($user->ID != $content_struct["wp_author_id"])
		) {
			switch($post_type) {
				case "post":
					if(!current_user_can("edit_others_posts")) {
						return(new IXR_Error(401, __("You are not allowed to change the post author as this user.")));
					}
					break;
				case "page":
					if(!current_user_can("edit_others_pages")) {
						return(new IXR_Error(401, __("You are not allowed to change the page author as this user.")));
					}
					break;
				default:
					return(new IXR_Error(401, __("Invalid post type.")));
					break;
			}
			$post_author = $content_struct["wp_author_id"];
		}

		if(isset($content_struct["mt_allow_comments"])) {
			if(!is_numeric($content_struct["mt_allow_comments"])) {
				switch($content_struct["mt_allow_comments"]) {
					case "closed":
						$comment_status = "closed";
						break;
					case "open":
						$comment_status = "open";
						break;
					default:
						$comment_status = get_option("default_comment_status");
						break;
				}
			}
			else {
				switch((int) $content_struct["mt_allow_comments"]) {
					case 0:
					case 2:
						$comment_status = "closed";
						break;
					case 1:
						$comment_status = "open";
						break;
					default:
						$comment_status = get_option("default_comment_status");
						break;
				}
			}
		}

		if(isset($content_struct["mt_allow_pings"])) {
			if(!is_numeric($content_struct["mt_allow_pings"])) {
				switch($content_struct["mt_allow_pings"]) {
					case "closed":
						$ping_status = "closed";
						break;
					case "open":
						$ping_status = "open";
						break;
					default:
						$ping_status = get_option("default_ping_status");
						break;
				}
			}
			else {
				switch((int) $content_struct["mt_allow_pings"]) {
					case 0:
						$ping_status = "closed";
						break;
					case 1:
						$ping_status = "open";
						break;
					default:
						$ping_status = get_option("default_ping_status");
						break;
				}
			}
		}

		$post_title = $content_struct['title'];
		$post_content = apply_filters( 'content_save_pre', $content_struct['description'] );
		$catnames = $content_struct['categories'];

		$post_category = array();

		if (is_array($catnames)) {
			foreach ($catnames as $cat) {
		 		$post_category[] = get_cat_ID($cat);
			}
		}

		$post_excerpt = $content_struct['mt_excerpt'];
		$post_more = $content_struct['mt_text_more'];

		$post_status = $publish ? 'publish' : 'draft';
		if( isset( $content_struct["{$post_type}_status"] ) ) {
			switch( $content_struct["{$post_type}_status"] ) {
				case 'draft':
				case 'private':
				case 'publish':
					$post_status = $content_struct["{$post_type}_status"];
					break;
				case 'pending':
					// Pending is only valid for posts, not pages.
					if( $post_type === 'post' ) {
						$post_status = $content_struct["{$post_type}_status"];
					}
					break;
				default:
					$post_status = $publish ? 'publish' : 'draft';
					break;
			}
		}

		$tags_input = $content_struct['mt_keywords'];

		if ( ('publish' == $post_status) ) {
			if ( ( 'page' == $post_type ) && !current_user_can('publish_pages') )
				return new IXR_Error(401, __('Sorry, you do not have the right to publish this page.'));
			else if ( !current_user_can('publish_posts') )
				return new IXR_Error(401, __('Sorry, you do not have the right to publish this post.'));
		}

		if ($post_more) {
			$post_content = $post_content . "<!--more-->" . $post_more;
		}

		$to_ping = $content_struct['mt_tb_ping_urls'];
		if ( is_array($to_ping) )
			$to_ping = implode(' ', $to_ping);

		// Do some timestamp voodoo
		if ( !empty( $content_struct['date_created_gmt'] ) )
			$dateCreated = str_replace( 'Z', '', $content_struct['date_created_gmt']->getIso() ) . 'Z'; // We know this is supposed to be GMT, so we're going to slap that Z on there by force
		elseif ( !empty( $content_struct['dateCreated']) )
			$dateCreated = $content_struct['dateCreated']->getIso();

		if ( !empty( $dateCreated ) ) {
			$post_date = get_date_from_gmt(iso8601_to_datetime($dateCreated));
			$post_date_gmt = iso8601_to_datetime($dateCreated, GMT);
		} else {
			$post_date     = $postdata['post_date'];
			$post_date_gmt = $postdata['post_date_gmt'];
		}

		// We've got all the data -- post it:
		$newpost = compact('ID', 'post_content', 'post_title', 'post_category', 'post_status', 'post_excerpt', 'comment_status', 'ping_status', 'post_date', 'post_date_gmt', 'to_ping', 'post_name', 'post_password', 'post_parent', 'menu_order', 'post_author', 'tags_input', 'page_template');

		$result = wp_update_post($newpost, true);
		if ( is_wp_error( $result ) )
			return new IXR_Error(500, $result->get_error_message());

		if (!$result) {
			return new IXR_Error(500, __('Sorry, your entry could not be edited. Something wrong happened.'));
		}

		if ( isset($content_struct['custom_fields']) ) {
			$this->set_custom_fields($post_ID, $content_struct['custom_fields']);
		}

		// Handle enclosures
		$enclosure = $content_struct['enclosure'];
		if( is_array( $enclosure ) && isset( $enclosure['url'] ) && isset( $enclosure['length'] ) && isset( $enclosure['type'] ) ) {
			add_post_meta( $post_ID, 'enclosure', $enclosure['url'] . "\n" . $enclosure['length'] . "\n" . $enclosure['type'] );
		}

		$this->attach_uploads( $ID, $post_content );

		logIO('O',"(MW) Edited ! ID: $post_ID");

		return true;
	}

	/**
	 * Retrieve post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_getPost($args) {

		$this->escape($args);

		$post_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_post', $post_ID ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit this post.' ) );

		do_action('xmlrpc_call', 'metaWeblog.getPost');

		$postdata = wp_get_single_post($post_ID, ARRAY_A);

		if ($postdata['post_date'] != '') {
			$post_date = mysql2date('Ymd\TH:i:s', $postdata['post_date']);
			$post_date_gmt = mysql2date('Ymd\TH:i:s', $postdata['post_date_gmt']);

			$categories = array();
			$catids = wp_get_post_categories($post_ID);
			foreach($catids as $catid)
				$categories[] = get_cat_name($catid);

			$tagnames = array();
			$tags = wp_get_post_tags( $post_ID );
			if ( !empty( $tags ) ) {
				foreach ( $tags as $tag )
					$tagnames[] = $tag->name;
				$tagnames = implode( ', ', $tagnames );
			} else {
				$tagnames = '';
			}

			$post = get_extended($postdata['post_content']);
			$link = post_permalink($postdata['ID']);

			// Get the author info.
			$author = get_userdata($postdata['post_author']);

			$allow_comments = ('open' == $postdata['comment_status']) ? 1 : 0;
			$allow_pings = ('open' == $postdata['ping_status']) ? 1 : 0;

			// Consider future posts as published
			if( $postdata['post_status'] === 'future' ) {
				$postdata['post_status'] = 'publish';
			}

			$enclosure = array();
			foreach ( (array) get_post_custom($post_ID) as $key => $val) {
				if ($key == 'enclosure') {
					foreach ( (array) $val as $enc ) {
						$encdata = split("\n", $enc);
						$enclosure['url'] = trim(htmlspecialchars($encdata[0]));
						$enclosure['length'] = trim($encdata[1]);
						$enclosure['type'] = trim($encdata[2]);
						break 2;
					}
				}
			}

			$resp = array(
				'dateCreated' => new IXR_Date($post_date),
				'userid' => $postdata['post_author'],
				'postid' => $postdata['ID'],
				'description' => $post['main'],
				'title' => $postdata['post_title'],
				'link' => $link,
				'permaLink' => $link,
				// commented out because no other tool seems to use this
				//	      'content' => $entry['post_content'],
				'categories' => $categories,
				'mt_excerpt' => $postdata['post_excerpt'],
				'mt_text_more' => $post['extended'],
				'mt_allow_comments' => $allow_comments,
				'mt_allow_pings' => $allow_pings,
				'mt_keywords' => $tagnames,
				'wp_slug' => $postdata['post_name'],
				'wp_password' => $postdata['post_password'],
				'wp_author_id' => $author->ID,
				'wp_author_display_name'	=> $author->display_name,
				'date_created_gmt' => new IXR_Date($post_date_gmt),
				'post_status' => $postdata['post_status'],
				'custom_fields' => $this->get_custom_fields($post_ID)
			);

			if (!empty($enclosure)) $resp['enclosure'] = $enclosure;

			return $resp;
		} else {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}
	}

	/**
	 * Retrieve list of recent posts.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_getRecentPosts($args) {

		$this->escape($args);

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$num_posts   = (int) $args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'metaWeblog.getRecentPosts');

		$posts_list = wp_get_recent_posts($num_posts);

		if (!$posts_list) {
			return array( );
		}

		set_current_user( 0, $user_login );

		foreach ($posts_list as $entry) {
			if( !current_user_can( 'edit_post', $entry['ID'] ) )
				continue;

			$post_date = mysql2date('Ymd\TH:i:s', $entry['post_date']);
			$post_date_gmt = mysql2date('Ymd\TH:i:s', $entry['post_date_gmt']);

			$categories = array();
			$catids = wp_get_post_categories($entry['ID']);
			foreach($catids as $catid) {
				$categories[] = get_cat_name($catid);
			}

			$tagnames = array();
			$tags = wp_get_post_tags( $entry['ID'] );
			if ( !empty( $tags ) ) {
				foreach ( $tags as $tag ) {
					$tagnames[] = $tag->name;
				}
				$tagnames = implode( ', ', $tagnames );
			} else {
				$tagnames = '';
			}

			$post = get_extended($entry['post_content']);
			$link = post_permalink($entry['ID']);

			// Get the post author info.
			$author = get_userdata($entry['post_author']);

			$allow_comments = ('open' == $entry['comment_status']) ? 1 : 0;
			$allow_pings = ('open' == $entry['ping_status']) ? 1 : 0;

			// Consider future posts as published
			if( $entry['post_status'] === 'future' ) {
				$entry['post_status'] = 'publish';
			}

			$struct[] = array(
				'dateCreated' => new IXR_Date($post_date),
				'userid' => $entry['post_author'],
				'postid' => $entry['ID'],
				'description' => $post['main'],
				'title' => $entry['post_title'],
				'link' => $link,
				'permaLink' => $link,
				// commented out because no other tool seems to use this
				// 'content' => $entry['post_content'],
				'categories' => $categories,
				'mt_excerpt' => $entry['post_excerpt'],
				'mt_text_more' => $post['extended'],
				'mt_allow_comments' => $allow_comments,
				'mt_allow_pings' => $allow_pings,
				'mt_keywords' => $tagnames,
				'wp_slug' => $entry['post_name'],
				'wp_password' => $entry['post_password'],
				'wp_author_id' => $author->ID,
				'wp_author_display_name' => $author->display_name,
				'date_created_gmt' => new IXR_Date($post_date_gmt),
				'post_status' => $entry['post_status'],
				'custom_fields' => $this->get_custom_fields($entry['ID'])
			);

		}

		$recent_posts = array();
		for ($j=0; $j<count($struct); $j++) {
			array_push($recent_posts, $struct[$j]);
		}

		return $recent_posts;
	}

	/**
	 * Retrieve the list of categories on a given blog.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_getCategories($args) {

		$this->escape($args);

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_posts' ) )
			return new IXR_Error( 401, __( 'Sorry, you must be able to edit posts on this blog in order to view categories.' ) );

		do_action('xmlrpc_call', 'metaWeblog.getCategories');

		$categories_struct = array();

		if ( $cats = get_categories('get=all') ) {
			foreach ( $cats as $cat ) {
				$struct['categoryId'] = $cat->term_id;
				$struct['parentId'] = $cat->parent;
				$struct['description'] = $cat->name;
				$struct['categoryDescription'] = $cat->description;
				$struct['categoryName'] = $cat->name;
				$struct['htmlUrl'] = wp_specialchars(get_category_link($cat->term_id));
				$struct['rssUrl'] = wp_specialchars(get_category_feed_link($cat->term_id, 'rss2'));

				$categories_struct[] = $struct;
			}
		}

		return $categories_struct;
	}

	/**
	 * Uploads a file, following your settings.
	 *
	 * Adapted from a patch by Johann Richard.
	 *
	 * @link http://mycvs.org/archives/2004/06/30/file-upload-to-wordpress-in-ecto/
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mw_newMediaObject($args) {
		global $wpdb;

		$blog_ID     = (int) $args[0];
		$user_login  = $wpdb->escape($args[1]);
		$user_pass   = $wpdb->escape($args[2]);
		$data        = $args[3];

		$name = sanitize_file_name( $data['name'] );
		$type = $data['type'];
		$bits = $data['bits'];

		logIO('O', '(MW) Received '.strlen($bits).' bytes');

		if ( !$this->login_pass_ok($user_login, $user_pass) )
			return $this->error;

		do_action('xmlrpc_call', 'metaWeblog.newMediaObject');

		set_current_user(0, $user_login);
		if ( !current_user_can('upload_files') ) {
			logIO('O', '(MW) User does not have upload_files capability');
			$this->error = new IXR_Error(401, __('You are not allowed to upload files to this site.'));
			return $this->error;
		}

		if ( $upload_err = apply_filters( "pre_upload_error", false ) )
			return new IXR_Error(500, $upload_err);

		if(!empty($data["overwrite"]) && ($data["overwrite"] == true)) {
			// Get postmeta info on the object.
			$old_file = $wpdb->get_row("
				SELECT ID
				FROM {$wpdb->posts}
				WHERE post_title = '{$name}'
					AND post_type = 'attachment'
			");

			// Delete previous file.
			wp_delete_attachment($old_file->ID);

			// Make sure the new name is different by pre-pending the
			// previous post id.
			$filename = preg_replace("/^wpid\d+-/", "", $name);
			$name = "wpid{$old_file->ID}-{$filename}";
		}

		$upload = wp_upload_bits($name, $type, $bits);
		if ( ! empty($upload['error']) ) {
			$errorString = sprintf(__('Could not write file %1$s (%2$s)'), $name, $upload['error']);
			logIO('O', '(MW) ' . $errorString);
			return new IXR_Error(500, $errorString);
		}
		// Construct the attachment array
		// attach to post_id -1
		$post_id = -1;
		$attachment = array(
			'post_title' => $name,
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => $post_id,
			'post_mime_type' => $type,
			'guid' => $upload[ 'url' ]
		);

		// Save the data
		$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

		return apply_filters( 'wp_handle_upload', array( 'file' => $name, 'url' => $upload[ 'url' ], 'type' => $type ) );
	}

	/* MovableType API functions
	 * specs on http://www.movabletype.org/docs/mtmanual_programmatic.html
	 */

	/**
	 * Retrieve the post titles of recent posts.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_getRecentPostTitles($args) {

		$this->escape($args);

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$num_posts   = (int) $args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'mt.getRecentPostTitles');

		$posts_list = wp_get_recent_posts($num_posts);

		if (!$posts_list) {
			$this->error = new IXR_Error(500, __('Either there are no posts, or something went wrong.'));
			return $this->error;
		}

		set_current_user( 0, $user_login );

		foreach ($posts_list as $entry) {
			if( !current_user_can( 'edit_post', $entry['ID'] ) )
				continue;

			$post_date = mysql2date('Ymd\TH:i:s', $entry['post_date']);
			$post_date_gmt = mysql2date('Ymd\TH:i:s', $entry['post_date_gmt']);

			$struct[] = array(
				'dateCreated' => new IXR_Date($post_date),
				'userid' => $entry['post_author'],
				'postid' => $entry['ID'],
				'title' => $entry['post_title'],
				'date_created_gmt' => new IXR_Date($post_date_gmt)
			);

		}

		$recent_posts = array();
		for ($j=0; $j<count($struct); $j++) {
			array_push($recent_posts, $struct[$j]);
		}

		return $recent_posts;
	}

	/**
	 * Retrieve list of all categories on blog.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_getCategoryList($args) {

		$this->escape($args);

		$blog_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_posts' ) )
			return new IXR_Error( 401, __( 'Sorry, you must be able to edit posts on this blog in order to view categories.' ) );

		do_action('xmlrpc_call', 'mt.getCategoryList');

		$categories_struct = array();

		if ( $cats = get_categories('hide_empty=0&hierarchical=0') ) {
			foreach ($cats as $cat) {
				$struct['categoryId'] = $cat->term_id;
				$struct['categoryName'] = $cat->name;

				$categories_struct[] = $struct;
			}
		}

		return $categories_struct;
	}

	/**
	 * Retrieve post categories.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_getPostCategories($args) {

		$this->escape($args);

		$post_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		set_current_user( 0, $user_login );
		if( !current_user_can( 'edit_post', $post_ID ) )
			return new IXR_Error( 401, __( 'Sorry, you can not edit this post.' ) );

		do_action('xmlrpc_call', 'mt.getPostCategories');

		$categories = array();
		$catids = wp_get_post_categories(intval($post_ID));
		// first listed category will be the primary category
		$isPrimary = true;
		foreach($catids as $catid) {
			$categories[] = array(
				'categoryName' => get_cat_name($catid),
				'categoryId' => (string) $catid,
				'isPrimary' => $isPrimary
			);
			$isPrimary = false;
		}

		return $categories;
	}

	/**
	 * Sets categories for a post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return bool True on success.
	 */
	function mt_setPostCategories($args) {

		$this->escape($args);

		$post_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];
		$categories  = $args[3];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'mt.setPostCategories');

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_post', $post_ID) )
			return new IXR_Error(401, __('Sorry, you can not edit this post.'));

		foreach($categories as $cat) {
			$catids[] = $cat['categoryId'];
		}

		wp_set_post_categories($post_ID, $catids);

		return true;
	}

	/**
	 * Retrieve an array of methods supported by this server.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function mt_supportedMethods($args) {

		do_action('xmlrpc_call', 'mt.supportedMethods');

		$supported_methods = array();
		foreach($this->methods as $key=>$value) {
			$supported_methods[] = $key;
		}

		return $supported_methods;
	}

	/**
	 * Retrieve an empty array because we don't support per-post text filters.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 */
	function mt_supportedTextFilters($args) {
		do_action('xmlrpc_call', 'mt.supportedTextFilters');
		return apply_filters('xmlrpc_text_filters', array());
	}

	/**
	 * Retrieve trackbacks sent to a given post.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return mixed
	 */
	function mt_getTrackbackPings($args) {

		global $wpdb;

		$post_ID = intval($args);

		do_action('xmlrpc_call', 'mt.getTrackbackPings');

		$actual_post = wp_get_single_post($post_ID, ARRAY_A);

		if (!$actual_post) {
			return new IXR_Error(404, __('Sorry, no such post.'));
		}

		$comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_author_url, comment_content, comment_author_IP, comment_type FROM $wpdb->comments WHERE comment_post_ID = %d", $post_ID) );

		if (!$comments) {
			return array();
		}

		$trackback_pings = array();
		foreach($comments as $comment) {
			if ( 'trackback' == $comment->comment_type ) {
				$content = $comment->comment_content;
				$title = substr($content, 8, (strpos($content, '</strong>') - 8));
				$trackback_pings[] = array(
					'pingTitle' => $title,
					'pingURL'   => $comment->comment_author_url,
					'pingIP'    => $comment->comment_author_IP
				);
		}
		}

		return $trackback_pings;
	}

	/**
	 * Sets a post's publish status to 'publish'.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return int
	 */
	function mt_publishPost($args) {

		$this->escape($args);

		$post_ID     = (int) $args[0];
		$user_login  = $args[1];
		$user_pass   = $args[2];

		if (!$this->login_pass_ok($user_login, $user_pass)) {
			return $this->error;
		}

		do_action('xmlrpc_call', 'mt.publishPost');

		set_current_user(0, $user_login);
		if ( !current_user_can('edit_post', $post_ID) )
			return new IXR_Error(401, __('Sorry, you can not edit this post.'));

		$postdata = wp_get_single_post($post_ID,ARRAY_A);

		$postdata['post_status'] = 'publish';

		// retain old cats
		$cats = wp_get_post_categories($post_ID);
		$postdata['post_category'] = $cats;
		$this->escape($postdata);

		$result = wp_update_post($postdata);

		return $result;
	}

	/* PingBack functions
	 * specs on www.hixie.ch/specs/pingback/pingback
	 */

	/**
	 * Retrieves a pingback and registers it.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function pingback_ping($args) {
		global $wpdb;

		do_action('xmlrpc_call', 'pingback.ping');

		$this->escape($args);

		$pagelinkedfrom = $args[0];
		$pagelinkedto   = $args[1];

		$title = '';

		$pagelinkedfrom = str_replace('&amp;', '&', $pagelinkedfrom);
		$pagelinkedto = str_replace('&amp;', '&', $pagelinkedto);
		$pagelinkedto = str_replace('&', '&amp;', $pagelinkedto);

		// Check if the page linked to is in our site
		$pos1 = strpos($pagelinkedto, str_replace(array('http://www.','http://','https://www.','https://'), '', get_option('home')));
		if( !$pos1 )
			return new IXR_Error(0, __('Is there no link to us?'));

		// let's find which post is linked to
		// FIXME: does url_to_postid() cover all these cases already?
		//        if so, then let's use it and drop the old code.
		$urltest = parse_url($pagelinkedto);
		if ($post_ID = url_to_postid($pagelinkedto)) {
			$way = 'url_to_postid()';
		} elseif (preg_match('#p/[0-9]{1,}#', $urltest['path'], $match)) {
			// the path defines the post_ID (archives/p/XXXX)
			$blah = explode('/', $match[0]);
			$post_ID = (int) $blah[1];
			$way = 'from the path';
		} elseif (preg_match('#p=[0-9]{1,}#', $urltest['query'], $match)) {
			// the querystring defines the post_ID (?p=XXXX)
			$blah = explode('=', $match[0]);
			$post_ID = (int) $blah[1];
			$way = 'from the querystring';
		} elseif (isset($urltest['fragment'])) {
			// an #anchor is there, it's either...
			if (intval($urltest['fragment'])) {
				// ...an integer #XXXX (simpliest case)
				$post_ID = (int) $urltest['fragment'];
				$way = 'from the fragment (numeric)';
			} elseif (preg_match('/post-[0-9]+/',$urltest['fragment'])) {
				// ...a post id in the form 'post-###'
				$post_ID = preg_replace('/[^0-9]+/', '', $urltest['fragment']);
				$way = 'from the fragment (post-###)';
			} elseif (is_string($urltest['fragment'])) {
				// ...or a string #title, a little more complicated
				$title = preg_replace('/[^a-z0-9]/i', '.', $urltest['fragment']);
				$sql = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title RLIKE %s", $title);
				if (! ($post_ID = $wpdb->get_var($sql)) ) {
					// returning unknown error '0' is better than die()ing
			  		return new IXR_Error(0, '');
				}
				$way = 'from the fragment (title)';
			}
		} else {
			// TODO: Attempt to extract a post ID from the given URL
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}
		$post_ID = (int) $post_ID;


		logIO("O","(PB) URL='$pagelinkedto' ID='$post_ID' Found='$way'");

		$post = get_post($post_ID);

		if ( !$post ) // Post_ID not found
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));

		if ( $post_ID == url_to_postid($pagelinkedfrom) )
			return new IXR_Error(0, __('The source URL and the target URL cannot both point to the same resource.'));

		// Check if pings are on
		if ( !pings_open($post) )
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));

		// Let's check that the remote site didn't already pingback this entry
		$wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_author_url = %s", $post_ID, $pagelinkedfrom) );

		if ( $wpdb->num_rows ) // We already have a Pingback from this URL
	  		return new IXR_Error(48, __('The pingback has already been registered.'));

		// very stupid, but gives time to the 'from' server to publish !
		sleep(1);

		// Let's check the remote site
		$linea = wp_remote_fopen( $pagelinkedfrom );
		if ( !$linea )
	  		return new IXR_Error(16, __('The source URL does not exist.'));

		$linea = apply_filters('pre_remote_source', $linea, $pagelinkedto);

		// Work around bug in strip_tags():
		$linea = str_replace('<!DOC', '<DOC', $linea);
		$linea = preg_replace( '/[\s\r\n\t]+/', ' ', $linea ); // normalize spaces
		$linea = preg_replace( "/ <(h1|h2|h3|h4|h5|h6|p|th|td|li|dt|dd|pre|caption|input|textarea|button|body)[^>]*>/", "\n\n", $linea );

		preg_match('|<title>([^<]*?)</title>|is', $linea, $matchtitle);
		$title = $matchtitle[1];
		if ( empty( $title ) )
			return new IXR_Error(32, __('We cannot find a title on that page.'));

		$linea = strip_tags( $linea, '<a>' ); // just keep the tag we need

		$p = explode( "\n\n", $linea );

		$preg_target = preg_quote($pagelinkedto);

		foreach ( $p as $para ) {
			if ( strpos($para, $pagelinkedto) !== false ) { // it exists, but is it a link?
				preg_match("|<a[^>]+?".$preg_target."[^>]*>([^>]+?)</a>|", $para, $context);

				// If the URL isn't in a link context, keep looking
				if ( empty($context) )
					continue;

				// We're going to use this fake tag to mark the context in a bit
				// the marker is needed in case the link text appears more than once in the paragraph
				$excerpt = preg_replace('|\</?wpcontext\>|', '', $para);

				// prevent really long link text
				if ( strlen($context[1]) > 100 )
					$context[1] = substr($context[1], 0, 100) . '...';

				$marker = '<wpcontext>'.$context[1].'</wpcontext>';    // set up our marker
				$excerpt= str_replace($context[0], $marker, $excerpt); // swap out the link for our marker
				$excerpt = strip_tags($excerpt, '<wpcontext>');        // strip all tags but our context marker
				$excerpt = trim($excerpt);
				$preg_marker = preg_quote($marker);
				$excerpt = preg_replace("|.*?\s(.{0,100}$preg_marker.{0,100})\s.*|s", '$1', $excerpt);
				$excerpt = strip_tags($excerpt); // YES, again, to remove the marker wrapper
				break;
			}
		}

		if ( empty($context) ) // Link to target not found
			return new IXR_Error(17, __('The source URL does not contain a link to the target URL, and so cannot be used as a source.'));

		$pagelinkedfrom = str_replace('&', '&amp;', $pagelinkedfrom);

		$context = '[...] ' . wp_specialchars( $excerpt ) . ' [...]';
		$pagelinkedfrom = $wpdb->escape( $pagelinkedfrom );

		$comment_post_ID = (int) $post_ID;
		$comment_author = $title;
		$this->escape($comment_author);
		$comment_author_url = $pagelinkedfrom;
		$comment_content = $context;
		$this->escape($comment_content);
		$comment_type = 'pingback';

		$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_content', 'comment_type');

		$comment_ID = wp_new_comment($commentdata);
		do_action('pingback_post', $comment_ID);

		return sprintf(__('Pingback from %1$s to %2$s registered. Keep the web talking! :-)'), $pagelinkedfrom, $pagelinkedto);
	}

	/**
	 * Retrieve array of URLs that pingbacked the given URL.
	 *
	 * Specs on http://www.aquarionics.com/misc/archives/blogite/0198.html
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function pingback_extensions_getPingbacks($args) {

		global $wpdb;

		do_action('xmlrpc_call', 'pingback.extensions.getPingbacks');

		$this->escape($args);

		$url = $args;

		$post_ID = url_to_postid($url);
		if (!$post_ID) {
			// We aren't sure that the resource is available and/or pingback enabled
	  		return new IXR_Error(33, __('The specified target URL cannot be used as a target. It either doesn\'t exist, or it is not a pingback-enabled resource.'));
		}

		$actual_post = wp_get_single_post($post_ID, ARRAY_A);

		if (!$actual_post) {
			// No such post = resource not found
	  		return new IXR_Error(32, __('The specified target URL does not exist.'));
		}

		$comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_author_url, comment_content, comment_author_IP, comment_type FROM $wpdb->comments WHERE comment_post_ID = %d", $post_ID) );

		if (!$comments) {
			return array();
		}

		$pingbacks = array();
		foreach($comments as $comment) {
			if ( 'pingback' == $comment->comment_type )
				$pingbacks[] = $comment->comment_author_url;
		}

		return $pingbacks;
	}
}

$wp_xmlrpc_server = new wp_xmlrpc_server();

?>
