<?php
/**
 * Edit attachments table for inclusion in administration panels.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! defined('ABSPATH') ) die();
?>
<table class="widefat">
	<thead>
	<tr>

<?php $posts_columns = wp_manage_media_columns(); ?>
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
	<tbody id="the-list" class="list:post">
<?php
if ( have_posts() ) {
$bgcolor = '';
add_filter('the_title','wp_specialchars');
while (have_posts()) : the_post();
$class = 'alternate' == $class ? '' : 'alternate';
global $current_user;
$post_owner = ( $current_user->ID == $post->post_author ? 'self' : 'other' );
$att_title = get_the_title();
if ( empty($att_title) )
	$att_title = __('(no title)');

?>
	<tr id='post-<?php echo $id; ?>' class='<?php echo trim( $class . ' author-' . $post_owner . ' status-' . $post->post_status ); ?>' valign="top">

<?php

foreach($posts_columns as $column_name=>$column_display_name) {

	switch($column_name) {

	case 'cb':
		?>
		<th scope="row" class="check-column"><input type="checkbox" name="media[]" value="<?php the_ID(); ?>" /></th>
		<?php
		break;

	case 'icon':
		?>
		<td class="media-icon"><?php
			if ( $thumb = wp_get_attachment_image( $post->ID, array(80, 60), true ) ) {
?>

				<a href="media.php?action=edit&amp;attachment_id=<?php the_ID(); ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $att_title)); ?>">
					<?php echo $thumb; ?>
				</a>

<?php			}
		?></td>
		<?php
		// TODO
		break;

	case 'media':
		?>
		<td><strong><a href="<?php echo get_edit_post_link( $post->ID ); ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $att_title)); ?>"><?php echo $att_title; ?></a></strong><br />
		<?php echo strtoupper(preg_replace('/^.*?\.(\w+)$/', '$1', get_attached_file($post->ID))); ?>
		<p>
		<a href="media.php?action=edit&amp;attachment_id=<?php the_ID(); ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $att_title)); ?>"><?php _e('Edit'); ?></a> |
		<a href="<?php the_permalink(); ?>"><?php _e('Get permalink'); ?></a> |
		<a href="#" class="delete"><?php _e('Delete'); ?></a>
		</p>
		<?php do_action('manage_media_media_column', $post->ID); ?>
		</td>
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

	case 'desc':
		?>
		<td><?php echo has_excerpt() ? $post->post_excerpt : ''; ?></td>
		<?php
		break;

	case 'date':
		if ( '0000-00-00 00:00:00' == $post->post_date && 'date' == $column_name ) {
			$t_time = $h_time = __('Unpublished');
		} else {
			$t_time = get_the_time(__('Y/m/d g:i:s A'));
			$m_time = $post->post_date;
			$time = get_post_time( 'G', true );
			if ( ( abs($t_diff = time() - $time) ) < 86400 ) {
				if ( $t_diff < 0 )
					$h_time = sprintf( __('%s from now'), human_time_diff( $time ) );
				else
					$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
			} else {
				$h_time = mysql2date(__('Y/m/d'), $m_time);
			}
		}
		?>
		<td><?php echo $h_time ?></td>
		<?php
		break;

	case 'parent':
		$title = __('(no title)'); // override below
		if ( $post->post_parent > 0 ) {
			if ( get_post($post->post_parent) ) {
				$parent_title = get_the_title($post->post_parent);
				if ( !empty($parent_title) )
					$title = $parent_title;
			}
			?>
			<td><strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>, <?php echo get_the_time(__('Y/m/d')); ?></td>
			<?php
		} else {
			?>
			<td>&nbsp;</td>
			<?php
		}

		break;

	case 'comments':
		?>
		<td class="num"><div class="post-com-count-wrapper">
		<?php
		$left = get_pending_comments_num( $post->ID );
		$pending_phrase = sprintf( __('%s pending'), number_format( $left ) );
		if ( $left )
			echo '<strong>';
		comments_number("<a href='upload.php?attachment_id=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('0') . '</span></a>', "<a href='upload.php?attachment_id=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('1') . '</span></a>', "<a href='upload.php?attachment_id=$id' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . __('%') . '</span></a>');
		if ( $left )
			echo '</strong>';
		?>
		</div></td>
		<?php
		break;

	case 'actions':
		?>
		<td>
		<a href="media.php?action=edit&amp;attachment_id=<?php the_ID(); ?>" title="<?php echo attribute_escape(sprintf(__('Edit "%s"'), $att_title)); ?>"><?php _e('Edit'); ?></a> |
		<a href="<?php the_permalink(); ?>"><?php _e('Get permalink'); ?></a>
		</td>
		<?php
		break;

	default:
		?>
		<td><?php do_action('manage_media_custom_column', $column_name, $id); ?></td>
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
