<?php
/**
 * LiveJournal Importer
 *
 * @package WordPress
 * @subpackage Importer
 */

/**
 * LiveJournal Importer class
 *
 * Imports your LiveJournal XML exported file into WordPress.
 *
 * @since unknown
 */
class LJ_Import {

	var $file;

	function header() {
		echo '<div class="wrap">';
		echo '<h2>'.__('Import LiveJournal').'</h2>';
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
		echo '<p>'.__('Howdy! Upload your LiveJournal XML export file and we&#8217;ll import the posts into this blog.').'</p>';
		echo '<p>'.__('Choose a LiveJournal XML file to upload, then click Upload file and import.').'</p>';
		wp_import_upload_form("admin.php?import=livejournal&amp;step=1");
		echo '</div>';
	}

	function import_posts() {
		global $wpdb, $current_user;

		set_magic_quotes_runtime(0);
		$importdata = file($this->file); // Read the file into an array
		$importdata = implode('', $importdata); // squish it
		$importdata = str_replace(array ("\r\n", "\r"), "\n", $importdata);

		preg_match_all('|<entry>(.*?)</entry>|is', $importdata, $posts);
		$posts = $posts[1];
		unset($importdata);
		echo '<ol>';
		foreach ($posts as $post) {
			preg_match('|<subject>(.*?)</subject>|is', $post, $post_title);
			$post_title = $wpdb->escape(trim($post_title[1]));
			if ( empty($post_title) ) {
				preg_match('|<itemid>(.*?)</itemid>|is', $post, $post_title);
				$post_title = $wpdb->escape(trim($post_title[1]));
			}

			preg_match('|<eventtime>(.*?)</eventtime>|is', $post, $post_date);
			$post_date = strtotime($post_date[1]);
			$post_date = date('Y-m-d H:i:s', $post_date);

			preg_match('|<event>(.*?)</event>|is', $post, $post_content);
			$post_content = str_replace(array ('<![CDATA[', ']]>'), '', trim($post_content[1]));
			$post_content = $this->unhtmlentities($post_content);

			// Clean up content
			$post_content = preg_replace('|<(/?[A-Z]+)|e', "'<' . strtolower('$1')", $post_content);
			$post_content = str_replace('<br>', '<br />', $post_content);
			$post_content = str_replace('<hr>', '<hr />', $post_content);
			$post_content = $wpdb->escape($post_content);

			$post_author = $current_user->ID;
			$post_status = 'publish';

			echo '<li>';
			if ($post_id = post_exists($post_title, $post_content, $post_date)) {
				printf(__('Post <em>%s</em> already exists.'), stripslashes($post_title));
			} else {
				printf(__('Importing post <em>%s</em>...'), stripslashes($post_title));
				$postdata = compact('post_author', 'post_date', 'post_content', 'post_title', 'post_status');
				$post_id = wp_insert_post($postdata);
				if ( is_wp_error( $post_id ) )
					return $post_id;
				if (!$post_id) {
					_e("Couldn't get post ID");
					echo '</li>';
					break;
				}
			}

			preg_match_all('|<comment>(.*?)</comment>|is', $post, $comments);
			$comments = $comments[1];

			if ( $comments ) {
				$comment_post_ID = (int) $post_id;
				$num_comments = 0;
				foreach ($comments as $comment) {
					preg_match('|<event>(.*?)</event>|is', $comment, $comment_content);
					$comment_content = str_replace(array ('<![CDATA[', ']]>'), '', trim($comment_content[1]));
					$comment_content = $this->unhtmlentities($comment_content);

					// Clean up content
					$comment_content = preg_replace('|<(/?[A-Z]+)|e', "'<' . strtolower('$1')", $comment_content);
					$comment_content = str_replace('<br>', '<br />', $comment_content);
					$comment_content = str_replace('<hr>', '<hr />', $comment_content);
					$comment_content = $wpdb->escape($comment_content);

					preg_match('|<eventtime>(.*?)</eventtime>|is', $comment, $comment_date);
					$comment_date = trim($comment_date[1]);
					$comment_date = date('Y-m-d H:i:s', strtotime($comment_date));

					preg_match('|<name>(.*?)</name>|is', $comment, $comment_author);
					$comment_author = $wpdb->escape(trim($comment_author[1]));

					preg_match('|<email>(.*?)</email>|is', $comment, $comment_author_email);
					$comment_author_email = $wpdb->escape(trim($comment_author_email[1]));

					$comment_approved = 1;
					// Check if it's already there
					if (!comment_exists($comment_author, $comment_date)) {
						$commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_date', 'comment_content', 'comment_approved');
						$commentdata = wp_filter_comment($commentdata);
						wp_insert_comment($commentdata);
						$num_comments++;
					}
				}
			}
			if ( $num_comments ) {
				echo ' ';
				printf(__ngettext('(%s comment)', '(%s comments)', $num_comments), $num_comments);
			}
			echo '</li>';
		}
		echo '</ol>';
	}

	function import() {
		$file = wp_import_handle_upload();
		if ( isset($file['error']) ) {
			echo $file['error'];
			return;
		}

		$this->file = $file['file'];
		$result = $this->import_posts();
		if ( is_wp_error( $result ) )
			return $result;
		wp_import_cleanup($file['id']);
		do_action('import_done', 'livejournal');

		echo '<h3>';
		printf(__('All done. <a href="%s">Have fun!</a>'), get_option('home'));
		echo '</h3>';
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
				$result = $this->import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}

		$this->footer();
	}

	function LJ_Import() {
		// Nothing.
	}
}

$livejournal_import = new LJ_Import();

register_importer('livejournal', __('LiveJournal'), __('Import posts from a LiveJournal XML export file.'), array ($livejournal_import, 'dispatch'));
?>
