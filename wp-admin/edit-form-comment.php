<?php
$submitbutton_text = __('Edit Comment');
$toprow_title = sprintf(__('Editing Comment # %s'), $comment->comment_ID);
$form_action = 'editedcomment';
$form_extra = "' />\n<input type='hidden' name='comment_ID' value='" . $comment->comment_ID . "' />\n<input type='hidden' name='comment_post_ID' value='" . $comment->comment_post_ID;
?>

<form name="post" action="comment.php" method="post" id="post">
<div id="wpbody-content">

<?php wp_nonce_field('update-comment_' . $comment->comment_ID) ?>
<div class="wrap">
<h2><?php echo $toprow_title; ?></h2>
<input type="hidden" name="user_ID" value="<?php echo (int) $user_ID ?>" />
<input type="hidden" name="action" value='<?php echo $form_action . $form_extra ?>' />

<div id="poststuff">

<!--
<p id="big-add-button">
<span id="previewview">
<a href="<?php echo get_comment_link(); ?>" class="button" target="_blank"><?php _e('View this Comment'); ?></a>
</span>
</p>
-->

<!-- crazyhorse
<div class="side-info">
<h5><?php _e('Related') ?></h5>

<ul>
<li><a href="edit-comments.php"><?php _e('Manage All Comments') ?></a></li>
<li><a href="edit-comments.php?comment_status=moderated"><?php _e('Moderate Comments') ?></a></li>
<?php do_action('comment_relatedlinks_list'); ?>
</ul>
</div>
<?php do_action('submitcomment_box'); ?>
</div>
-->

<div id="side-info-column" class="inner-sidebar">

<div id="emaildiv" class="stuffbox">
<h3><label for="email"><?php _e('E-mail') ?></label></h3>
<div class="inside">
<input type="text" name="newcomment_author_email" size="30" value="<?php echo attribute_escape( $comment->comment_author_email ); ?>" tabindex="2" id="email" />
</div>
</div>

<div id="uridiv" class="stuffbox">
<h3><label for="newcomment_author_url"><?php _e('URL') ?></label></h3>
<div class="inside">
<input type="text" id="newcomment_author_url" name="newcomment_author_url" size="30" value="<?php echo attribute_escape( $comment->comment_author_url ); ?>" tabindex="3" />
</div>
</div>

<div id="statusdiv" class="stuffbox">
<h3><label for='comment_status'><?php _e('Approval Status') ?></label></h3>
<div class="inside">
<select name='comment_status' id='comment_status'>
<option<?php selected( $comment->comment_approved, '1' ); ?> value='1'><?php _e('Approved') ?></option>
<option<?php selected( $comment->comment_approved, '0' ); ?> value='0'><?php _e('Moderated') ?></option>
<option<?php selected( $comment->comment_approved, 'spam' ); ?> value='spam'><?php _e('Spam') ?></option>
</select>
</div>
</div>

</div>

<div id="post-body" class="has-sidebar">
<div id="post-body-content" class="has-sidebar-content">

<div id="namediv" class="stuffbox">
<h3><label for="name"><?php _e('Name') ?></label></h3>
<div class="inside">
<input type="text" name="newcomment_author" size="30" value="<?php echo attribute_escape( $comment->comment_author ); ?>" tabindex="1" id="name" />
</div>
</div>


<div id="postdiv" class="postarea">
<h3><?php _e('Comment') ?></h3>
<?php the_editor($comment->comment_content, 'content', 'newcomment_author_url', false, 4); ?>
<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
</div>

<?php do_meta_boxes('comment', 'normal', $comment); ?>

<input type="hidden" name="c" value="<?php echo $comment->comment_ID ?>" />
<input type="hidden" name="p" value="<?php echo $comment->comment_post_ID ?>" />
<input name="referredby" type="hidden" id="referredby" value="<?php echo wp_get_referer(); ?>" />
<?php wp_original_referer_field(true, 'previous'); ?>
<input type="hidden" name="noredir" value="1" />

</div>
</div>
</div>
</div>

</div><!-- wpbody-content (fixedbar) -->

<div id="fixedbar">
<table id="fixedbar-wrap"><tbody><tr>

<td id="preview-link">&nbsp;
<span>
<a href="<?php echo get_comment_link(); ?>" target="_blank"><?php _e('View this Comment'); ?></a>
</span>
</td>

<td id="submitpost" class="submitbox">
<div id="comment-time-info" class="alignleft">
<?php
$stamp = __('Timestamp: <span class="timestamp">%1$s at %2$s %3$s</span>');
$date = mysql2date(get_option('date_format'), $comment->comment_date);
$time = mysql2date(get_option('time_format'), $comment->comment_date);
$edit = '(<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js">' . __('Change') . '</a>)';

?>
<p id="curtime"><?php printf($stamp, $date, $time, $edit); ?></p>

<div id='timestampdiv' class='hide-if-js'><?php touch_time(('editcomment' == $action), 0, 5); ?></div>
</div>

<p class="submit alignright">
<?php
echo "<a class='submitdelete' href='" . wp_nonce_url("comment.php?action=deletecomment&amp;c=$comment->comment_ID&amp;_wp_original_http_referer=" . wp_get_referer(), 'delete-comment_' . $comment->comment_ID) . "' onclick=\"if ( confirm('" . js_escape(__("You are about to delete this comment. \n  'Cancel' to stop, 'OK' to delete.")) . "') ) { return true;}return false;\">" . __('Delete comment') . "</a>&nbsp;";
?>
<input type="submit" name="save" value="<?php _e('Save'); ?>" tabindex="4" class="button button-highlighted" />
</p>
</td></tr></tbody></table>
</div><!-- /fixedbar -->





</form>

<script type="text/javascript">
try{document.post.name.focus();}catch(e){}
</script>
