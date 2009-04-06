<?php
/**
 * Edit Pages Administration Panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

// Handle bulk actions
if ( isset($_GET['action']) && ( -1 != $_GET['action'] || -1 != $_GET['action2'] ) ) {
	$doaction = ( -1 != $_GET['action'] ) ? $_GET['action'] : $_GET['action2'];

	switch ( $doaction ) {
		case 'delete':
			if ( isset($_GET['post']) && ! isset($_GET['bulk_edit']) && (isset($_GET['doaction']) || isset($_GET['doaction2'])) ) {
				check_admin_referer('bulk-pages');
				$deleted = 0;
				foreach( (array) $_GET['post'] as $post_id_del ) {
					$post_del = & get_post($post_id_del);

					if ( !current_user_can('delete_page', $post_id_del) )
						wp_die( __('You are not allowed to delete this page.') );

					if ( $post_del->post_type == 'attachment' ) {
						if ( ! wp_delete_attachment($post_id_del) )
							wp_die( __('Error in deleting...') );
					} else {
						if ( !wp_delete_post($post_id_del) )
							wp_die( __('Error in deleting...') );
					}
					$deleted++;
				}
			}
			break;
		case 'edit':
			if ( isset($_GET['post']) && isset($_GET['bulk_edit']) ) {
				check_admin_referer('bulk-pages');

				if ( -1 == $_GET['_status'] ) {
					$_GET['post_status'] = null;
					unset($_GET['_status'], $_GET['post_status']);
				} else {
					$_GET['post_status'] = $_GET['_status'];
				}

				$done = bulk_edit_posts($_GET);
			}
			break;
	}

	$sendback = wp_get_referer();
	if (strpos($sendback, 'page.php') !== false) $sendback = admin_url('page-new.php');
	elseif (strpos($sendback, 'attachments.php') !== false) $sendback = admin_url('attachments.php');
	if ( isset($done) ) {
		$done['updated'] = count( $done['updated'] );
		$done['skipped'] = count( $done['skipped'] );
		$done['locked'] = count( $done['locked'] );
		$sendback = add_query_arg( $done, $sendback );
	}
	if ( isset($deleted) )
		$sendback = add_query_arg('deleted', $deleted, $sendback);
	wp_redirect($sendback);
	exit();
} elseif ( isset($_GET['_wp_http_referer']) && ! empty($_GET['_wp_http_referer']) ) {
	 wp_redirect( remove_query_arg( array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI']) ) );
	 exit;
}

if ( empty($title) )
	$title = __('Edit Pages');
$parent_file = 'edit-pages.php';
wp_enqueue_script('inline-edit-post');

$post_stati  = array(	//	array( adj, noun )
		'publish' => array(__('Published|page'), __('Published pages'), _n_noop('Published <span class="count">(%s)</span>|page', 'Published <span class="count">(%s)</span>')),
		'future' => array(__('Scheduled|page'), __('Scheduled pages'), _n_noop('Scheduled <span class="count">(%s)</span>|page', 'Scheduled <span class="count">(%s)</span>')),
		'pending' => array(__('Pending Review|page'), __('Pending pages'), _n_noop('Pending Review <span class="count">(%s)</span>|page', 'Pending Review <span class="count">(%s)</span>')),
		'draft' => array(__('Draft|page'), _c('Drafts|manage posts header'), _n_noop('Draft <span class="count">(%s)</span>|page', 'Drafts <span class="count">(%s)</span>')),
		'private' => array(__('Private|page'), __('Private pages'), _n_noop('Private <span class="count">(%s)</span>|page', 'Private <span class="count">(%s)</span>'))
	);

$query = array('post_type' => 'page', 'orderby' => 'menu_order title', 'what_to_show' => 'posts',
	'posts_per_page' => -1, 'posts_per_archive_page' => -1, 'order' => 'asc');

$post_status_label = __('Pages');
if ( isset($_GET['post_status']) && in_array( $_GET['post_status'], array_keys($post_stati) ) ) {
	$post_status_label = $post_stati[$_GET['post_status']][1];
	$query['post_status'] = $_GET['post_status'];
	$query['perm'] = 'readable';
}

$query = apply_filters('manage_pages_query', $query);
wp($query);

if ( is_singular() ) {
	wp_enqueue_script( 'admin-comments' );
	enqueue_comment_hotkeys_js();
}

require_once('admin-header.php'); ?>

<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo wp_specialchars( $title );
if ( isset($_GET['s']) && $_GET['s'] )
	printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', wp_specialchars( get_search_query() ) ); ?>
</h2>

<?php if ( isset($_GET['locked']) || isset($_GET['skipped']) || isset($_GET['updated']) || isset($_GET['deleted']) ) { ?>
<div id="message" class="updated fade"><p>
<?php if ( isset($_GET['updated']) && (int) $_GET['updated'] ) {
	printf( __ngettext( '%s page updated.', '%s pages updated.', $_GET['updated'] ), number_format_i18n( $_GET['updated'] ) );
	unset($_GET['updated']);
}

if ( isset($_GET['skipped']) && (int) $_GET['skipped'] ) {
	printf( __ngettext( '%s page not updated, invalid parent page specified.', '%s pages not updated, invalid parent page specified.', $_GET['skipped'] ), number_format_i18n( $_GET['skipped'] ) );
	unset($_GET['skipped']);
}

if ( isset($_GET['locked']) && (int) $_GET['locked'] ) {
	printf( __ngettext( '%s page not updated, somebody is editing it.', '%s pages not updated, somebody is editing them.', $_GET['locked'] ), number_format_i18n( $_GET['skipped'] ) );
	unset($_GET['locked']);
}

if ( isset($_GET['deleted']) && (int) $_GET['deleted'] ) {
	printf( __ngettext( 'Page deleted.', '%s pages deleted.', $_GET['deleted'] ), number_format_i18n( $_GET['deleted'] ) );
	unset($_GET['deleted']);
}
$_SERVER['REQUEST_URI'] = remove_query_arg( array('locked', 'skipped', 'updated', 'deleted'), $_SERVER['REQUEST_URI'] );
?>
</p></div>
<?php } ?>

<?php if ( isset($_GET['posted']) && $_GET['posted'] ) : $_GET['posted'] = (int) $_GET['posted']; ?>
<div id="message" class="updated fade"><p><strong><?php _e('Your page has been saved.'); ?></strong> <a href="<?php echo get_permalink( $_GET['posted'] ); ?>"><?php _e('View page'); ?></a> | <a href="<?php echo get_edit_post_link( $_GET['posted'] ); ?>"><?php _e('Edit page'); ?></a></p></div>
<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('posted'), $_SERVER['REQUEST_URI']);
endif; ?>

<form id="posts-filter" action="" method="get">
<ul class="subsubsub">
<?php

$avail_post_stati = get_available_post_statuses('page');
if ( empty($locked_post_status) ) :
$status_links = array();
$num_posts = wp_count_posts('page', 'readable');
$total_posts = array_sum( (array) $num_posts );
$class = empty($_GET['post_status']) ? ' class="current"' : '';
$status_links[] = "<li><a href='edit-pages.php'$class>" . sprintf( __ngettext( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts ), number_format_i18n( $total_posts ) ) . '</a>';
foreach ( $post_stati as $status => $label ) {
	$class = '';

	if ( !in_array($status, $avail_post_stati) )
		continue;

	if ( isset( $_GET['post_status'] ) && $status == $_GET['post_status'] )
		$class = ' class="current"';

	$status_links[] = "<li><a href='edit-pages.php?post_status=$status'$class>" . sprintf( _nc( $label[2][0], $label[2][1], $num_posts->$status ), number_format_i18n( $num_posts->$status ) ) . '</a>';
}
echo implode( " |</li>\n", $status_links ) . '</li>';
unset($status_links);
endif;
?>
</ul>

<p class="search-box">
	<label class="hidden" for="page-search-input"><?php _e( 'Search Pages' ); ?>:</label>
	<input type="text" class="search-input" id="page-search-input" name="s" value="<?php _admin_search_query(); ?>" />
	<input type="submit" value="<?php _e( 'Search Pages' ); ?>" class="button" />
</p>

<?php if ( isset($_GET['post_status'] ) ) : ?>
<input type="hidden" name="post_status" value="<?php echo attribute_escape($_GET['post_status']) ?>" />
<?php endif; ?>

<?php if ($posts) { ?>

<div class="tablenav">

<?php
$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
if ( empty($pagenum) )
	$pagenum = 1;
if( ! isset( $per_page ) || $per_page < 0 )
	$per_page = 20;

$num_pages = ceil($wp_query->post_count / $per_page);
$page_links = paginate_links( array(
	'base' => add_query_arg( 'pagenum', '%#%' ),
	'format' => '',
	'prev_text' => __('&laquo;'),
	'next_text' => __('&raquo;'),
	'total' => $num_pages,
	'current' => $pagenum
));

if ( $page_links ) : ?>
<div class="tablenav-pages"><?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
	number_format_i18n( ( $pagenum - 1 ) * $per_page + 1 ),
	number_format_i18n( min( $pagenum * $per_page, $wp_query->post_count ) ),
	number_format_i18n( $wp_query->post_count ),
	$page_links
); echo $page_links_text; ?></div>
<?php endif; ?>

<div class="alignleft actions">
<select name="action">
<option value="-1" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="edit"><?php _e('Edit'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction" id="doaction" class="button-secondary action" />
<?php wp_nonce_field('bulk-pages'); ?>
</div>

<br class="clear" />
</div>

<div class="clear"></div>

<table class="widefat page fixed" cellspacing="0">
  <thead>
  <tr>
<?php print_column_headers('edit-pages'); ?>
  </tr>
  </thead>

  <tfoot>
  <tr>
<?php print_column_headers('edit-pages', false); ?>
  </tr>
  </tfoot>

  <tbody>
  <?php page_rows($posts, $pagenum, $per_page); ?>
  </tbody>
</table>

<div class="tablenav">
<?php
if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links_text</div>";
?>

<div class="alignleft actions">
<select name="action2">
<option value="-1" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="edit"><?php _e('Edit'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
</div>

<br class="clear" />
</div>

<?php } else { ?>
<div class="clear"></div>
<p><?php _e('No pages found.') ?></p>
<?php
} // end if ($posts)
?>

</form>

<?php inline_edit_row( 'page' ) ?>

<div id="ajax-response"></div>


<?php

if ( 1 == count($posts) && is_singular() ) :

	$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved != 'spam' ORDER BY comment_date", $id) );
	if ( $comments ) :
		// Make sure comments, post, and post_author are cached
		update_comment_cache($comments);
		$post = get_post($id);
		$authordata = get_userdata($post->post_author);
	?>

<br class="clear" />

<table class="widefat" cellspacing="0">
<thead>
  <tr>
    <th scope="col" class="column-comment"><?php echo _c('Comment|noun') ?></th>
    <th scope="col" class="column-author"><?php _e('Author') ?></th>
    <th scope="col" class="column-date"><?php _e('Submitted') ?></th>
  </tr>
</thead>
<tbody id="the-comment-list" class="list:comment">
<?php
	foreach ($comments as $comment)
		_wp_comment_row( $comment->comment_ID, 'single', false, false );
?>
</tbody>
</table>

<?php
wp_comment_reply();
endif; // comments
endif; // posts;

?>

</div>

<script type="text/javascript">
/* <![CDATA[ */
(function($){
	$(document).ready(function(){
		$('#doaction, #doaction2').click(function(){
			if ( $('select[name="action"]').val() == 'delete' || $('select[name="action2"]').val() == 'delete' ) {
				var m = '<?php echo js_escape(__("You are about to delete the selected pages.\n  'Cancel' to stop, 'OK' to delete.")); ?>';
				return showNotice.warn(m);
			}
		});
	});
})(jQuery);
columns.init('edit-pages');
/* ]]> */
</script>

<?php include('admin-footer.php'); ?>
