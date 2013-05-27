<?php
/**
 * WordPress Administration Template Header
 *
 * @package WordPress
 * @subpackage Administration
 */

@header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
if ( ! defined( 'WP_ADMIN' ) )
	require_once( './admin.php' );

// In case admin-header.php is included in a function.
global $title, $hook_suffix, $current_screen, $wp_locale, $pagenow, $wp_version,
	$current_site, $update_title, $total_update_count, $parent_file;

// Catch plugins that include admin-header.php before admin.php completes.
if ( empty( $current_screen ) )
	set_current_screen();

get_admin_page_title();
$title = esc_html( strip_tags( $title ) );

if ( is_network_admin() )
	$admin_title = __( 'Network Admin' );
elseif ( is_user_admin() )
	$admin_title = __( 'Global Dashboard' );
else
	$admin_title = get_bloginfo( 'name' );

if ( $admin_title == $title )
	$admin_title = sprintf( __( '%1$s &#8212; WordPress' ), $title );
else
	$admin_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $title, $admin_title );

$admin_title = apply_filters( 'admin_title', $admin_title, $title );

wp_user_settings();

_wp_admin_html_begin();
?>
<title><?php echo $admin_title; ?></title>
<?php

wp_enqueue_style( 'colors' );
wp_enqueue_style( 'ie' );
wp_enqueue_script('utils');

$admin_body_class = preg_replace('/[^a-z0-9_-]+/i', '-', $hook_suffix);
?>
<script type="text/javascript">
addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
	pagenow = '<?php echo $current_screen->id; ?>',
	typenow = '<?php echo $current_screen->post_type; ?>',
	adminpage = '<?php echo $admin_body_class; ?>',
	thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
	decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>',
	isRtl = <?php echo (int) is_rtl(); ?>;
</script>
<?php

do_action('admin_enqueue_scripts', $hook_suffix);
do_action("admin_print_styles-$hook_suffix");
do_action('admin_print_styles');
do_action("admin_print_scripts-$hook_suffix");
do_action('admin_print_scripts');
do_action("admin_head-$hook_suffix");
do_action('admin_head');

if ( get_user_setting('mfold') == 'f' )
	$admin_body_class .= ' folded';

if ( !get_user_setting('unfold') )
	$admin_body_class .= ' auto-fold';

if ( is_admin_bar_showing() )
	$admin_body_class .= ' admin-bar';

if ( is_rtl() )
	$admin_body_class .= ' rtl';

$admin_body_class .= ' branch-' . str_replace( array( '.', ',' ), '-', floatval( $wp_version ) );
$admin_body_class .= ' version-' . str_replace( '.', '-', preg_replace( '/^([.0-9]+).*/', '$1', $wp_version ) );
$admin_body_class .= ' admin-color-' . sanitize_html_class( get_user_option( 'admin_color' ), 'fresh' );
$admin_body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

if ( isset( $post ) && is_a( $post, 'WP_Post' ) && post_type_supports( get_post_type(), 'post-formats' ) ) {
	$post_format = get_post_format();

	if ( ! empty( $_REQUEST['format'] ) && in_array( $_REQUEST['format'], get_post_format_slugs() ) )
		$post_format = $_REQUEST['format'];

	if ( ! $post_format )
		$post_format = 'standard';

	$admin_body_class .= ' wp-format-' . $post_format;

	$show_post_format_ui = false;

	if ( apply_filters( 'enable_post_format_ui', true, $post ) ) {

		// If the user has explicitly set a screen option, use it, otherwise the UI is shown
		// when the theme supports formats, or if the site has formats assigned to posts.
		$post_format_user_option = get_user_option( 'post_formats_' . $post->post_type );
		if ( false !== $post_format_user_option )
			$show_post_format_ui = (bool) $post_format_user_option;
		else
			$show_post_format_ui = current_theme_supports( 'post-formats' ) || (bool) get_terms( 'post_format', array( 'number' => 1 ) );

		if ( ! $show_post_format_ui ) {
			$meta = get_post_format_meta( $post->ID );
			$format_meta_keys = array(
				'link'  => array( 'linkurl' ),
				'image' => array( 'url', 'image' ),
				'quote' => array( 'quote_source_name', 'quote_source_url' ),
				'video' => array( 'video_embed' ),
				'audio' => array( 'audio_embed' ),
			);

			// If there's any structured post format data, enforce the UI display.
			$format_meta_keys = isset( $format_meta_keys[ get_post_format() ] ) ? $format_meta_keys[ get_post_format() ] : array();
			foreach ( $format_meta_keys as $key )
				if ( ! empty( $meta[ $key ] ) )
					$show_post_format_ui = true;
		}
	}

	if ( $show_post_format_ui )
		$admin_body_class .= ' wp-post-format-show-ui';
}

if ( wp_is_mobile() )
	$admin_body_class .= ' mobile';

$admin_body_class .= ' no-customize-support';

?>
</head>
<body class="wp-admin wp-core-ui no-js <?php echo apply_filters( 'admin_body_class', '' ) . " $admin_body_class"; ?>">
<script type="text/javascript">
	document.body.className = document.body.className.replace('no-js','js');
</script>

<?php
// Make sure the customize body classes are correct as early as possible.
if ( current_user_can( 'edit_theme_options' ) )
	wp_customize_support_script();
?>

<div id="wpwrap">
<a tabindex="1" href="#wpbody-content" class="screen-reader-shortcut"><?php _e('Skip to main content'); ?></a>
<?php require(ABSPATH . 'wp-admin/menu-header.php'); ?>
<div id="wpcontent">

<?php
do_action('in_admin_header');
?>

<div id="wpbody">
<?php
unset($title_class, $blog_name, $total_update_count, $update_title);

$current_screen->set_parentage( $parent_file );

?>

<div id="wpbody-content" aria-label="<?php esc_attr_e('Main content'); ?>" tabindex="0">
<?php

$current_screen->render_screen_meta();

if ( is_network_admin() )
	do_action('network_admin_notices');
elseif ( is_user_admin() )
	do_action('user_admin_notices');
else
	do_action('admin_notices');

do_action('all_admin_notices');

if ( $parent_file == 'options-general.php' )
	require(ABSPATH . 'wp-admin/options-head.php');
