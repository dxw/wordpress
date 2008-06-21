<?php
require_once('admin.php');

$title = __('Inbox');
$parent_file = 'inbox.php';

require_once('admin-header.php');

?>
<div class="wrap">
<form id="inbox-filter" action="" method="get">
<h2><?php _e('Inbox'); ?></h2>
<ul class="subsubsub">
<li><a href="#" class="current"><?php _e('Messages') ?></a></li> | <li><a href="#"><?php echo sprintf(__('Archived') . ' (%s)', '42'); ?></a></li>
</ul>
<div class="tablenav">
<div class="alignleft">
<select name="action">
<option value="" selected><?php _e('Actions'); ?></option>
<option value="archive"><?php _e('Archive'); ?></option>
</select>
<input type="submit" value="<?php _e('Apply'); ?>" name="doaction" class="button-secondary action" />
</div>
<br class="clear" />
</div>
<br class="clear" />
<table class="widefat">
	<thead>
	<tr>
	<th scope="col" class="check-column"><input type="checkbox"/></th>
	<th scope="col"><?php _e('Message'); ?></th>
	<th scope="col"><?php _e('Date'); ?></th>
	<th scope="col"><?php _e('From'); ?></th>
	</tr>
	</thead>
	<tbody>
	<tr id="message-15">
		<th scope="col" class="check-column"><input type="checkbox" name="messages[]" value="15" /></td>
		<td>
			Your take on the evolution of Dr. Who is ridiculous. The fact that the actors are getting younger has nothing to do with Gallifrey lore, and everything to do with celebrity culture.
			<br/><a class="inbox-more" href="#"><?php _e('more...'); ?></a>
		</td>
		<td><a href="#link-to-comment"><abbr title="2008/09/06 at 4:19pm">2008/09/06</abbr></a></td>
		<td>
			l. monroe
			<br/>on "<a href="#">Post</a>"
		</td>
	</tr>
	<tr id="message-14">
		<th scope="col" class="check-column"><input type="checkbox" name="messages[]" value="14" /></td>
		<td><strong>Announcement: WordPress introduces new features for mobile blogging.</strong></td>
		<td><abbr title="2008/09/06 at 3:24pm">2008/09/06</abbr></td>
		<td><em>WordPress.org</em></td>
	</tr>
	<tr id="message-12">
		<th scope="col" class="check-column"><input type="checkbox" name="messages[]" value="12" /></td>
		<td>Great review. You left out a few things, but maybe you were trying to avoid spoilers? Will check back later in a week.</td>
		<td><a href="#link-to-comment"><abbr title="2008/09/06 at 2:46pm">2008/09/06</abbr></a></td>
		<td>
			matt
			<br/>on "<a href="#">Post</a>"
		</td>
	</tr>
	<tr id="message-11">
		<th scope="col" class="check-column"><input type="checkbox" name="messages[]" value="11" /></td>
		<td>nice picture!</td>
		<td><a href="#link-to-comment"><abbr title="2008/08/06 at 3:17pm">2008/08/06</abbr></a></td>
		<td>
			caped crusader
			<br/>on "<a href="#">some post</a>"
		</td>
	</tr>
</table>
</form>
<div class="tablenav"></div>
<br class="clear"/>
</div>
<?php include('admin-footer.php'); ?>
