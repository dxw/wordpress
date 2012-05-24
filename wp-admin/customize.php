<?php
/**
 * Customize Controls
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

require_once( './admin.php' );
if ( ! current_user_can( 'edit_theme_options' ) )
	wp_die( __( 'Cheatin&#8217; uh?' ) );

global $wp_scripts, $wp_customize;

wp_reset_vars( array( 'theme' ) );

if ( ! $theme )
	$theme = get_stylesheet();

$registered = $wp_scripts->registered;
$wp_scripts = new WP_Scripts;
$wp_scripts->registered = $registered;

add_action( 'customize_controls_print_scripts',        'print_head_scripts', 20 );
add_action( 'customize_controls_print_footer_scripts', '_wp_footer_scripts'     );
add_action( 'customize_controls_print_styles',         'print_admin_styles', 20 );

do_action( 'customize_controls_init' );

wp_enqueue_script( 'customize-controls' );
wp_enqueue_style( 'customize-controls' );

do_action( 'customize_controls_enqueue_scripts' );

// Let's roll.
@header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));

wp_user_settings();
_wp_admin_html_begin();

$admin_title = sprintf( __( '%1$s &#8212; WordPress' ), strip_tags( sprintf( __( 'Customize %s' ), $wp_customize->theme()->display('Name') ) ) );
?><title><?php echo $admin_title; ?></title><?php

do_action( 'customize_controls_print_styles' );
do_action( 'customize_controls_print_scripts' );
?>
</head>
<body class="wp-full-overlay">
	<form id="customize-controls" class="wrap wp-full-overlay-sidebar">
		<?php wp_nonce_field( 'customize_controls' ); ?>
		<div id="customize-header-actions" class="wp-full-overlay-header">
			<?php
				$save_text = $wp_customize->is_theme_active() ? __( 'Save &amp; Publish' ) : __( 'Save &amp; Activate' );
				submit_button( $save_text, 'primary', 'save', false );
			?>
			<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" />
			<a class="back button" href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>">
				<?php _e( 'Cancel' ); ?>
			</a>
		</div>

		<div class="wp-full-overlay-sidebar-content">
			<div id="customize-info" class="customize-section">
				<div class="customize-section-title">
					<span class="preview-notice"><?php _e('You are previewing'); ?></span>
					<strong class="theme-name"><?php echo $wp_customize->theme()->display('Name'); ?></strong>
				</div>
				<div class="customize-section-content">
					<?php if ( $screenshot = $wp_customize->theme()->get_screenshot() ) : ?>
						<img class="theme-screenshot" src="<?php echo esc_url( $screenshot ); ?>" />
					<?php endif; ?>

					<?php if ( $wp_customize->theme()->get('Description') ): ?>
						<div class="theme-description"><?php echo $wp_customize->theme()->display('Description'); ?></div>
					<?php endif; ?>
				</div>
			</div>

			<div id="customize-theme-controls"><ul>
				<?php
				foreach ( $wp_customize->sections() as $section )
					$section->maybe_render();
				?>
			</ul></div>
		</div>

		<div id="customize-footer-actions" class="wp-full-overlay-footer">
			<a href="#" class="collapse-sidebar button-secondary" title="<?php esc_attr_e('Collapse Sidebar'); ?>">
				<span class="collapse-sidebar-label"><?php _e('Collapse'); ?></span>
				<span class="collapse-sidebar-arrow"></span>
			</a>
		</div>
	</form>
	<div id="customize-preview" class="wp-full-overlay-main"></div>
	<?php

	do_action( 'customize_controls_print_footer_scripts' );

	// If the frontend and the admin are served from the same domain, load the
	// preview over ssl if the customizer is being loaded over ssl. This avoids
	// insecure content warnings. This is not attempted if the admin and frontend
	// are on different domains to avoid the case where the frontend doesn't have
	// ssl certs. Domain mapping plugins can allow other urls in these conditions
	// using the customize_allowed_urls filter.

	$allowed_urls = array( home_url('/') );
	$admin_origin = parse_url( admin_url() );
	$home_origin  = parse_url( home_url() );

	if ( is_ssl() && ( $admin_origin[ 'host' ] == $home_origin[ 'host' ] ) )
		$allowed_urls[] = home_url( '/', 'https' );

	$allowed_urls = array_unique( apply_filters( 'customize_allowed_urls', $allowed_urls ) );

	$settings = array(
		'theme'    => array(
			'stylesheet' => $wp_customize->get_stylesheet(),
			'active'     => $wp_customize->is_theme_active(),
		),
		'url'      => array(
			'preview'  => esc_url( home_url( '/' ) ),
			'parent'   => esc_url( admin_url() ),
			'ajax'     => esc_url( admin_url( 'admin-ajax.php', 'relative' ) ),
			'allowed'  => array_map( 'esc_url', $allowed_urls ),
		),
		'settings' => array(),
		'controls' => array(),
	);

	foreach ( $wp_customize->settings() as $id => $setting ) {
		$settings['settings'][ $id ] = array(
			'value'     => $setting->js_value(),
			'transport' => $setting->transport,
		);
	}

	foreach ( $wp_customize->controls() as $id => $control ) {
		$control->to_json();
		$settings['controls'][ $id ] = $control->json;
	}

	?>
	<script type="text/javascript">
		var _wpCustomizeSettings = <?php echo json_encode( $settings ); ?>;
	</script>
</body>
</html>
