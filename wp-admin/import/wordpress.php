<?php

class WP_Import {

	var $post_ids_processed = array ();
	var $orphans = array ();
	var $file;
	var $id;
	var $mtnames = array ();
	var $newauthornames = array ();
	var $allauthornames = array ();

	var $author_ids = array ();
	var $tags = array ();
	var $categories = array ();

	var $j = -1;
	var $fetch_attachments = false;
	var $url_remap = array ();

	function header() {
		echo '<div class="wrap">';
		echo '<h2>'.__('Import WordPress').'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function unhtmlentities($string) { // From php.net for < 4.3 compat
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);
		return strtr($string, $trans_tbl);
	}

	function greet() {
		echo '<div class="narrow">';
		echo '<p>'.__('Howdy! Upload your WordPress eXtended RSS (WXR) file and we&#8217;ll import the posts, comments, custom fields, and categories into this blog.').'</p>';
		echo '<p>'.__('Choose a WordPress WXR file to upload, then click Upload file and import.').'</p>';
		wp_import_upload_form("admin.php?import=wordpress&amp;step=1");
		echo '</div>';
	}

	function get_tag( $string, $tag ) {
		global $wpdb;
		preg_match("|<$tag.*?>(.*?)</$tag>|is", $string, $return);
		$return = preg_replace('|^<!\[CDATA\[(.*)\]\]>$|s', '$1', $return[1]);
		$return = $wpdb->escape( trim( $return ) );
		return $return;
	}

	function has_gzip() {
		return is_callable('gzopen');
	}

	function fopen($filename, $mode='r') {
		if ( $this->has_gzip() )
			return gzopen($filename, $mode);
		return fopen($filename, $mode);
	}

	function feof($fp) {
		if ( $this->has_gzip() )
			return gzeof($fp);
		return feof($fp);
	}

	function fgets($fp, $len=8192) {
		if ( $this->has_gzip() )
			return gzgets($fp, $len);
		return fgets($fp, $len);
	}

	function fclose($fp) {
		if ( $this->has_gzip() )
			return gzclose($fp);
		return fclose($fp);
	}

	function get_entries($process_post_func=NULL) {
		set_magic_quotes_runtime(0);

		$doing_entry = false;
		$is_wxr_file = false;

		$fp = $this->fopen($this->file, 'r');
		if ($fp) {
			while ( !$this->feof($fp) ) {
				$importline = rtrim($this->fgets($fp));

				// this doesn't check that the file is perfectly valid but will at least confirm that it's not the wrong format altogether
				if ( !$is_wxr_file && preg_match('|xmlns:wp="http://wordpress[.]org/export/\d+[.]\d+/"|', $importline) )
					$is_wxr_file = true;

				if ( false !== strpos($importline, '<wp:category>') ) {
					preg_match('|<wp:category>(.*?)</wp:category>|is', $importline, $category);
					$this->categories[] = $category[1];
					continue;
				}
				if ( false !== strpos($importline, '<wp:tag>') ) {
					preg_match('|<wp:tag>(.*?)</wp:tag>|is', $importline, $tag);
					$this->tags[] = $tag[1];
					continue;
				}
				if ( false !== strpos($importline, '<item>') ) {
					$this->post = '';
					$doing_entry = true;
					continue;
				}
				if ( false !== strpos($importline, '</item>') ) {
					$doing_entry = false;
					if ($process_post_func)
						call_user_func($process_post_func, $this->post);
					continue;
				}
				if ( $doing_entry ) {
					$this->post .= $importline . "\n";
				}
			}

			$this->fclose($fp);
		}

		return $is_wxr_file;

	}

	function get_wp_authors() {
		// We need to find unique values of author names, while preserving the order, so this function emulates the unique_value(); php function, without the sorting.
		$temp = $this->allauthornames;
		$authors[0] = array_shift($temp);
		$y = count($temp) + 1;
		for ($x = 1; $x < $y; $x ++) {
			$next = array_shift($temp);
			if (!(in_array($next, $authors)))
				array_push($authors, "$next");
		}

		return $authors;
	}

	function get_authors_from_post() {
		global $current_user;

		// this will populate $this->author_ids with a list of author_names => user_ids

		foreach ( $_POST['author_in'] as $i => $in_author_name ) {

			if ( !empty($_POST['user_select'][$i]) ) {
				// an existing user was selected in the dropdown list
				$user = get_userdata( intval($_POST['user_select'][$i]) );
				if ( isset($user->ID) )
					$this->author_ids[$in_author_name] = $user->ID;
			}
			elseif ( $this->allow_create_users() ) {
				// nothing was selected in the dropdown list, so we'll use the name in the text field

				$new_author_name = trim($_POST['user_create'][$i]);
				// if the user didn't enter a name, assume they want to use the same name as in the import file
				if ( empty($new_author_name) )
					$new_author_name = $in_author_name;

				$user_id = username_exists($new_author_name);
				if ( !$user_id ) {
					$user_id = wp_create_user($new_author_name, wp_generate_password());
				}

				$this->author_ids[$in_author_name] = $user_id;
			}

			// failsafe: if the user_id was invalid, default to the current user
			if ( empty($this->author_ids[$in_author_name]) ) {
				$this->author_ids[$in_author_name] = intval($current_user->ID);
			}
		}

	}

	function wp_authors_form() {
?>
<h2><?php _e('Assign Authors'); ?></h2>
<p><?php _e('To make it easier for you to edit and save the imported posts and drafts, you may want to change the name of the author of the posts. For example, you may want to import all the entries as <code>admin</code>s entries.'); ?></p>
<?php
	if ( $this->allow_create_users() ) {
		echo '<p>'.__('If a new user is created by WordPress, a password will be randomly generated. Manually change the user\'s details if necessary.')."</p>\n";
	}


		$authors = $this->get_wp_authors();
		echo '<ol id="authors">';
		echo '<form action="?import=wordpress&amp;step=2&amp;id=' . $this->id . '" method="post">';
		wp_nonce_field('import-wordpress');
		$j = -1;
		foreach ($authors as $author) {
			++ $j;
			echo '<li>'.__('Import author:').' <strong>'.$author.'</strong><br />';
			$this->users_form($j, $author);
			echo '</li>';
		}

		if ( $this->allow_fetch_attachments() ) {
?>
</ol>
<h2><?php _e('Import Attachments'); ?></h2>
<p>
	<input type="checkbox" value="1" name="attachments" id="import-attachments" />
	<label for="import-attachments"><?php _e('Download and import file attachments') ?></label>
</p>

<?php
		}

		echo '<input type="submit" value="'.attribute_escape( __('Submit') ).'">'.'<br />';
		echo '</form>';

	}

	function users_form($n, $author) {

		if ( $this->allow_create_users() ) {
			printf('<label>'.__('Create user %1$s or map to existing'), ' <input type="text" value="'.$author.'" name="'.'user_create['.intval($n).']'.'" maxlength="30"></label> <br />');
		}
		else {
			echo __('Map to existing').'<br />';
		}

		// keep track of $n => $author name
		echo '<input type="hidden" name="author_in['.intval($n).']" value="'.htmlspecialchars($author).'" />';

		$users = get_users_of_blog();
?><select name="user_select[<?php echo $n; ?>]">
	<option value="0"><?php _e('- Select -'); ?></option>
	<?php
		foreach ($users as $user) {
			echo '<option value="'.$user->user_id.'">'.$user->user_login.'</option>';
		}
?>
	</select>
	<?php
	}

	function select_authors() {
		$is_wxr_file = $this->get_entries(array(&$this, 'process_author'));
		if ( $is_wxr_file ) {
			$this->wp_authors_form();
		}
		else {
			echo '<h2>'.__('Invalid file').'</h2>';
			echo '<p>'.__('Please upload a valid WXR (WordPress eXtended RSS) export file.').'</p>';
		}
	}

	// fetch the user ID for a given author name, respecting the mapping preferences
	function checkauthor($author) {
		global $current_user;

		if ( !empty($this->author_ids[$author]) )
			return $this->author_ids[$author];

		// failsafe: map to the current user
		return $current_user->ID;
	}



	function process_categories() {
		global $wpdb;

		$cat_names = (array) get_terms('category', 'fields=names');

		while ( $c = array_shift($this->categories) ) {
			$cat_name = trim($this->get_tag( $c, 'wp:cat_name' ));

			// If the category exists we leave it alone
			if ( in_array($cat_name, $cat_names) )
				continue;

			$category_nicename	= $this->get_tag( $c, 'wp:category_nicename' );
			$posts_private		= (int) $this->get_tag( $c, 'wp:posts_private' );
			$links_private		= (int) $this->get_tag( $c, 'wp:links_private' );

			$parent = $this->get_tag( $c, 'wp:category_parent' );

			if ( empty($parent) )
				$category_parent = '0';
			else
				$category_parent = category_exists($parent);

			$catarr = compact('category_nicename', 'category_parent', 'posts_private', 'links_private', 'posts_private', 'cat_name');

			$cat_ID = wp_insert_category($catarr);
		}
	}

	function process_tags() {
		global $wpdb;

		$tag_names = (array) get_terms('post_tag', 'fields=names');

		while ( $c = array_shift($this->tags) ) {
			$tag_name = trim($this->get_tag( $c, 'wp:tag_name' ));

			// If the category exists we leave it alone
			if ( in_array($tag_name, $tag_names) )
				continue;

			$slug = $this->get_tag( $c, 'wp:tag_slug' );
			$description = $this->get_tag( $c, 'wp:tag_description' );

			$tagarr = compact('slug', 'description');

			$tag_ID = wp_insert_term($tag_name, 'post_tag', $tagarr);
		}
	}

	function process_author($post) {
		$author = $this->get_tag( $post, 'dc:creator' );
		if ($author)
			$this->allauthornames[] = $author;
	}

	function process_posts() {
		$i = -1;
		echo '<ol>';

		$this->get_entries(array(&$this, 'process_post'));

		echo '</ol>';

		wp_import_cleanup($this->id);
		do_action('import_done', 'wordpress');

		echo '<h3>'.sprintf(__('All done.').' <a href="%s">'.__('Have fun!').'</a>', get_option('home')).'</h3>';
	}

	function process_post($post) {
		global $wpdb;

		$post_ID = (int) $this->get_tag( $post, 'wp:post_id' );
  		if ( $post_ID && !empty($this->post_ids_processed[$post_ID]) ) // Processed already
			return 0;

		set_time_limit( 60 );

		// There are only ever one of these
		$post_title     = $this->get_tag( $post, 'title' );
		$post_date      = $this->get_tag( $post, 'wp:post_date' );
		$post_date_gmt  = $this->get_tag( $post, 'wp:post_date_gmt' );
		$comment_status = $this->get_tag( $post, 'wp:comment_status' );
		$ping_status    = $this->get_tag( $post, 'wp:ping_status' );
		$post_status    = $this->get_tag( $post, 'wp:status' );
		$post_name      = $this->get_tag( $post, 'wp:post_name' );
		$post_parent    = $this->get_tag( $post, 'wp:post_parent' );
		$menu_order     = $this->get_tag( $post, 'wp:menu_order' );
		$post_type      = $this->get_tag( $post, 'wp:post_type' );
		$post_password  = $this->get_tag( $post, 'wp:post_password' );
		$guid           = $this->get_tag( $post, 'guid' );
		$post_author    = $this->get_tag( $post, 'dc:creator' );

		$post_excerpt = $this->get_tag( $post, 'excerpt:encoded' );
		$post_excerpt = preg_replace('|<(/?[A-Z]+)|e', "'<' . strtolower('$1')", $post_excerpt);
		$post_excerpt = str_replace('<br>', '<br />', $post_excerpt);
		$post_excerpt = str_replace('<hr>', '<hr />', $post_excerpt);

		$post_content = $this->get_tag( $post, 'content:encoded' );
		$post_content = preg_replace('|<(/?[A-Z]+)|e', "'<' . strtolower('$1')", $post_content);
		$post_content = str_replace('<br>', '<br />', $post_content);
		$post_content = str_replace('<hr>', '<hr />', $post_content);

		preg_match_all('|<category domain="tag">(.*?)</category>|is', $post, $tags);
		$tags = $tags[1];

		$tag_index = 0;
		foreach ($tags as $tag) {
			$tags[$tag_index] = $wpdb->escape($this->unhtmlentities(str_replace(array ('<![CDATA[', ']]>'), '', $tag)));
			$tag_index++;
		}

		preg_match_all('|<category>(.*?)</category>|is', $post, $categories);
		$categories = $categories[1];

		$cat_index = 0;
		foreach ($categories as $category) {
			$categories[$cat_index] = $wpdb->escape($this->unhtmlentities(str_replace(array ('<![CDATA[', ']]>'), '', $category)));
			$cat_index++;
		}

		$post_exists = post_exists($post_title, '', $post_date);

		if ( $post_exists ) {
			echo '<li>';
			printf(__('Post <em>%s</em> already exists.'), stripslashes($post_title));
		} else {

			// If it has parent, process parent first.
			$post_parent = (int) $post_parent;
			if ($post_parent) {
				// if we already know the parent, map it to the local ID
				if ( $parent = $this->post_ids_processed[$post_parent] ) {
					$post_parent = $parent;  // new ID of the parent
				}
				else {
					// record the parent for later
					$this->orphans[intval($post_ID)] = $post_parent;
				}
			}

			echo '<li>';

			$post_author = $this->checkauthor($post_author); //just so that if a post already exists, new users are not created by checkauthor

			$postdata = compact('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_excerpt', 'post_title', 'post_status', 'post_name', 'comment_status', 'ping_status', 'guid', 'post_parent', 'menu_order', 'post_type', 'post_password');
			if ($post_type == 'attachment') {
				$remote_url = $this->get_tag( $post, 'wp:attachment_url' );
				if ( !$remote_url )
					$remote_url = $guid;

				$comment_post_ID = $post_id = $this->process_attachment($postdata, $remote_url);
				if ( !$post_id or is_wp_error($post_id) )
					return $post_id;
			}
			else {
				printf(__('Importing post <em>%s</em>...'), stripslashes($post_title));
				$comment_post_ID = $post_id = wp_insert_post($postdata);
			}

			if ( is_wp_error( $post_id ) )
				return $post_id;

			// Memorize old and new ID.
			if ( $post_id && $post_ID ) {
				$this->post_ids_processed[intval($post_ID)] = intval($post_id);
			}

			// Add categories.
			if (count($categories) > 0) {
				$post_cats = array();
				foreach ($categories as $category) {
					$slug = sanitize_term_field('slug', $category, 0, 'category', 'db');
					$cat = get_term_by('slug', $slug, 'category');
					$cat_ID = 0;
					if ( ! empty($cat) )
						$cat_ID = $cat->term_id;
					if ($cat_ID == 0) {
						$category = $wpdb->escape($category);
						$cat_ID = wp_insert_category(array('cat_name' => $category));
					}
					$post_cats[] = $cat_ID;
				}
				wp_set_post_categories($post_id, $post_cats);
			}

			// Add tags.
			if (count($tags) > 0) {
				$post_tags = array();
				foreach ($tags as $tag) {
					$slug = sanitize_term_field('slug', $tag, 0, 'post_tag', 'db');
					$tag_obj = get_term_by('slug', $slug, 'post_tag');
					$tag_id = 0;
					if ( ! empty($tag_obj) )
						$tag_id = $tag_obj->term_id;
					if ( $tag_id == 0 ) {
						$tag = $wpdb->escape($tag);
						$tag_id = wp_insert_term($tag, 'post_tag');
						$tag_id = $tag_id['term_id'];
					}
					$post_tags[] = intval($tag_id);
				}
				wp_set_post_tags($post_id, $post_tags);
			}
		}

		// Now for comments
		preg_match_all('|<wp:comment>(.*?)</wp:comment>|is', $post, $comments);
		$comments = $comments[1];
		$num_comments = 0;
		if ( $comments) { foreach ($comments as $comment) {
			$comment_author       = $this->get_tag( $comment, 'wp:comment_author');
			$comment_author_email = $this->get_tag( $comment, 'wp:comment_author_email');
			$comment_author_IP    = $this->get_tag( $comment, 'wp:comment_author_IP');
			$comment_author_url   = $this->get_tag( $comment, 'wp:comment_author_url');
			$comment_date         = $this->get_tag( $comment, 'wp:comment_date');
			$comment_date_gmt     = $this->get_tag( $comment, 'wp:comment_date_gmt');
			$comment_content      = $this->get_tag( $comment, 'wp:comment_content');
			$comment_approved     = $this->get_tag( $comment, 'wp:comment_approved');
			$comment_type         = $this->get_tag( $comment, 'wp:comment_type');
			$comment_parent       = $this->get_tag( $comment, 'wp:comment_parent');

			// if this is a new post we can skip the comment_exists() check
			if ( !$post_exists || !comment_exists($comment_author, $comment_date) ) {
				$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_approved', 'comment_type', 'comment_parent');
				wp_insert_comment($commentdata);
				$num_comments++;
			}
		} }

		if ( $num_comments )
			printf(' '.__ngettext('(%s comment)', '(%s comments)', $num_comments), $num_comments);

		// Now for post meta
		preg_match_all('|<wp:postmeta>(.*?)</wp:postmeta>|is', $post, $postmeta);
		$postmeta = $postmeta[1];
		if ( $postmeta) { foreach ($postmeta as $p) {
			$key   = $this->get_tag( $p, 'wp:meta_key' );
			$value = $this->get_tag( $p, 'wp:meta_value' );
			$value = stripslashes($value); // add_post_meta() will escape.

			$this->process_post_meta($post_id, $key, $value);

		} }

		do_action('import_post_added', $post_id);
		print "</li>\n";
	}

	function process_post_meta($post_id, $key, $value) {
		// the filter can return false to skip a particular metadata key
		$_key = apply_filters('import_post_meta_key', $key);
		if ( $_key ) {
			add_post_meta( $post_id, $_key, $value );
			do_action('import_post_meta', $post_id, $_key, $value);
		}
	}

	function process_attachment($postdata, $remote_url) {
		if ($this->fetch_attachments and $remote_url) {
			printf( __('Importing attachment <em>%s</em>... '), htmlspecialchars($remote_url) );
			$upload = $this->fetch_remote_file($postdata, $remote_url);
			if ( is_wp_error($upload) ) {
				printf( __('Remote file error: %s'), htmlspecialchars($upload->get_error_message()) );
				return $upload;
			}
			else {
				print '('.size_format(filesize($upload['file'])).')';
			}

			if ( $info = wp_check_filetype($upload['file']) ) {
				$postdata['post_mime_type'] = $info['type'];
			}
			else {
				print __('Invalid file type');
				return;
			}

			$postdata['guid'] = $upload['url'];

			// as per wp-admin/includes/upload.php
			$post_id = wp_insert_attachment($postdata, $upload['file']);
			wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

			// remap the thumbnail url.  this isn't perfect because we're just guessing the original url.
			if ( preg_match('@^image/@', $info['type']) && $thumb_url = wp_get_attachment_thumb_url($post_id) ) {
				$parts = pathinfo($remote_url);
				$ext = $parts['extension'];
				$name = basename($parts['basename'], ".{$ext}");
				$this->url_remap[$parts['dirname'] . '/' . $name . '.thumbnail.' . $ext] = $thumb_url;
			}

			return $post_id;
		}
		else {
			printf( __('Skipping attachment <em>%s</em>'), htmlspecialchars($remote_url) );
		}
	}

	function fetch_remote_file($post, $url) {
		$upload = wp_upload_dir($post['post_date']);

		// extract the file name and extension from the url
		$file_name = basename($url);

		// get placeholder file in the upload dir with a unique sanitized filename
		$upload = wp_upload_bits( $file_name, 0, '', $post['post_date']);
		if ( $upload['error'] ) {
			echo $upload['error'];
			return new WP_Error( 'upload_dir_error', $upload['error'] );
		}

		// fetch the remote url and write it to the placeholder file
		$headers = wp_get_http($url, $upload['file']);

		// make sure the fetch was successful
		if ( $headers['response'] != '200' ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', sprintf(__('Remote file returned error response %d'), intval($headers['response'])) );
		}
		elseif ( isset($headers['content-length']) && filesize($upload['file']) != $headers['content-length'] ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', __('Remote file is incorrect size') );
		}

		$max_size = $this->max_attachment_size();
		if ( !empty($max_size) and filesize($upload['file']) > $max_size ) {
			@unlink($upload['file']);
			return new WP_Error( 'import_file_error', sprintf(__('Remote file is too large, limit is %s', size_format($max_size))) );
		}

		// keep track of the old and new urls so we can substitute them later
		$this->url_remap[$url] = $upload['url'];
		// if the remote url is redirected somewhere else, keep track of the destination too
		if ( $headers['x-final-location'] != $url )
			$this->url_remap[$headers['x-final-location']] = $upload['url'];

		return $upload;

	}

	// sort by strlen, longest string first
	function cmpr_strlen($a, $b) {
		return strlen($b) - strlen($a);
	}

	// update url references in post bodies to point to the new local files
	function backfill_attachment_urls() {

		// make sure we do the longest urls first, in case one is a substring of another
		uksort($this->url_remap, array(&$this, 'cmpr_strlen'));

		global $wpdb;
		foreach ($this->url_remap as $from_url => $to_url) {
			// remap urls in post_content
			$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, '%s', '%s')", $from_url, $to_url) );
			// remap enclosure urls
			$result = $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, '%s', '%s') WHERE meta_key='enclosure'", $from_url, $to_url) );
		}
	}

	// update the post_parent of orphans now that we know the local id's of all parents
	function backfill_parents() {
		global $wpdb;

		foreach ($this->orphans as $child_id => $parent_id) {
			$local_child_id = $this->post_ids_processed[$child_id];
			$local_parent_id = $this->post_ids_processed[$parent_id];
			if ($local_child_id and $local_parent_id) {
				$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_parent = %d WHERE ID = %d", $local_parent_id, $local_child_id));
			}
		}
	}

	function is_valid_meta_key($key) {
		// skip _wp_attached_file metadata since we'll regenerate it from scratch
		if ( $key == '_wp_attached_file' )
			return false;
		return $key;
	}

	// give the user the option of creating new users to represent authors in the import file?
	function allow_create_users() {
		return apply_filters('import_allow_create_users', true);
	}

	// give the user the option of downloading and importing attached files
	function allow_fetch_attachments() {
		return apply_filters('import_allow_fetch_attachments', true);
	}

	function max_attachment_size() {
		// can be overridden with a filter - 0 means no limit
		return apply_filters('import_attachment_size_limit', 0);
	}

	function import_start() {
		wp_defer_term_counting(true);
		wp_defer_comment_counting(true);
		do_action('import_start');
	}

	function import_end() {
		do_action('import_end');

		// clear the caches after backfilling
		foreach ($this->post_ids_processed as $post_id)
			clean_post_cache($post_id);

		wp_defer_term_counting(false);
		wp_defer_comment_counting(false);
	}

	function import($id, $fetch_attachments = false) {
		$this->id = (int) $id;
		$this->fetch_attachments = ($this->allow_fetch_attachments() && (bool) $fetch_attachments);

		add_filter('import_post_meta_key', array($this, 'is_valid_meta_key'));
		$file = get_attached_file($this->id);
		$this->import_file($file);
	}

	function import_file($file) {
		$this->file = $file;

		$this->import_start();
		$this->get_authors_from_post();
		$this->get_entries();
		$this->process_categories();
		$this->process_tags();
		$result = $this->process_posts();
		$this->backfill_parents();
		$this->backfill_attachment_urls();
		$this->import_end();

		if ( is_wp_error( $result ) )
			return $result;
	}

	function handle_upload() {
		$file = wp_import_handle_upload();
		if ( isset($file['error']) ) {
			echo '<p>'.__('Sorry, there has been an error.').'</p>';
			echo '<p><strong>' . $file['error'] . '</strong></p>';
			return false;
		}
		$this->file = $file['file'];
		$this->id = (int) $file['id'];
		return true;
	}

	function dispatch() {
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		$this->header();
		switch ($step) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import-upload');
				if ( $this->handle_upload() )
					$this->select_authors();
				break;
			case 2:
				check_admin_referer('import-wordpress');
				$result = $this->import( $_GET['id'], $_POST['attachments'] );
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}
		$this->footer();
	}

	function WP_Import() {
		// Nothing.
	}
}

$wp_import = new WP_Import();

register_importer('wordpress', 'WordPress', __('Import <strong>posts, comments, custom fields, pages, and categories</strong> from a WordPress export file.'), array ($wp_import, 'dispatch'));

?>
