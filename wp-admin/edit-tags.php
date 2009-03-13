<?php
/**
 * Edit Tags Administration Panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

$title = __('Tags');

wp_reset_vars( array('action', 'tag') );

if ( isset( $_GET['action'] ) && isset($_GET['delete_tags']) && ( 'delete' == $_GET['action'] || 'delete' == $_GET['action2'] ) )
	$action = 'bulk-delete';

switch($action) {

case 'addtag':

	check_admin_referer('add-tag');

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	$ret = wp_insert_term($_POST['name'], 'post_tag', $_POST);
	if ( $ret && !is_wp_error( $ret ) ) {
		wp_redirect('edit-tags.php?message=1#addtag');
	} else {
		wp_redirect('edit-tags.php?message=4#addtag');
	}
	exit;
break;

case 'delete':
	$tag_ID = (int) $_GET['tag_ID'];
	check_admin_referer('delete-tag_' .  $tag_ID);

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	wp_delete_term( $tag_ID, 'post_tag');

	$location = 'edit-tags.php';
	if ( $referer = wp_get_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	$location = add_query_arg('message', 2, $location);
	wp_redirect($location);
	exit;

break;

case 'bulk-delete':
	check_admin_referer('bulk-tags');

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	$tags = $_GET['delete_tags'];
	foreach( (array) $tags as $tag_ID ) {
		wp_delete_term( $tag_ID, 'post_tag');
	}

	$location = 'edit-tags.php';
	if ( $referer = wp_get_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	$location = add_query_arg('message', 6, $location);
	wp_redirect($location);
	exit;

break;

case 'edit':
	$title = __('Edit Tag');

	require_once ('admin-header.php');
	$tag_ID = (int) $_GET['tag_ID'];

	$tag = get_term($tag_ID, 'post_tag', OBJECT, 'edit');
	include('edit-tag-form.php');

break;

case 'editedtag':
	$tag_ID = (int) $_POST['tag_ID'];
	check_admin_referer('update-tag_' . $tag_ID);

	if ( !current_user_can('manage_categories') )
		wp_die(__('Cheatin&#8217; uh?'));

	$ret = wp_update_term($tag_ID, 'post_tag', $_POST);

	$location = 'edit-tags.php';
	if ( $referer = wp_get_original_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	if ( $ret && !is_wp_error( $ret ) )
		$location = add_query_arg('message', 3, $location);
	else
		$location = add_query_arg('message', 5, $location);

	wp_redirect($location);
	exit;
break;

default:

if ( isset($_GET['_wp_http_referer']) && ! empty($_GET['_wp_http_referer']) ) {
	 wp_redirect( remove_query_arg( array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI']) ) );
	 exit;
}

$can_manage = current_user_can('manage_categories');

wp_enqueue_script('admin-tags');
if ( $can_manage )
	wp_enqueue_script('inline-edit-tax');

require_once ('admin-header.php');

$messages[1] = __('Tag added.');
$messages[2] = __('Tag deleted.');
$messages[3] = __('Tag updated.');
$messages[4] = __('Tag not added.');
$messages[5] = __('Tag not updated.');
$messages[6] = __('Tags deleted.'); ?>

<div class="wrap nosubsub">
<?php screen_icon(); ?>
<h2><?php echo wp_specialchars( $title );
if ( isset($_GET['s']) && $_GET['s'] )
	printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', wp_specialchars( stripslashes($_GET['s']) ) ); ?>
</h2>

<?php if ( isset($_GET['message']) && ( $msg = (int) $_GET['message'] ) ) : ?>
<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
endif; ?>

<form class="search-form" action="" method="get">
<p class="search-box">
	<label class="hidden" for="tag-search-input"><?php _e( 'Search Tags' ); ?>:</label>
	<input type="text" class="search-input" id="tag-search-input" name="s" value="<?php _admin_search_query(); ?>" />
	<input type="submit" value="<?php _e( 'Search Tags' ); ?>" class="button" />
</p>
</form>
<br class="clear" />

<div id="col-container">

<div id="col-right">
<div class="col-wrap">
<form id="posts-filter" action="" method="get">
<div class="tablenav">
<?php
$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
if ( empty($pagenum) )
	$pagenum = 1;

$tagsperpage = apply_filters("tagsperpage",20);

$page_links = paginate_links( array(
	'base' => add_query_arg( 'pagenum', '%#%' ),
	'format' => '',
	'prev_text' => __('&laquo;'),
	'next_text' => __('&raquo;'),
	'total' => ceil(wp_count_terms('post_tag') / $tagsperpage),
	'current' => $pagenum
));

if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<select name="action">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction" id="doaction" class="button-secondary action" />
<?php wp_nonce_field('bulk-tags'); ?>
</div>

<br class="clear" />
</div>

<div class="clear"></div>

<table class="widefat tag fixed" cellspacing="0">
	<thead>
	<tr>
<?php print_column_headers('edit-tags'); ?>
	</tr>
	</thead>

	<tfoot>
	<tr>
<?php print_column_headers('edit-tags', false); ?>
	</tr>
	</tfoot>

	<tbody id="the-list" class="list:tag">
<?php

$searchterms = isset( $_GET['s'] ) ? trim( $_GET['s'] ) : '';

$count = tag_rows( $pagenum, $tagsperpage, $searchterms );
?>
	</tbody>
</table>

<div class="tablenav">
<?php
if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<select name="action2">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
</div>

<br class="clear" />
</div>

<br class="clear" />
</form>
</div>
</div><!-- /col-right -->

<div id="col-left">
<div class="col-wrap">

<div class="tagcloud">
<h3><?php _e('Popular Tags'); ?></h3>
<?php
if ( $can_manage )
	wp_tag_cloud(array('link' => 'edit'));
else
	wp_tag_cloud();
?>
</div>

<?php if ( $can_manage ) {
	do_action('add_tag_form_pre'); ?>

<div class="form-wrap">
<h3><?php _e('Add a New Tag'); ?></h3>
<div id="ajax-response"></div>
<form name="addtag" id="addtag" method="post" action="edit-tags.php" class="add:the-list: validate">
<input type="hidden" name="action" value="addtag" />
<?php wp_original_referer_field(true, 'previous'); wp_nonce_field('add-tag'); ?>

<div class="form-field form-required">
	<label for="name"><?php _e('Tag name') ?></label>
	<input name="name" id="name" type="text" value="" size="40" aria-required="true" />
	<p><?php _e('The name is how the tag appears on your site.'); ?></p>
</div>

<div class="form-field">
	<label for="slug"><?php _e('Tag slug') ?></label>
	<input name="slug" id="slug" type="text" value="" size="40" />
	<p><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
</div>

<p class="submit"><input type="submit" class="button" name="submit" value="<?php _e('Add Tag'); ?>" /></p>
<?php do_action('add_tag_form'); ?>
</form></div>
<?php } ?>

</div>
</div><!-- /col-left -->

</div><!-- /col-container -->
</div><!-- /wrap -->

<script type="text/javascript">
/* <![CDATA[ */
(function($){
	$(document).ready(function(){
		$('#doaction, #doaction2').click(function(){
			if ( $('select[name^="action"]').val() == 'delete' ) {
				var m = '<?php echo js_escape(__("You are about to delete the selected tags.\n  'Cancel' to stop, 'OK' to delete.")); ?>';
				return showNotice.warn(m);
			}
		});
	});
})(jQuery);
/* ]]> */
</script>

<?php inline_edit_term_row('edit-tags'); ?>

<?php
break;
}

include('admin-footer.php');

?>
