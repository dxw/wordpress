<?php

require_once('admin.php');

require_once(ABSPATH . 'wp-admin/includes/dashboard.php');

wp_dashboard_setup();

function index_js() {
?>
<script type="text/javascript">
jQuery(function($) {
	var ajaxWidgets = {
		dashboard_incoming_links: 'incominglinks',
		dashboard_primary: 'devnews',
		dashboard_secondary: 'planetnews',
		dashboard_plugins: 'plugins'
	};
	$.each( ajaxWidgets, function(i,a) {
		var e = jQuery('#' + i + ' div.dashboard-widget-content').not('.dashboard-widget-control').find('.widget-loading');
		if ( e.size() ) { e.parent().load('index-extra.php?jax=' + a); }
	} );
});
</script>
<?php
}
add_action( 'admin_head', 'index_js' );

wp_enqueue_script( 'jquery' );
wp_admin_css( 'dashboard' );

$title = __('Dashboard');
$parent_file = 'index.php';
require_once('admin-header.php');

$today = current_time('mysql', 1);
?>

<div class="wrap">
<div class="pagehead"><a class="button new" href="/wp-admin/post-new.php">Write New Post</a></div>
<div id="dashboard-widgets-wrap">

<?php wp_dashboard(); ?>


</div><!-- dashboard-widgets-wrap -->

</div><!-- wrap -->

<?php require('./admin-footer.php'); ?>
