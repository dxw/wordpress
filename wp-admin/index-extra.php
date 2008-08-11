<?php
/**
 * Handle default dashboard widgets options AJAX.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Load WordPress Bootstrap */
require_once('admin.php');

/** Load WordPress Administration Dashboard API */
require( 'includes/dashboard.php' );

/** Load Magpie RSS API or custom RSS API */
require_once (ABSPATH . WPINC . '/rss.php');

@header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));

switch ( $_GET['jax'] ) {

case 'incominglinks' :
	wp_dashboard_incoming_links_output();
	break;

case 'devnews' :
	wp_dashboard_rss_output( 'dashboard_primary' );
	break;

case 'planetnews' :
	wp_dashboard_secondary_output();
	break;

case 'plugins' :
	wp_dashboard_plugins_output();
	break;

}

?>