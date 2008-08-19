<?php
/**
 * DotClear Importer
 *
 * @package WordPress
 * @subpackage Importer
 * @author Thomas Quinot
 * @link http://thomas.quinot.org/
 */

/**
	Add These Functions to make our lives easier
**/

if(!function_exists('get_comment_count'))
{
	/**
	 * Get the comment count for posts.
	 *
	 * @package WordPress
	 * @subpackage Dotclear_Import
	 *
	 * @param int $post_ID Post ID
	 * @return int
	 */
	function get_comment_count($post_ID)
	{
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare("SELECT count(*) FROM $wpdb->comments WHERE comment_post_ID = %d", $post_ID) );
	}
}

if(!function_exists('link_exists'))
{
	/**
	 * Check whether link already exists.
	 *
	 * @package WordPress
	 * @subpackage Dotclear_Import
	 *
	 * @param string $linkname
	 * @return int
	 */
	function link_exists($linkname)
	{
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_name = %s", $linkname) );
	}
}

/*
 Identify UTF-8 text
 Taken from http://www.php.net/manual/fr/function.mb-detect-encoding.php#50087
*/
//
//    utf8 encoding validation developed based on Wikipedia entry at:
//    http://en.wikipedia.org/wiki/UTF-8
//
//    Implemented as a recursive descent parser based on a simple state machine
//    copyright 2005 Maarten Meijer
//
//    This cries out for a C-implementation to be included in PHP core
//

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $char
 * @return string
 */
function valid_1byte($char) {
	if(!is_int($char)) return false;
		return ($char & 0x80) == 0x00;
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $char
 * @return string
 */
function valid_2byte($char) {
	if(!is_int($char)) return false;
		return ($char & 0xE0) == 0xC0;
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $char
 * @return string
 */
function valid_3byte($char) {
	if(!is_int($char)) return false;
		return ($char & 0xF0) == 0xE0;
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $char
 * @return string
 */
function valid_4byte($char) {
	if(!is_int($char)) return false;
		return ($char & 0xF8) == 0xF0;
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $char
 * @return string
 */
function valid_nextbyte($char) {
	if(!is_int($char)) return false;
		return ($char & 0xC0) == 0x80;
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $string
 * @return string
 */
function valid_utf8($string) {
	$len = strlen($string);
	$i = 0;
	while( $i < $len ) {
		$char = ord(substr($string, $i++, 1));
		if(valid_1byte($char)) {    // continue
			continue;
		} else if(valid_2byte($char)) { // check 1 byte
			if(!valid_nextbyte(ord(substr($string, $i++, 1))))
				return false;
		} else if(valid_3byte($char)) { // check 2 bytes
			if(!valid_nextbyte(ord(substr($string, $i++, 1))))
				return false;
			if(!valid_nextbyte(ord(substr($string, $i++, 1))))
				return false;
		} else if(valid_4byte($char)) { // check 3 bytes
			if(!valid_nextbyte(ord(substr($string, $i++, 1))))
				return false;
			if(!valid_nextbyte(ord(substr($string, $i++, 1))))
				return false;
			if(!valid_nextbyte(ord(substr($string, $i++, 1))))
				return false;
		} // goto next char
	}
	return true; // done
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $s
 * @return string
 */
function csc ($s) {
	if (valid_utf8 ($s)) {
		return $s;
	} else {
		return iconv(get_option ("dccharset"),"UTF-8",$s);
	}
}

/**
 * @package WordPress
 * @subpackage Dotclear_Import
 *
 * @param string $s
 * @return string
 */
function textconv ($s) {
	return csc (preg_replace ('|(?<!<br />)\s*\n|', ' ', $s));
}

/**
 * Dotclear Importer class
 *
 * Will process the WordPress eXtended RSS files that you upload from the export
 * file.
 *
 * @package WordPress
 * @subpackage Importer
 *
 * @since unknown
 */
class Dotclear_Import {

	function header()
	{
		echo '<div class="wrap">';
		echo '<h2>'.__('Import DotClear').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.').'</p>';
	}

	function footer()
	{
		echo '</div>';
	}

	function greet()
	{
		echo '<div class="narrow"><p>'.__('Howdy! This importer allows you to extract posts from a DotClear database into your blog.  Mileage may vary.').'</p>';
		echo '<p>'.__('Your DotClear Configuration settings are as follows:').'</p>';
		echo '<form action="admin.php?import=dotclear&amp;step=1" method="post">';
		wp_nonce_field('import-dotclear');
		$this->db_form();
		echo '<p class="submit"><input type="submit" name="submit" value="'.attribute_escape(__('Import Categories')).'" /></p>';
		echo '</form></div>';
	}

	function get_dc_cats()
	{
		global $wpdb;
		// General Housekeeping
		$dcdb = new wpdb(get_option('dcuser'), get_option('dcpass'), get_option('dcname'), get_option('dchost'));
		set_magic_quotes_runtime(0);
		$dbprefix = get_option('dcdbprefix');

		// Get Categories
		return $dcdb->get_results('SELECT * FROM '.$dbprefix.'categorie', ARRAY_A);
	}

	function get_dc_users()
	{
		global $wpdb;
		// General Housekeeping
		$dcdb = new wpdb(get_option('dcuser'), get_option('dcpass'), get_option('dcname'), get_option('dchost'));
		set_magic_quotes_runtime(0);
		$dbprefix = get_option('dcdbprefix');

		// Get Users

		return $dcdb->get_results('SELECT * FROM '.$dbprefix.'user', ARRAY_A);
	}

	function get_dc_posts()
	{
		// General Housekeeping
		$dcdb = new wpdb(get_option('dcuser'), get_option('dcpass'), get_option('dcname'), get_option('dchost'));
		set_magic_quotes_runtime(0);
		$dbprefix = get_option('dcdbprefix');

		// Get Posts
		return $dcdb->get_results('SELECT '.$dbprefix.'post.*, '.$dbprefix.'categorie.cat_libelle_url AS post_cat_name
						FROM '.$dbprefix.'post INNER JOIN '.$dbprefix.'categorie
						ON '.$dbprefix.'post.cat_id = '.$dbprefix.'categorie.cat_id', ARRAY_A);
	}

	function get_dc_comments()
	{
		global $wpdb;
		// General Housekeeping
		$dcdb = new wpdb(get_option('dcuser'), get_option('dcpass'), get_option('dcname'), get_option('dchost'));
		set_magic_quotes_runtime(0);
		$dbprefix = get_option('dcdbprefix');

		// Get Comments
		return $dcdb->get_results('SELECT * FROM '.$dbprefix.'comment', ARRAY_A);
	}

	function get_dc_links()
	{
		//General Housekeeping
		$dcdb = new wpdb(get_option('dcuser'), get_option('dcpass'), get_option('dcname'), get_option('dchost'));
		set_magic_quotes_runtime(0);
		$dbprefix = get_option('dcdbprefix');

		return $dcdb->get_results('SELECT * FROM '.$dbprefix.'link ORDER BY position', ARRAY_A);
	}

	function cat2wp($categories='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$dccat2wpcat = array();
		// Do the Magic
		if(is_array($categories))
		{
			echo '<p>'.__('Importing Categories...').'<br /><br /></p>';
			foreach ($categories as $category)
			{
				$count++;
				extract($category);

				// Make Nice Variables
				$name = $wpdb->escape($cat_libelle_url);
				$title = $wpdb->escape(csc ($cat_libelle));
				$desc = $wpdb->escape(csc ($cat_desc));

				if($cinfo = category_exists($name))
				{
					$ret_id = wp_insert_category(array('cat_ID' => $cinfo, 'category_nicename' => $name, 'cat_name' => $title, 'category_description' => $desc));
				}
				else
				{
					$ret_id = wp_insert_category(array('category_nicename' => $name, 'cat_name' => $title, 'category_description' => $desc));
				}
				$dccat2wpcat[$id] = $ret_id;
			}

			// Store category translation for future use
			add_option('dccat2wpcat',$dccat2wpcat);
			echo '<p>'.sprintf(__ngettext('Done! <strong>%1$s</strong> category imported.', 'Done! <strong>%1$s</strong> categories imported.', $count), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Categories to Import!');
		return false;
	}

	function users2wp($users='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$dcid2wpid = array();

		// Midnight Mojo
		if(is_array($users))
		{
			echo '<p>'.__('Importing Users...').'<br /><br /></p>';
			foreach($users as $user)
			{
				$count++;
				extract($user);

				// Make Nice Variables
				$name = $wpdb->escape(csc ($name));
				$RealName = $wpdb->escape(csc ($user_pseudo));

				if($uinfo = get_userdatabylogin($name))
				{

					$ret_id = wp_insert_user(array(
								'ID'		=> $uinfo->ID,
								'user_login'	=> $user_id,
								'user_nicename'	=> $Realname,
								'user_email'	=> $user_email,
								'user_url'	=> 'http://',
								'display_name'	=> $Realname)
								);
				}
				else
				{
					$ret_id = wp_insert_user(array(
								'user_login'	=> $user_id,
								'user_nicename'	=> csc ($user_pseudo),
								'user_email'	=> $user_email,
								'user_url'	=> 'http://',
								'display_name'	=> $Realname)
								);
				}
				$dcid2wpid[$user_id] = $ret_id;

				// Set DotClear-to-WordPress permissions translation

				// Update Usermeta Data
				$user = new WP_User($ret_id);
				$wp_perms = $user_level + 1;
				if(10 == $wp_perms) { $user->set_role('administrator'); }
				else if(9  == $wp_perms) { $user->set_role('editor'); }
				else if(5  <= $wp_perms) { $user->set_role('editor'); }
				else if(4  <= $wp_perms) { $user->set_role('author'); }
				else if(3  <= $wp_perms) { $user->set_role('contributor'); }
				else if(2  <= $wp_perms) { $user->set_role('contributor'); }
				else                     { $user->set_role('subscriber'); }

				update_usermeta( $ret_id, 'wp_user_level', $wp_perms);
				update_usermeta( $ret_id, 'rich_editing', 'false');
				update_usermeta( $ret_id, 'first_name', csc ($user_prenom));
				update_usermeta( $ret_id, 'last_name', csc ($user_nom));
			}// End foreach($users as $user)

			// Store id translation array for future use
			add_option('dcid2wpid',$dcid2wpid);


			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> users imported.'), $count).'<br /><br /></p>';
			return true;
		}// End if(is_array($users)

		echo __('No Users to Import!');
		return false;

	}// End function user2wp()

	function posts2wp($posts='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$dcposts2wpposts = array();
		$cats = array();

		// Do the Magic
		if(is_array($posts))
		{
			echo '<p>'.__('Importing Posts...').'<br /><br /></p>';
			foreach($posts as $post)
			{
				$count++;
				extract($post);

				// Set DotClear-to-WordPress status translation
				$stattrans = array(0 => 'draft', 1 => 'publish');
				$comment_status_map = array (0 => 'closed', 1 => 'open');

				//Can we do this more efficiently?
				$uinfo = ( get_userdatabylogin( $user_id ) ) ? get_userdatabylogin( $user_id ) : 1;
				$authorid = ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo ;

				$Title = $wpdb->escape(csc ($post_titre));
				$post_content = textconv ($post_content);
				$post_excerpt = "";
				if ($post_chapo != "") {
					$post_excerpt = textconv ($post_chapo);
					$post_content = $post_excerpt ."\n<!--more-->\n".$post_content;
				}
				$post_excerpt = $wpdb->escape ($post_excerpt);
				$post_content = $wpdb->escape ($post_content);
				$post_status = $stattrans[$post_pub];

				// Import Post data into WordPress

				if($pinfo = post_exists($Title,$post_content))
				{
					$ret_id = wp_insert_post(array(
							'ID'			=> $pinfo,
							'post_author'		=> $authorid,
							'post_date'		=> $post_dt,
							'post_date_gmt'		=> $post_dt,
							'post_modified'		=> $post_upddt,
							'post_modified_gmt'	=> $post_upddt,
							'post_title'		=> $Title,
							'post_content'		=> $post_content,
							'post_excerpt'		=> $post_excerpt,
							'post_status'		=> $post_status,
							'post_name'		=> $post_titre_url,
							'comment_status'	=> $comment_status_map[$post_open_comment],
							'ping_status'		=> $comment_status_map[$post_open_tb],
							'comment_count'		=> $post_nb_comment + $post_nb_trackback)
							);
					if ( is_wp_error( $ret_id ) )
						return $ret_id;
				}
				else
				{
					$ret_id = wp_insert_post(array(
							'post_author'		=> $authorid,
							'post_date'		=> $post_dt,
							'post_date_gmt'		=> $post_dt,
							'post_modified'		=> $post_modified_gmt,
							'post_modified_gmt'	=> $post_modified_gmt,
							'post_title'		=> $Title,
							'post_content'		=> $post_content,
							'post_excerpt'		=> $post_excerpt,
							'post_status'		=> $post_status,
							'post_name'		=> $post_titre_url,
							'comment_status'	=> $comment_status_map[$post_open_comment],
							'ping_status'		=> $comment_status_map[$post_open_tb],
							'comment_count'		=> $post_nb_comment + $post_nb_trackback)
							);
					if ( is_wp_error( $ret_id ) )
						return $ret_id;
				}
				$dcposts2wpposts[$post_id] = $ret_id;

				// Make Post-to-Category associations
				$cats = array();
				$category1 = get_category_by_slug($post_cat_name);
				$category1 = $category1->term_id;

				if($cat1 = $category1) { $cats[1] = $cat1; }

				if(!empty($cats)) { wp_set_post_categories($ret_id, $cats); }
			}
		}
		// Store ID translation for later use
		add_option('dcposts2wpposts',$dcposts2wpposts);

		echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
		return true;
	}

	function comments2wp($comments='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$dccm2wpcm = array();
		$postarr = get_option('dcposts2wpposts');

		// Magic Mojo
		if(is_array($comments))
		{
			echo '<p>'.__('Importing Comments...').'<br /><br /></p>';
			foreach($comments as $comment)
			{
				$count++;
				extract($comment);

				// WordPressify Data
				$comment_ID = (int) ltrim($comment_id, '0');
				$comment_post_ID = (int) $postarr[$post_id];
				$comment_approved = "$comment_pub";
				$name = $wpdb->escape(csc ($comment_auteur));
				$email = $wpdb->escape($comment_email);
				$web = "http://".$wpdb->escape($comment_site);
				$message = $wpdb->escape(textconv ($comment_content));

				if($cinfo = comment_exists($name, $comment_dt))
				{
					// Update comments
					$ret_id = wp_update_comment(array(
							'comment_ID'		=> $cinfo,
							'comment_post_ID'	=> $comment_post_ID,
							'comment_author'	=> $name,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'	=> $comment_ip,
							'comment_date'		=> $comment_dt,
							'comment_date_gmt'	=> $comment_dt,
							'comment_content'	=> $message,
							'comment_approved'	=> $comment_approved)
							);
				}
				else
				{
					// Insert comments
					$ret_id = wp_insert_comment(array(
							'comment_post_ID'	=> $comment_post_ID,
							'comment_author'	=> $name,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'	=> $comment_ip,
							'comment_date'		=> $comment_dt,
							'comment_date_gmt'	=> $comment_dt,
							'comment_content'	=> $message,
							'comment_approved'	=> $comment_approved)
							);
				}
				$dccm2wpcm[$comment_ID] = $ret_id;
			}
			// Store Comment ID translation for future use
			add_option('dccm2wpcm', $dccm2wpcm);

			// Associate newly formed categories with posts
			get_comment_count($ret_id);


			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Comments to Import!');
		return false;
	}

	function links2wp($links='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;

		// Deal with the links
		if(is_array($links))
		{
			echo '<p>'.__('Importing Links...').'<br /><br /></p>';
			foreach($links as $link)
			{
				$count++;
				extract($link);

				if ($title != "") {
					if ($cinfo = is_term(csc ($title), 'link_category')) {
						$category = $cinfo['term_id'];
					} else {
						$category = wp_insert_term($wpdb->escape (csc ($title)), 'link_category');
						$category = $category['term_id'];
					}
				} else {
					$linkname = $wpdb->escape(csc ($label));
					$description = $wpdb->escape(csc ($title));

					if($linfo = link_exists($linkname)) {
						$ret_id = wp_insert_link(array(
									'link_id'		=> $linfo,
									'link_url'		=> $href,
									'link_name'		=> $linkname,
									'link_category'		=> $category,
									'link_description'	=> $description)
									);
					} else {
						$ret_id = wp_insert_link(array(
									'link_url'		=> $url,
									'link_name'		=> $linkname,
									'link_category'		=> $category,
									'link_description'	=> $description)
									);
					}
					$dclinks2wplinks[$link_id] = $ret_id;
				}
			}
			add_option('dclinks2wplinks',$dclinks2wplinks);
			echo '<p>';
			printf(__ngettext('Done! <strong>%s</strong> link or link category imported.', 'Done! <strong>%s</strong> links or link categories imported.', $count), $count);
			echo '<br /><br /></p>';
			return true;
		}
		echo __('No Links to Import!');
		return false;
	}

	function import_categories()
	{
		// Category Import
		$cats = $this->get_dc_cats();
		$this->cat2wp($cats);
		add_option('dc_cats', $cats);



		echo '<form action="admin.php?import=dotclear&amp;step=2" method="post">';
		wp_nonce_field('import-dotclear');
		printf('<input type="submit" name="submit" value="%s" />', attribute_escape(__('Import Users')));
		echo '</form>';

	}

	function import_users()
	{
		// User Import
		$users = $this->get_dc_users();
		$this->users2wp($users);

		echo '<form action="admin.php?import=dotclear&amp;step=3" method="post">';
		wp_nonce_field('import-dotclear');
		printf('<input type="submit" name="submit" value="%s" />', attribute_escape(__('Import Posts')));
		echo '</form>';
	}

	function import_posts()
	{
		// Post Import
		$posts = $this->get_dc_posts();
		$result = $this->posts2wp($posts);
		if ( is_wp_error( $result ) )
			return $result;

		echo '<form action="admin.php?import=dotclear&amp;step=4" method="post">';
		wp_nonce_field('import-dotclear');
		printf('<input type="submit" name="submit" value="%s" />', attribute_escape(__('Import Comments')));
		echo '</form>';
	}

	function import_comments()
	{
		// Comment Import
		$comments = $this->get_dc_comments();
		$this->comments2wp($comments);

		echo '<form action="admin.php?import=dotclear&amp;step=5" method="post">';
		wp_nonce_field('import-dotclear');
		printf('<input type="submit" name="submit" value="%s" />', attribute_escape(__('Import Links')));
		echo '</form>';
	}

	function import_links()
	{
		//Link Import
		$links = $this->get_dc_links();
		$this->links2wp($links);
		add_option('dc_links', $links);

		echo '<form action="admin.php?import=dotclear&amp;step=6" method="post">';
		wp_nonce_field('import-dotclear');
		printf('<input type="submit" name="submit" value="%s" />', attribute_escape(__('Finish')));
		echo '</form>';
	}

	function cleanup_dcimport()
	{
		delete_option('dcdbprefix');
		delete_option('dc_cats');
		delete_option('dcid2wpid');
		delete_option('dccat2wpcat');
		delete_option('dcposts2wpposts');
		delete_option('dccm2wpcm');
		delete_option('dclinks2wplinks');
		delete_option('dcuser');
		delete_option('dcpass');
		delete_option('dcname');
		delete_option('dchost');
		delete_option('dccharset');
		do_action('import_done', 'dotclear');
		$this->tips();
	}

	function tips()
	{
		echo '<p>'.__('Welcome to WordPress.  We hope (and expect!) that you will find this platform incredibly rewarding!  As a new WordPress user coming from DotClear, there are some things that we would like to point out.  Hopefully, they will help your transition go as smoothly as possible.').'</p>';
		echo '<h3>'.__('Users').'</h3>';
		echo '<p>'.sprintf(__('You have already setup WordPress and have been assigned an administrative login and password.  Forget it.  You didn\'t have that login in DotClear, why should you have it here?  Instead we have taken care to import all of your users into our system.  Unfortunately there is one downside.  Because both WordPress and DotClear uses a strong encryption hash with passwords, it is impossible to decrypt it and we are forced to assign temporary passwords to all your users.  <strong>Every user has the same username, but their passwords are reset to password123.</strong>  So <a href="%1$s">Login</a> and change it.'), '/wp-login.php').'</p>';
		echo '<h3>'.__('Preserving Authors').'</h3>';
		echo '<p>'.__('Secondly, we have attempted to preserve post authors.  If you are the only author or contributor to your blog, then you are safe.  In most cases, we are successful in this preservation endeavor.  However, if we cannot ascertain the name of the writer due to discrepancies between database tables, we assign it to you, the administrative user.').'</p>';
		echo '<h3>'.__('Textile').'</h3>';
		echo '<p>'.__('Also, since you\'re coming from DotClear, you probably have been using Textile to format your comments and posts.  If this is the case, we recommend downloading and installing <a href="http://www.huddledmasses.org/category/development/wordpress/textile/">Textile for WordPress</a>.  Trust me... You\'ll want it.').'</p>';
		echo '<h3>'.__('WordPress Resources').'</h3>';
		echo '<p>'.__('Finally, there are numerous WordPress resources around the internet.  Some of them are:').'</p>';
		echo '<ul>';
		echo '<li>'.__('<a href="http://www.wordpress.org">The official WordPress site</a>').'</li>';
		echo '<li>'.__('<a href="http://wordpress.org/support/">The WordPress support forums</a>').'</li>';
		echo '<li>'.__('<a href="http://codex.wordpress.org">The Codex (In other words, the WordPress Bible)</a>').'</li>';
		echo '</ul>';
		echo '<p>'.sprintf(__('That\'s it! What are you waiting for? Go <a href="%1$s">login</a>!'), '../wp-login.php').'</p>';
	}

	function db_form()
	{
		echo '<table class="form-table">';
		printf('<tr><th><label for="dbuser">%s</label></th><td><input type="text" name="dbuser" id="dbuser" /></td></tr>', __('DotClear Database User:'));
		printf('<tr><th><label for="dbpass">%s</label></th><td><input type="password" name="dbpass" id="dbpass" /></td></tr>', __('DotClear Database Password:'));
		printf('<tr><th><label for="dbname">%s</label></th><td><input type="text" name="dbname" id="dbname" /></td></tr>', __('DotClear Database Name:'));
		printf('<tr><th><label for="dbhost">%s</label></th><td><input type="text" name="dbhost" nameid="dbhost" value="localhost" /></td></tr>', __('DotClear Database Host:'));
		printf('<tr><th><label for="dbprefix">%s</label></th><td><input type="text" name="dbprefix" id="dbprefix" value="dc_"/></td></tr>', __('DotClear Table prefix:'));
		printf('<tr><th><label for="dccharset">%s</label></th><td><input type="text" name="dccharset" id="dccharset" value="ISO-8859-15"/></td></tr>', __('Originating character set:'));
		echo '</table>';
	}

	function dispatch()
	{

		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
		$this->header();

		if ( $step > 0 )
		{
			check_admin_referer('import-dotclear');

			if($_POST['dbuser'])
			{
				if(get_option('dcuser'))
					delete_option('dcuser');
				add_option('dcuser', sanitize_user($_POST['dbuser'], true));
			}
			if($_POST['dbpass'])
			{
				if(get_option('dcpass'))
					delete_option('dcpass');
				add_option('dcpass', sanitize_user($_POST['dbpass'], true));
			}

			if($_POST['dbname'])
			{
				if(get_option('dcname'))
					delete_option('dcname');
				add_option('dcname', sanitize_user($_POST['dbname'], true));
			}
			if($_POST['dbhost'])
			{
				if(get_option('dchost'))
					delete_option('dchost');
				add_option('dchost', sanitize_user($_POST['dbhost'], true));
			}
			if($_POST['dccharset'])
			{
				if(get_option('dccharset'))
					delete_option('dccharset');
				add_option('dccharset', sanitize_user($_POST['dccharset'], true));
			}
			if($_POST['dbprefix'])
			{
				if(get_option('dcdbprefix'))
					delete_option('dcdbprefix');
				add_option('dcdbprefix', sanitize_user($_POST['dbprefix'], true));
			}


		}

		switch ($step)
		{
			default:
			case 0 :
				$this->greet();
				break;
			case 1 :
				$this->import_categories();
				break;
			case 2 :
				$this->import_users();
				break;
			case 3 :
				$result = $this->import_posts();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
			case 4 :
				$this->import_comments();
				break;
			case 5 :
				$this->import_links();
				break;
			case 6 :
				$this->cleanup_dcimport();
				break;
		}

		$this->footer();
	}

	function Dotclear_Import()
	{
		// Nothing.
	}
}

$dc_import = new Dotclear_Import();

register_importer('dotclear', __('DotClear'), __('Import categories, users, posts, comments, and links from a DotClear blog.'), array ($dc_import, 'dispatch'));

?>
