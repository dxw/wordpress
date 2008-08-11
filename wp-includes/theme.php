<?php
/*
 * Theme/template/stylesheet functions.
 */

function get_stylesheet() {
	return apply_filters('stylesheet', get_option('stylesheet'));
}

function get_stylesheet_directory() {
	$stylesheet = get_stylesheet();
	$stylesheet_dir = get_theme_root() . "/$stylesheet";
	return apply_filters('stylesheet_directory', $stylesheet_dir, $stylesheet);
}

function get_stylesheet_directory_uri() {
	$stylesheet = get_stylesheet();
	$stylesheet_dir_uri = get_theme_root_uri() . "/$stylesheet";
	return apply_filters('stylesheet_directory_uri', $stylesheet_dir_uri, $stylesheet);
}

function get_stylesheet_uri() {
	$stylesheet_dir_uri = get_stylesheet_directory_uri();
	$stylesheet_uri = $stylesheet_dir_uri . "/style.css";
	return apply_filters('stylesheet_uri', $stylesheet_uri, $stylesheet_dir_uri);
}

function get_locale_stylesheet_uri() {
	global $wp_locale;
	$stylesheet_dir_uri = get_stylesheet_directory_uri();
	$dir = get_stylesheet_directory();
	$locale = get_locale();
	if ( file_exists("$dir/$locale.css") )
		$stylesheet_uri = "$stylesheet_dir_uri/$locale.css";
	elseif ( !empty($wp_locale->text_direction) && file_exists("$dir/{$wp_locale->text_direction}.css") )
		$stylesheet_uri = "$stylesheet_dir_uri/{$wp_locale->text_direction}.css";
	else
		$stylesheet_uri = '';
	return apply_filters('locale_stylesheet_uri', $stylesheet_uri, $stylesheet_dir_uri);
}

function get_template() {
	return apply_filters('template', get_option('template'));
}

function get_template_directory() {
	$template = get_template();
	$template_dir = get_theme_root() . "/$template";
	return apply_filters('template_directory', $template_dir, $template);
}

function get_template_directory_uri() {
	$template = get_template();
	$template_dir_uri = get_theme_root_uri() . "/$template";
	return apply_filters('template_directory_uri', $template_dir_uri, $template);
}

function get_theme_data( $theme_file ) {
	$themes_allowed_tags = array(
		'a' => array(
			'href' => array(),'title' => array()
			),
		'abbr' => array(
			'title' => array()
			),
		'acronym' => array(
			'title' => array()
			),
		'code' => array(),
		'em' => array(),
		'strong' => array()
	);

	$theme_data = implode( '', file( $theme_file ) );
	$theme_data = str_replace ( '\r', '\n', $theme_data );
	preg_match( '|Theme Name:(.*)$|mi', $theme_data, $theme_name );
	preg_match( '|Theme URI:(.*)$|mi', $theme_data, $theme_uri );
	preg_match( '|Description:(.*)$|mi', $theme_data, $description );

	if ( preg_match( '|Author URI:(.*)$|mi', $theme_data, $author_uri ) )
		$author_uri = clean_url( trim( $author_uri[1]) );
	else
		$author_uti = '';

	if ( preg_match( '|Template:(.*)$|mi', $theme_data, $template ) )
		$template = wp_kses( trim( $template[1] ), $themes_allowed_tags );
	else
		$template = '';

	if ( preg_match( '|Version:(.*)|i', $theme_data, $version ) )
		$version = wp_kses( trim( $version[1] ), $themes_allowed_tags );
	else
		$version = '';

	if ( preg_match('|Status:(.*)|i', $theme_data, $status) )
		$status = wp_kses( trim( $status[1] ), $themes_allowed_tags );
	else
		$status = 'publish';

	if ( preg_match('|Tags:(.*)|i', $theme_data, $tags) )
		$tags = array_map( 'trim', explode( ',', wp_kses( trim( $tags[1] ), array() ) ) );
	else
		$tags = array();

	$name = $theme = wp_kses( trim( $theme_name[1] ), $themes_allowed_tags );
	$theme_uri = clean_url( trim( $theme_uri[1] ) );
	$description = wptexturize( wp_kses( trim( $description[1] ), $themes_allowed_tags ) );

	if ( preg_match( '|Author:(.*)$|mi', $theme_data, $author_name ) ) {
		if ( empty( $author_uri ) ) {
			$author = wp_kses( trim( $author_name[1] ), $themes_allowed_tags );
		} else {
			$author = sprintf( '<a href="%1$s" title="%2$s">%3$s</a>', $author_uri, __( 'Visit author homepage' ), wp_kses( trim( $author_name[1] ), $themes_allowed_tags ) );
		}
	} else {
		$author = __('Anonymous');
	}

	return array( 'Name' => $name, 'Title' => $theme, 'URI' => $theme_uri, 'Description' => $description, 'Author' => $author, 'Version' => $version, 'Template' => $template, 'Status' => $status, 'Tags' => $tags );
}

function get_themes() {
	global $wp_themes, $wp_broken_themes;

	if ( isset($wp_themes) )
		return $wp_themes;

	$themes = array();
	$wp_broken_themes = array();
	$theme_loc = $theme_root = get_theme_root();
	if ( '/' != WP_CONTENT_DIR ) // don't want to replace all forward slashes, see Trac #4541
		$theme_loc = str_replace(WP_CONTENT_DIR, '', $theme_root);

	// Files in wp-content/themes directory and one subdir down
	$themes_dir = @ opendir($theme_root);
	if ( !$themes_dir )
		return false;

	while ( ($theme_dir = readdir($themes_dir)) !== false ) {
		if ( is_dir($theme_root . '/' . $theme_dir) && is_readable($theme_root . '/' . $theme_dir) ) {
			if ( $theme_dir{0} == '.' || $theme_dir == '..' || $theme_dir == 'CVS' )
				continue;
			$stylish_dir = @ opendir($theme_root . '/' . $theme_dir);
			$found_stylesheet = false;
			while ( ($theme_file = readdir($stylish_dir)) !== false ) {
				if ( $theme_file == 'style.css' ) {
					$theme_files[] = $theme_dir . '/' . $theme_file;
					$found_stylesheet = true;
					break;
				}
			}
			@closedir($stylish_dir);
			if ( !$found_stylesheet ) { // look for themes in that dir
				$subdir = "$theme_root/$theme_dir";
				$subdir_name = $theme_dir;
				$theme_subdir = @ opendir( $subdir );
				while ( ($theme_dir = readdir($theme_subdir)) !== false ) {
					if ( is_dir( $subdir . '/' . $theme_dir) && is_readable($subdir . '/' . $theme_dir) ) {
						if ( $theme_dir{0} == '.' || $theme_dir == '..' || $theme_dir == 'CVS' )
							continue;
						$stylish_dir = @ opendir($subdir . '/' . $theme_dir);
						$found_stylesheet = false;
						while ( ($theme_file = readdir($stylish_dir)) !== false ) {
							if ( $theme_file == 'style.css' ) {
								$theme_files[] = $subdir_name . '/' . $theme_dir . '/' . $theme_file;
								$found_stylesheet = true;
								break;
							}
						}
						@closedir($stylish_dir);
					}
				}
				@closedir($theme_subdir);
				$wp_broken_themes[$theme_dir] = array('Name' => $theme_dir, 'Title' => $theme_dir, 'Description' => __('Stylesheet is missing.'));
			}
		}
	}
	if ( is_dir( $theme_dir ) )
		@closedir( $theme_dir );

	if ( !$themes_dir || !$theme_files )
		return $themes;

	sort($theme_files);

	foreach ( (array) $theme_files as $theme_file ) {
		if ( !is_readable("$theme_root/$theme_file") ) {
			$wp_broken_themes[$theme_file] = array('Name' => $theme_file, 'Title' => $theme_file, 'Description' => __('File not readable.'));
			continue;
		}

		$theme_data = get_theme_data("$theme_root/$theme_file");

		$name        = $theme_data['Name'];
		$title       = $theme_data['Title'];
		$description = wptexturize($theme_data['Description']);
		$version     = $theme_data['Version'];
		$author      = $theme_data['Author'];
		$template    = $theme_data['Template'];
		$stylesheet  = dirname($theme_file);

		$screenshot = false;
		foreach ( array('png', 'gif', 'jpg', 'jpeg') as $ext ) {
			if (file_exists("$theme_root/$stylesheet/screenshot.$ext")) {
				$screenshot = "screenshot.$ext";
				break;
			}
		}

		if ( empty($name) ) {
			$name = dirname($theme_file);
			$title = $name;
		}

		if ( empty($template) ) {
			if ( file_exists(dirname("$theme_root/$theme_file/index.php")) )
				$template = dirname($theme_file);
			else
				continue;
		}

		$template = trim($template);

		if ( !file_exists("$theme_root/$template/index.php") ) {
			$parent_dir = dirname(dirname($theme_file));
			if ( file_exists("$theme_root/$parent_dir/$template/index.php") ) {
				$template = "$parent_dir/$template";
			} else {
				$wp_broken_themes[$name] = array('Name' => $name, 'Title' => $title, 'Description' => __('Template is missing.'));
				continue;
			}
		}

		$stylesheet_files = array();
		$template_files = array();

		$stylesheet_dir = @ dir("$theme_root/$stylesheet");
		if ( $stylesheet_dir ) {
			while ( ($file = $stylesheet_dir->read()) !== false ) {
				if ( !preg_match('|^\.+$|', $file) ) {
					if ( preg_match('|\.css$|', $file) )
						$stylesheet_files[] = "$theme_loc/$stylesheet/$file";
					elseif ( preg_match('|\.php$|', $file) )
						$template_files[] = "$theme_loc/$stylesheet/$file";
				}
			}
		}

		$template_dir = @ dir("$theme_root/$template");
		if ( $template_dir ) {
			while(($file = $template_dir->read()) !== false) {
				if ( !preg_match('|^\.+$|', $file) && preg_match('|\.php$|', $file) )
					$template_files[] = "$theme_loc/$template/$file";
			}
		}

		$template_dir = dirname($template_files[0]);
		$stylesheet_dir = dirname($stylesheet_files[0]);

		if ( empty($template_dir) )
			$template_dir = '/';
		if ( empty($stylesheet_dir) )
			$stylesheet_dir = '/';

		// Check for theme name collision.  This occurs if a theme is copied to
		// a new theme directory and the theme header is not updated.  Whichever
		// theme is first keeps the name.  Subsequent themes get a suffix applied.
		// The Default and Classic themes always trump their pretenders.
		if ( isset($themes[$name]) ) {
			if ( ('WordPress Default' == $name || 'WordPress Classic' == $name) &&
					 ('default' == $stylesheet || 'classic' == $stylesheet) ) {
				// If another theme has claimed to be one of our default themes, move
				// them aside.
				$suffix = $themes[$name]['Stylesheet'];
				$new_name = "$name/$suffix";
				$themes[$new_name] = $themes[$name];
				$themes[$new_name]['Name'] = $new_name;
			} else {
				$name = "$name/$stylesheet";
			}
		}

		$themes[$name] = array('Name' => $name, 'Title' => $title, 'Description' => $description, 'Author' => $author, 'Version' => $version, 'Template' => $template, 'Stylesheet' => $stylesheet, 'Template Files' => $template_files, 'Stylesheet Files' => $stylesheet_files, 'Template Dir' => $template_dir, 'Stylesheet Dir' => $stylesheet_dir, 'Status' => $theme_data['Status'], 'Screenshot' => $screenshot, 'Tags' => $theme_data['Tags']);
	}

	// Resolve theme dependencies.
	$theme_names = array_keys($themes);

	foreach ( (array) $theme_names as $theme_name ) {
		$themes[$theme_name]['Parent Theme'] = '';
		if ( $themes[$theme_name]['Stylesheet'] != $themes[$theme_name]['Template'] ) {
			foreach ( (array) $theme_names as $parent_theme_name ) {
				if ( ($themes[$parent_theme_name]['Stylesheet'] == $themes[$parent_theme_name]['Template']) && ($themes[$parent_theme_name]['Template'] == $themes[$theme_name]['Template']) ) {
					$themes[$theme_name]['Parent Theme'] = $themes[$parent_theme_name]['Name'];
					break;
				}
			}
		}
	}

	$wp_themes = $themes;

	return $themes;
}

function get_theme($theme) {
	$themes = get_themes();

	if ( array_key_exists($theme, $themes) )
		return $themes[$theme];

	return NULL;
}

function get_current_theme() {
	if ( $theme = get_option('current_theme') )
		return $theme;

	$themes = get_themes();
	$theme_names = array_keys($themes);
	$current_template = get_option('template');
	$current_stylesheet = get_option('stylesheet');
	$current_theme = 'WordPress Default';

	if ( $themes ) {
		foreach ( (array) $theme_names as $theme_name ) {
			if ( $themes[$theme_name]['Stylesheet'] == $current_stylesheet &&
					$themes[$theme_name]['Template'] == $current_template ) {
				$current_theme = $themes[$theme_name]['Name'];
				break;
			}
		}
	}

	update_option('current_theme', $current_theme);

	return $current_theme;
}

function get_theme_root() {
	return apply_filters('theme_root', WP_CONTENT_DIR . "/themes");
}

function get_theme_root_uri() {
	return apply_filters('theme_root_uri', content_url('themes'), get_option('siteurl'));
}

function get_query_template($type) {
	$template = '';
	$type = preg_replace( '|[^a-z0-9-]+|', '', $type );
	if ( file_exists(STYLESHEETPATH . "/{$type}.php") )
		$template = STYLESHEETPATH . "/{$type}.php";
	elseif ( file_exists(TEMPLATEPATH . "/{$type}.php") )
		$template = TEMPLATEPATH . "/{$type}.php";

	return apply_filters("{$type}_template", $template);
}

function get_404_template() {
	return get_query_template('404');
}

function get_archive_template() {
	return get_query_template('archive');
}

function get_author_template() {
	return get_query_template('author');
}

function get_category_template() {
	$template = '';
	if ( file_exists(STYLESHEETPATH . "/category-" . absint( get_query_var('cat') ) . '.php') )
		$template = STYLESHEETPATH . "/category-" . absint( get_query_var('cat') ) . '.php';
	elseif ( file_exists(TEMPLATEPATH . "/category-" . absint( get_query_var('cat') ) . '.php') )
		$template = TEMPLATEPATH . "/category-" . absint( get_query_var('cat') ) . '.php';
	elseif ( file_exists(STYLESHEETPATH . "/category.php") )
		$template = STYLESHEETPATH . "/category.php";
	elseif ( file_exists(TEMPLATEPATH . "/category.php") )
		$template = TEMPLATEPATH . "/category.php";

	return apply_filters('category_template', $template);
}

function get_tag_template() {
	$template = '';
	if ( file_exists(STYLESHEETPATH . "/tag-" . get_query_var('tag') . '.php') )
		$template = STYLESHEETPATH . "/tag-" . get_query_var('tag') . '.php';
	elseif ( file_exists(TEMPLATEPATH . "/tag-" . get_query_var('tag') . '.php') )
		$template = TEMPLATEPATH . "/tag-" . get_query_var('tag') . '.php';
	elseif ( file_exists(STYLESHEETPATH . "/tag.php") )
		$template = STYLESHEETPATH . "/tag.php";
	elseif ( file_exists(TEMPLATEPATH . "/tag.php") )
		$template = TEMPLATEPATH . "/tag.php";

	return apply_filters('tag_template', $template);
}

function get_taxonomy_template() {
	$template = '';
	$taxonomy = get_query_var('taxonomy');
	$term = get_query_var('term');
	if ( $taxonomy && $term && file_exists(STYLESHEETPATH . "/taxonomy-$taxonomy-$term.php") )
		$template = STYLESHEETPATH . "/taxonomy-$taxonomy-$term.php";
	elseif ( $taxonomy && $term && file_exists(TEMPLATEPATH . "/taxonomy-$taxonomy-$term.php") )
		$template = TEMPLATEPATH . "/taxonomy-$taxonomy-$term.php";
	elseif ( $taxonomy && file_exists(STYLESHEETPATH . "/taxonomy-$taxonomy.php") )
		$template = STYLESHEETPATH . "/taxonomy-$taxonomy.php";
	elseif ( $taxonomy && file_exists(TEMPLATEPATH . "/taxonomy-$taxonomy.php") )
		$template = TEMPLATEPATH . "/taxonomy-$taxonomy.php";
	elseif ( file_exists(STYLESHEETPATH . "/taxonomy.php") )
		$template = STYLESHEETPATH . "/taxonomy.php";
	elseif ( file_exists(TEMPLATEPATH . "/taxonomy.php") )
		$template = TEMPLATEPATH . "/taxonomy.php";

	return apply_filters('taxonomy_template', $template);
}

function get_date_template() {
	return get_query_template('date');
}

function get_home_template() {
	$template = '';

	if ( file_exists(STYLESHEETPATH . "/home.php") )
		$template = STYLESHEETPATH . "/home.php";
	elseif ( file_exists(TEMPLATEPATH . "/home.php") )
		$template = TEMPLATEPATH . "/home.php";
	elseif ( file_exists(STYLESHEETPATH . "/index.php") )
		$template = STYLESHEETPATH . "/index.php";
	elseif ( file_exists(TEMPLATEPATH . "/index.php") )
		$template = TEMPLATEPATH . "/index.php";

	return apply_filters('home_template', $template);
}

function get_page_template() {
	global $wp_query;

	$id = (int) $wp_query->post->ID;
	$template = get_post_meta($id, '_wp_page_template', true);

	if ( 'default' == $template )
		$template = '';

	if ( !empty($template) && !validate_file($template) && file_exists(STYLESHEETPATH . "/$template") )
		$template = STYLESHEETPATH . "/$template";
	elseif ( !empty($template) && !validate_file($template) && file_exists(TEMPLATEPATH . "/$template") )
		$template = TEMPLATEPATH . "/$template";
	elseif ( file_exists(STYLESHEETPATH . "/page.php") )
		$template = STYLESHEETPATH . "/page.php";
	elseif ( file_exists(TEMPLATEPATH . "/page.php") )
		$template = TEMPLATEPATH . "/page.php";
	else
		$template = '';

	return apply_filters('page_template', $template);
}

function get_paged_template() {
	return get_query_template('paged');
}

function get_search_template() {
	return get_query_template('search');
}

function get_single_template() {
	return get_query_template('single');
}

function get_attachment_template() {
	global $posts;
	$type = explode('/', $posts[0]->post_mime_type);
	if ( $template = get_query_template($type[0]) )
		return $template;
	elseif ( $template = get_query_template($type[1]) )
		return $template;
	elseif ( $template = get_query_template("$type[0]_$type[1]") )
		return $template;
	else
		return get_query_template('attachment');
}

function get_comments_popup_template() {
	if ( file_exists( STYLESHEETPATH . '/comments-popup.php') )
		$template = STYLESHEETPATH . '/comments-popup.php';
	elseif ( file_exists( TEMPLATEPATH . '/comments-popup.php') )
		$template = TEMPLATEPATH . '/comments-popup.php';
	else
		$template = get_theme_root() . '/default/comments-popup.php';

	return apply_filters('comments_popup_template', $template);
}

function load_template($_template_file) {
	global $posts, $post, $wp_did_header, $wp_did_template_redirect, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID;

	if ( is_array($wp_query->query_vars) )
		extract($wp_query->query_vars, EXTR_SKIP);

	require_once($_template_file);
}

function locale_stylesheet() {
	$stylesheet = get_locale_stylesheet_uri();
	if ( empty($stylesheet) )
		return;
	echo '<link rel="stylesheet" href="' . $stylesheet . '" type="text/css" media="screen" />';
}

function preview_theme() {
	if ( ! (isset($_GET['template']) && isset($_GET['preview'])) )
		return;

	if ( !current_user_can( 'switch_themes' ) )
		return;

	$_GET[template] = preg_replace('|[^a-z0-9_.-]|i', '', $_GET[template]);

	if ( validate_file($_GET[template]) )
		return;

	add_filter('template', create_function('', "return '$_GET[template]';") );

	if ( isset($_GET['stylesheet']) ) {
		$_GET[stylesheet] = preg_replace('|[^a-z0-9_.-]|i', '', $_GET[stylesheet]);
		if ( validate_file($_GET[stylesheet]) )
			return;
		add_filter('stylesheet', create_function('', "return '$_GET[stylesheet]';") );
	}

	ob_start( 'preview_theme_ob_filter' );
}
add_action('setup_theme', 'preview_theme');

function preview_theme_ob_filter( $content ) {
	return preg_replace_callback( "|(<a.*?href=([\"']))(.*?)([\"'].*?>)|", 'preview_theme_ob_filter_callback', $content );
}

function preview_theme_ob_filter_callback( $matches ) {
	if (
		( false !== strpos($matches[3], '/wp-admin/') )
	||
		( false !== strpos($matches[3], '://') && 0 !== strpos($matches[3], get_option('home')) )
	||
		( false !== strpos($matches[3], '/feed/') )
	||
		( false !== strpos($matches[3], '/trackback/') )
	)
		return $matches[1] . "#$matches[2] onclick=$matches[2]return false;" . $matches[4];

	$link = add_query_arg( array('preview' => 1, 'template' => $_GET['template'], 'stylesheet' => @$_GET['stylesheet'] ), $matches[3] );
	if ( 0 === strpos($link, 'preview=1') )
		$link = "?$link";
	return $matches[1] . attribute_escape( $link ) . $matches[4];
}

function switch_theme($template, $stylesheet) {
	update_option('template', $template);
	update_option('stylesheet', $stylesheet);
	delete_option('current_theme');
	$theme = get_current_theme();
	do_action('switch_theme', $theme);
}

function validate_current_theme() {
	// Don't validate during an install/upgrade.
	if ( defined('WP_INSTALLING') )
		return true;

	if ( get_template() != 'default' && !file_exists(get_template_directory() . '/index.php') ) {
		switch_theme('default', 'default');
		return false;
	}

	if ( get_stylesheet() != 'default' && !file_exists(get_template_directory() . '/style.css') ) {
		switch_theme('default', 'default');
		return false;
	}

	return true;
}

function get_theme_mod($name, $default = false) {
	$theme = get_current_theme();

	$mods = get_option("mods_$theme");

	if ( isset($mods[$name]) )
		return apply_filters( "theme_mod_$name", $mods[$name] );

	return apply_filters( "theme_mod_$name", sprintf($default, get_template_directory_uri(), get_stylesheet_directory_uri()) );
}

function set_theme_mod($name, $value) {
	$theme = get_current_theme();

	$mods = get_option("mods_$theme");

	$mods[$name] = $value;

	update_option("mods_$theme", $mods);
	wp_cache_delete("mods_$theme", 'options');
}

function remove_theme_mod( $name ) {
	$theme = get_current_theme();

	$mods = get_option("mods_$theme");

	if ( !isset($mods[$name]) )
		return;

	unset($mods[$name]);

	if ( empty($mods) )
		return remove_theme_mods();

	update_option("mods_$theme", $mods);
	wp_cache_delete("mods_$theme", 'options');
}

function remove_theme_mods() {
	$theme = get_current_theme();

	delete_option("mods_$theme");
}

function get_header_textcolor() {
	return get_theme_mod('header_textcolor', HEADER_TEXTCOLOR);
}

function header_textcolor() {
	echo get_header_textcolor();
}

function get_header_image() {
	return get_theme_mod('header_image', HEADER_IMAGE);
}

function header_image() {
	echo get_header_image();
}

function add_custom_image_header($header_callback, $admin_header_callback) {
	if ( ! empty($header_callback) )
		add_action('wp_head', $header_callback);

	if ( ! is_admin() )
		return;
	require_once(ABSPATH . 'wp-admin/custom-header.php');
	$GLOBALS['custom_image_header'] =& new Custom_Image_Header($admin_header_callback);
	add_action('admin_menu', array(&$GLOBALS['custom_image_header'], 'init'));
}

?>
