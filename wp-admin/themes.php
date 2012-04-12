<?php
/**
 * Themes administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('./admin.php');

if ( !current_user_can('switch_themes') && !current_user_can('edit_theme_options') )
	wp_die( __( 'Cheatin&#8217; uh?' ) );

$wp_list_table = _get_list_table('WP_Themes_List_Table');

if ( current_user_can( 'switch_themes' ) && isset($_GET['action'] ) ) {
	if ( 'activate' == $_GET['action'] ) {
		check_admin_referer('switch-theme_' . $_GET['template']);
		switch_theme($_GET['template'], $_GET['stylesheet']);
		wp_redirect( admin_url('themes.php?activated=true') );
		exit;
	} elseif ( 'delete' == $_GET['action'] ) {
		check_admin_referer('delete-theme_' . $_GET['template']);
		if ( !current_user_can('delete_themes') )
			wp_die( __( 'Cheatin&#8217; uh?' ) );
		delete_theme($_GET['template']);
		wp_redirect( admin_url('themes.php?deleted=true') );
		exit;
	}
}

$wp_list_table->prepare_items();

$title = __('Manage Themes');
$parent_file = 'themes.php';

if ( current_user_can( 'switch_themes' ) ) :

$help_manage = '<p>' . __('Aside from the default theme included with your WordPress installation, themes are designed and developed by third parties.') . '</p>' .
	'<p>' . __('You can see your active theme at the top of the screen. Below are the other themes you have installed that are not currently in use. You can see what your site would look like with one of these themes by clicking the Preview link. To change themes, click the Activate link.') . '</p>';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $help_manage,
) );

if ( current_user_can( 'install_themes' ) ) {
	if ( is_multisite() ) {
		$help_install = '<p>' . __('Installing themes on Multisite can only be done from the Network Admin section.') . '</p>';
	} else {
		$help_install = '<p>' . sprintf( __('If you would like to see more themes to choose from, click on the &#8220;Install Themes&#8221; tab and you will be able to browse or search for additional themes from the <a href="%s" target="_blank">WordPress.org Theme Directory</a>. Themes in the WordPress.org Theme Directory are designed and developed by third parties, and are compatible with the license WordPress uses. Oh, and they&#8217;re free!'), 'http://wordpress.org/extend/themes/' ) . '</p>';
	}

	get_current_screen()->add_help_tab( array(
		'id'      => 'adding-themes',
		'title'   => __('Adding Themes'),
		'content' => $help_install,
	) );
}

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="http://codex.wordpress.org/Using_Themes" target="_blank">Documentation on Using Themes</a>') . '</p>' .
	'<p>' . __('<a href="http://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
);

wp_enqueue_script( 'theme' );
wp_customize_loader();

endif;

require_once('./admin-header.php');
?>

<?php if ( ! validate_current_theme() ) : ?>
<div id="message1" class="updated"><p><?php _e('The active theme is broken. Reverting to the default theme.'); ?></p></div>
<?php elseif ( isset($_GET['activated']) ) :
		if ( isset($wp_registered_sidebars) && count( (array) $wp_registered_sidebars ) && current_user_can('edit_theme_options') ) { ?>
<div id="message2" class="updated"><p><?php printf( __('New theme activated. This theme supports widgets, please visit the <a href="%s">widgets settings</a> screen to configure them.'), admin_url( 'widgets.php' ) ); ?></p></div><?php
		} else { ?>
<div id="message2" class="updated"><p><?php printf( __( 'New theme activated. <a href="%s">Visit site</a>' ), home_url( '/' ) ); ?></p></div><?php
		}
	elseif ( isset($_GET['deleted']) ) : ?>
<div id="message3" class="updated"><p><?php _e('Theme deleted.') ?></p></div>
<?php endif; ?>

<div class="wrap"><?php
screen_icon();
if ( ! is_multisite() && current_user_can( 'install_themes' ) ) : ?>
<h2 class="nav-tab-wrapper">
<a href="themes.php" class="nav-tab nav-tab-active"><?php echo esc_html( $title ); ?></a><a href="<?php echo admin_url( 'theme-install.php'); ?>" class="nav-tab"><?php echo esc_html_x('Install Themes', 'theme'); ?></a>
<?php else : ?>
<h2><?php echo esc_html( $title ); ?>
<?php endif; ?>
</h2>
<?php

$ct = wp_get_theme();
$screenshot = $ct->get_screenshot();
$class = $screenshot ? 'has-screenshot' : '';

?>
<div id="current-theme" class="<?php echo esc_attr( $class ); ?>">
	<?php if ( $screenshot ) : ?>
		<img src="<?php echo esc_url( $screenshot ); ?>" alt="<?php esc_attr_e( 'Current theme preview' ); ?>" />
	<?php endif; ?>

	<h3><?php _e('Current Theme'); ?></h3>
	<h4>
		<?php echo $ct->display('Name'); ?>
	</h4>

	<div>
		<ul class="theme-info">
			<li><?php printf( __('By %s'), $ct->display('Author') ); ?></li>
			<li><?php printf( __('Version %s'), $ct->display('Version') ); ?></li>
		</ul>
		<p class="theme-description"><?php echo $ct->display('Description'); ?></p>
		<?php theme_update_available( $ct ); ?>
	</div>

<div class="theme-options">
	<a href="#" class="load-customize hide-if-no-js" data-customize-template="<?php echo esc_attr( $ct->get_template() ); ?>" data-customize-stylesheet="<?php echo esc_attr( $ct->get_stylesheet() ); ?>" title="<?php echo esc_attr( sprintf( __( 'Customize &#8220;%s&#8221;' ), $ct->get('Name') ) ); ?>"><?php _e( 'Customize' )?></a>
	<span><?php _e( 'Options:' )?></span>
	<?php
	// Pretend you didn't see this.
	$options = array();
	if ( is_array( $submenu ) && isset( $submenu['themes.php'] ) ) {
		foreach ( (array) $submenu['themes.php'] as $item) {
			$class = '';
			if ( 'themes.php' == $item[2] || 'theme-editor.php' == $item[2] )
				continue;
			// 0 = name, 1 = capability, 2 = file
			if ( ( strcmp($self, $item[2]) == 0 && empty($parent_file)) || ($parent_file && ($item[2] == $parent_file)) )
				$class = ' class="current"';
			if ( !empty($submenu[$item[2]]) ) {
				$submenu[$item[2]] = array_values($submenu[$item[2]]); // Re-index.
				$menu_hook = get_plugin_page_hook($submenu[$item[2]][0][2], $item[2]);
				if ( file_exists(WP_PLUGIN_DIR . "/{$submenu[$item[2]][0][2]}") || !empty($menu_hook))
					$options[] = "<a href='admin.php?page={$submenu[$item[2]][0][2]}'$class>{$item[0]}</a>";
				else
					$options[] = "<a href='{$submenu[$item[2]][0][2]}'$class>{$item[0]}</a>";
			} else if ( current_user_can($item[1]) ) {
				if ( file_exists(ABSPATH . 'wp-admin/' . $item[2]) ) {
					$options[] = "<a href='{$item[2]}'$class>{$item[0]}</a>";
				} else {
					$options[] = "<a href='themes.php?page={$item[2]}'$class>{$item[0]}</a>";
				}
			}
		}
	}

	?>
	<ul>
		<?php foreach ( $options as $option ) : ?>
			<li><?php echo $option; ?></li>
		<?php endforeach; ?>
	</ul>
</div>

</div>

<br class="clear" />
<?php
if ( ! current_user_can( 'switch_themes' ) ) {
	echo '</div>';
	require( './admin-footer.php' );
	exit;
}
?>

<h3 class="available-themes"><?php _e('Available Themes'); ?></h3>

<?php if ( !empty( $_REQUEST['s'] ) || !empty( $_REQUEST['features'] ) || $wp_list_table->has_items() ) : ?>

<form class="search-form filter-form" action="" method="get">

<p class="search-box">
	<label class="screen-reader-text" for="theme-search-input"><?php _e('Search Installed Themes'); ?>:</label>
	<input type="search" id="theme-search-input" name="s" value="<?php _admin_search_query(); ?>" />
	<?php submit_button( __( 'Search Installed Themes' ), 'button', false, false, array( 'id' => 'search-submit' ) ); ?>
	<a id="filter-click" href="?filter=1"><?php _e( 'Feature Filter' ); ?></a>
</p>

<br class="clear"/>

<div id="filter-box" style="<?php if ( empty($_REQUEST['filter']) ) echo 'display: none;'; ?>">
<?php $feature_list = get_theme_feature_list(); ?>
	<div class="feature-filter">
		<p class="install-help"><?php _e('Theme filters') ?></p>
	<?php if ( !empty( $_REQUEST['filter'] ) ) : ?>
		<input type="hidden" name="filter" value="1" />
	<?php endif; ?>
	<?php foreach ( $feature_list as $feature_name => $features ) :
			$feature_name = esc_html( $feature_name ); ?>

		<div class="feature-container">
			<div class="feature-name"><?php echo $feature_name ?></div>

			<ol class="feature-group">
				<?php foreach ( $features as $key => $feature ) :
						$feature_name = $feature;
						$feature_name = esc_html( $feature_name );
						$feature = esc_attr( $feature );
						?>
				<li>
					<input type="checkbox" name="features[]" id="feature-id-<?php echo $key; ?>" value="<?php echo $key; ?>" <?php checked( in_array( $key, $wp_list_table->features ) ); ?>/>
					<label for="feature-id-<?php echo $key; ?>"><?php echo $feature_name; ?></label>
				</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endforeach; ?>

	<div class="feature-container">
		<?php submit_button( __( 'Apply Filters' ), 'button-secondary submitter', false, false, array( 'id' => 'filter-submit' ) ); ?>
		&nbsp;
		<a id="mini-filter-click" href="<?php echo esc_url( remove_query_arg( array('filter', 'features', 'submit') ) ); ?>"><?php _e( 'Close filters' )?></a>
	</div>
	<br/>
	</div>
	<br class="clear"/>
</div>

<br class="clear" />

<?php endif; ?>

<?php $wp_list_table->display(); ?>

</form>
<br class="clear" />

<?php
// List broken themes, if any.
if ( ! is_multisite() && current_user_can('edit_themes') && $broken_themes = wp_get_themes( array( 'errors' => true ) ) ) {
?>

<h3><?php _e('Broken Themes'); ?></h3>
<p><?php _e('The following themes are installed but incomplete. Themes must have a stylesheet and a template.'); ?></p>

<table id="broken-themes">
	<tr>
		<th><?php _ex('Name', 'theme name'); ?></th>
		<th><?php _e('Description'); ?></th>
	</tr>
<?php
	$alt = '';
	foreach ( $broken_themes as $broken_theme ) {
		$alt = ('class="alternate"' == $alt) ? '' : 'class="alternate"';
		echo "
		<tr $alt>
			 <td>" . $broken_theme->get('Name') ."</td>
			 <td>" . $broken_theme->errors()->get_error_message() . "</td>
		</tr>";
	}
?>
</table>
<?php
}
?>
</div>

<?php require('./admin-footer.php'); ?>