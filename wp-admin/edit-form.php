<?php
/**
 * Edit post form for Write Post and for the Bookmarklet mode.
 *
 * @package WordPress
 * @subpackage Administration
 */
?>
<div class="wrap">
<h2><?php _e('Write Post'); ?></h2>
<form name="post" action="post.php" method="post" id="simple">

<?php if (isset($mode) && 'bookmarklet' == $mode) : ?>
<input type="hidden" name="mode" value="bookmarklet" />
<?php endif; ?>
<input type="hidden" id="user-id" name="user_ID" value="<?php echo (int) $user_ID ?>" />
<input type="hidden" name="action" value='post' />

<div id="poststuff">
    <fieldset id="titlediv">
      <legend><a href="http://wordpress.org/docs/reference/post/#title" title="<?php _e('Help on titles') ?>"><?php _e('Title') ?></a></legend>
	  <div><input type="text" name="post_title" size="30" tabindex="1" value="<?php echo attribute_escape( $post->post_title ); ?>" id="title" /></div>
    </fieldset>

    <fieldset id="categorydiv">
      <legend><a href="http://wordpress.org/docs/reference/post/#category" title="<?php _e('Help on categories') ?>"><?php _e('Categories') ?></a></legend>
	  <div><?php dropdown_categories($post->post_category); ?></div>
    </fieldset>

<br />
<fieldset id="postdiv">
    <legend><a href="http://wordpress.org/docs/reference/post/#post" title="<?php _e('Help with post field') ?>"><?php _e('Post') ?></a></legend>
<?php
 $rows = get_option('default_post_edit_rows');
 if (($rows < 3) || ($rows > 100)) {
     $rows = 10;
 }
?>
<div><textarea rows="<?php echo $rows; ?>" cols="40" name="content" tabindex="4" id="content"><?php echo $post->post_content ?></textarea></div>
<?php wp_nonce_field( 'autosave', 'autosavenonce', false ); ?>
</fieldset>


<script type="text/javascript">
<!--
edCanvas = document.getElementById('content');
//-->
</script>

<input type="hidden" name="post_pingback" value="<?php echo (int) get_option('default_pingback_flag') ?>" id="post_pingback" />

<p><label for="trackback"> <?php printf(__('<a href="%s" title="Help on trackbacks"><strong>TrackBack</strong> a <abbr title="Universal Resource Locator">URL</abbr></a>:</label> (Separate multiple <abbr title="Universal Resource Locator">URL</abbr>s with spaces.)'), 'http://wordpress.org/docs/reference/post/#trackback'); echo '<br />'; ?>
	<input type="text" name="trackback_url" style="width: 360px" id="trackback" tabindex="7" /></p>

<p class="submit"><input name="saveasdraft" type="submit" id="saveasdraft" tabindex="9" value="<?php _e('Save as Draft') ?>" />
	<input name="saveasprivate" type="submit" id="saveasprivate" tabindex="10" value="<?php _e('Save as Private') ?>" />

	 <?php if ( current_user_can('edit_posts') ) : ?>
	<input name="publish" type="submit" id="publish" tabindex="6" value="<?php _e('Publish') ?>" class="button button-highlighted" />
<?php endif; ?>

<?php if ('bookmarklet' != $mode) {
		echo '<input name="advanced" type="submit" id="advancededit" tabindex="7" value="' .  __('Advanced Editing') . '" />';
	} ?>
	<input name="referredby" type="hidden" id="referredby" value="<?php if ( $refby = wp_get_referer() ) echo urlencode($refby); ?>" />
</p>

<?php do_action('simple_edit_form', ''); ?>

</div>
</form>

<script type="text/javascript">
try{document.getElementById('title').focus();}catch(e){}
</script>
</div>
