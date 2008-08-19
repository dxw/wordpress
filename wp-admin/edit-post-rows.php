<?php
/**
 * Edit posts rows table for inclusion in administration panels.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! defined('ABSPATH') ) die();
?>
<table class="widefat">
	<thead>
	<tr>

<?php $posts_columns = wp_manage_posts_columns(); ?>
<?php foreach($posts_columns as $post_column_key => $column_display_name) {
	if ( 'cb' === $post_column_key )
		$class = ' class="check-column"';
	elseif ( 'comments' === $post_column_key )
		$class = ' class="num"';
	else
		$class = '';
?>
	<th scope="col"<?php echo $class; ?>><?php echo $column_display_name; ?></th>
<?php } ?>

	</tr>
	</thead>
	<tbody>
<?php
if ( have_posts() ) {
$bgcolor = '';
add_filter('the_title','wp_specialchars');

// Create array of post IDs.
$post_ids = array();
foreach ( $wp_query->posts as $a_post )
	$post_ids[] = $a_post->ID;

$comment_pending_count = get_pending_comments_num($post_ids);

while (have_posts()) : the_post();
$class = 'alternate' == $class ? '' : 'alternate';
global $current_user;
$post_owner = ( $current_user->ID == $post->post_author ? 'self' : 'other' );
$edit_link = get_edit_post_link( $post->ID );
$title = get_the_title();
if ( empty($title) )
	$title = __('(no title)');
?>
	<tr id='post-<?php echo $id; ?>' class='<?php echo trim( $class . ' author-' . $post_owner . ' status-' . $post->post_status ); ?>' valign="top">

<?php

foreach($posts_columns as $column_name=>$column_display_name) {

	switch($column_name) {

	case 'cb':
		?>
		<th scope="row" class="check-column"><?php if ( current_user_can( 'edit_post', $post->ID ) ) { ?><input type="checkbox" name="post[]" value="<?php the_ID(); ?>" /><?php } ?></th>
		<?php
		break;
	case 'modified':
	case 'date':
		if ( '0000-00-00 00:00:00' == $post->post_date && 'date' == $column_name ) {
			$t_time = $h_time = __('Unpublished');
		} else {
			if ( 'modified' == $column_name ) {
				$t_time = get_the_modified_time(__('Y/m/d g:i:s A'));
				$m_time = $post->post_modified;
				$time = get_post_modified_time('G', true);
			} else {
				$t_time = get_the_time(__('Y/m/d g:i:s A'));
				$m_time = $post->post_date;
				$time = get_post_time('G', true);
			}
			if ( ( abs(time() - $time) ) < 86400 ) {
				if ( ( 'future' == $post->post_status) )
					$h_time = sprintf( __('%s from now'), human_time_diff( $time ) );
				else
					$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
			} else {
				$h_time = mysql2date(__('Y/m/d'), $m_time);
			}
		}

		if ( 'excerpt' == $mode ) : ?>
		<td><?php echo apply_filters('post_date_column_time', $t_time, $post, $column_name, $mode) ?></td>
<?php		else : ?>
		<td><abbr title="<?php echo $t_time ?>"><?php echo apply_filters('post_date_column_time', $h_time, $post, $column_name, $mode) ?></abbr></td>
<?php		endif;
		break;
	case 'title':
		?>
		<td class="post-title"><strong><?php if ( current_user_can( 'edit_post', $post->ID ) ) { ?><a class="row-title" href="<?php echo $edit_link; ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $title)); ?>"><?php echo $title ?></a><?php } else { echo $title; } ?></strong>
		<?php
		if ( !empty($post->post_password) ) { _e(' &#8212; <strong>Protected</strong>'); } elseif ('private' == $post->post_status) { _e(' &#8212; <strong>Private</strong>'); }

		if ( 'excerpt' == $mode )
			the_excerpt();

		$actions = array();
		$actions['edit'] = '<a href="post.php?action=edit&amp;post=' . $post->ID . '">' . __('Edit') . '</a>';
		$actions['delete'] = "<a class='submitdelete' href='" . wp_nonce_url("post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post_ID) . "' onclick=\"if ( confirm('" . js_escape(sprintf( ('draft' == $post->post_status) ? __("You are about to delete this draft '%s'\n  'Cancel' to stop, 'OK' to delete.") : __("You are about to delete this post '%s'\n  'Cancel' to stop, 'OK' to delete."), $post->post_title )) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
		$action_count = count($actions);
		$i = 0;
		foreach ( $actions as $action => $link ) {
			++$i;
			( $i == $action_count ) ? $sep = '' : $sep = ' | ';
			echo "<span class='$action'>$link$sep</span>";
		}
		?>
		</td>
		<?php
		break;

	case 'categories':
		?>
		<td><?php
		$categories = get_the_category();
		if ( !empty( $categories ) ) {
			$out = array();
			foreach ( $categories as $c )
				$out[] = "<a href='edit.php?category_name=$c->slug'> " . wp_specialchars(sanitize_term_field('name', $c->name, $c->term_id, 'category', 'display')) . "</a>";
			echo join( ', ', $out );
		} else {
			_e('Uncategorized');
		}
		?></td>
		<?php
		break;

	case 'tags':
		?>
		<td><?php
		$tags = get_the_tags();
		if ( !empty( $tags ) ) {
			$out = array();
			foreach ( $tags as $c )
				$out[] = "<a href='edit.php?tag=$c->slug'> " . wp_specialchars(sanitize_term_field('name', $c->name, $c->term_id, 'post_tag', 'display')) . "</a>";
			echo join( ', ', $out );
		} else {
			_e('No Tags');
		}
		?></td>
		<?php
		break;

	case 'comments':
		?>
		<td class="num"><div class="post-com-count-wrapper">
		<?php
		$left = isset($comment_pending_count) ? $comment_pending_count[$post->ID] : 0;
		$pending_phrase = sprintf( __('%s pending'), number_format( $left ) );
		if ( $left )
			echo '<strong>';
		comments_number("<a href='edit.php?p=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('0') . '</span></a>', "<a href='edit.php?p=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('1') . '</span></a>', "<a href='edit.php?p=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('%') . '</span></a>');
		if ( $left )
			echo '</strong>';
		?>
		</div></td>
		<?php
		break;

	case 'author':
		?>
		<td><a href="edit.php?author=<?php the_author_ID(); ?>"><?php the_author() ?></a></td>
		<?php
		break;

	case 'status':
		?>
		<td>
		<a href="<?php the_permalink(); ?>" title="<?php echo attribute_escape(sprintf(__('View "%s"'), $title)); ?>" rel="permalink">
		<?php
		switch ( $post->post_status ) {
			case 'publish' :
			case 'private' :
				_e('Published');
				break;
			case 'future' :
				_e('Scheduled');
				break;
			case 'pending' :
				_e('Pending Review');
				break;
			case 'draft' :
				_e('Unpublished');
				break;
		}
		?>
		</a>
		</td>
		<?php
		break;

	case 'control_view':
		?>
		<td><a href="<?php the_permalink(); ?>" rel="permalink" class="view"><?php _e('View'); ?></a></td>
		<?php
		break;

	case 'control_edit':
		?>
		<td><?php if ( current_user_can('edit_post',$post->ID) ) { echo "<a href='$edit_link' class='edit'>" . __('Edit') . "</a>"; } ?></td>
		<?php
		break;

	case 'control_delete':
		?>
		<td><?php if ( current_user_can('delete_post',$post->ID) ) { echo "<a href='" . wp_nonce_url("post.php?action=delete&amp;post=$id", 'delete-post_' . $post->ID) . "' class='delete'>" . __('Delete') . "</a>"; } ?></td>
		<?php
		break;

	default:
		?>
		<td><?php do_action('manage_posts_custom_column', $column_name, $id); ?></td>
		<?php
		break;
	}
}
?>
	</tr>
<?php
endwhile;
} else {
?>
  <tr style='background-color: <?php echo $bgcolor; ?>'>
    <td colspan="8"><?php _e('No posts found.') ?></td>
  </tr>
<?php
} // end if ( have_posts() )
?>
	</tbody>
</table>
