<?php

/*
 * The Big Query.
 */

function get_query_var($var) {
	global $wp_query;

	return $wp_query->get($var);
}

function set_query_var($var, $value) {
	global $wp_query;

	return $wp_query->set($var, $value);
}

function &query_posts($query) {
	unset($GLOBALS['wp_query']);
	$GLOBALS['wp_query'] =& new WP_Query();
	return $GLOBALS['wp_query']->query($query);
}

function wp_reset_query() {
	unset($GLOBALS['wp_query']);
	$GLOBALS['wp_query'] =& $GLOBALS['wp_the_query'];
}

/*
 * Query type checks.
 */

function is_admin () {
	if ( defined('WP_ADMIN') ) 
		return WP_ADMIN;
	return false;
}

function is_archive () {
	global $wp_query;

	return $wp_query->is_archive;
}

function is_attachment () {
	global $wp_query;

	return $wp_query->is_attachment;
}

function is_author ($author = '') {
	global $wp_query;

	if ( !$wp_query->is_author )
		return false;

	if ( empty($author) )
		return true;

	$author_obj = $wp_query->get_queried_object();

	if ( $author == $author_obj->ID )
		return true;
	elseif ( $author == $author_obj->nickname )
		return true;
	elseif ( $author == $author_obj->user_nicename )
		return true;

	return false;
}

function is_category ($category = '') {
	global $wp_query;

	if ( !$wp_query->is_category )
		return false;

	if ( empty($category) )
		return true;

	$cat_obj = $wp_query->get_queried_object();

	if ( $category == $cat_obj->term_id )
		return true;
	else if ( $category == $cat_obj->name )
		return true;
	elseif ( $category == $cat_obj->slug )
		return true;

	return false;
}

function is_tag( $slug = '' ) {
	global $wp_query;
	if ( !$wp_query->is_tag )
		return false;

	if ( empty( $slug ) )
		return true;

	$tag_obj = $wp_query->get_queried_object();
	if ( $slug == $tag_obj->slug )
		return true;
	return false;
}

function is_comments_popup () {
	global $wp_query;

	return $wp_query->is_comments_popup;
}

function is_date () {
	global $wp_query;

	return $wp_query->is_date;
}

function is_day () {
	global $wp_query;

	return $wp_query->is_day;
}

function is_feed () {
	global $wp_query;

	return $wp_query->is_feed;
}

function is_home () {
	global $wp_query;

	return $wp_query->is_home;
}

function is_month () {
	global $wp_query;

	return $wp_query->is_month;
}

function is_page ($page = '') {
	global $wp_query;

	if ( !$wp_query->is_page )
		return false;

	if ( empty($page) )
		return true;

	$page_obj = $wp_query->get_queried_object();

	if ( $page == $page_obj->ID )
		return true;
	elseif ( $page == $page_obj->post_title )
		return true;
	else if ( $page == $page_obj->post_name )
		return true;

	return false;
}

function is_paged () {
	global $wp_query;

	return $wp_query->is_paged;
}

function is_plugin_page() {
	global $plugin_page;

	if ( isset($plugin_page) )
		return true;

	return false;
}

function is_preview() {
	global $wp_query;

	return $wp_query->is_preview;
}

function is_robots() {
	global $wp_query;

	return $wp_query->is_robots;
}

function is_search () {
	global $wp_query;

	return $wp_query->is_search;
}

function is_single ($post = '') {
	global $wp_query;

	if ( !$wp_query->is_single )
		return false;

	if ( empty( $post) )
		return true;

	$post_obj = $wp_query->get_queried_object();

	if ( $post == $post_obj->ID )
		return true;
	elseif ( $post == $post_obj->post_title )
		return true;
	elseif ( $post == $post_obj->post_name )
		return true;

	return false;
}

function is_singular() {
	global $wp_query;

	return $wp_query->is_singular;
}

function is_time () {
	global $wp_query;

	return $wp_query->is_time;
}

function is_trackback () {
	global $wp_query;

	return $wp_query->is_trackback;
}

function is_year () {
	global $wp_query;

	return $wp_query->is_year;
}

function is_404 () {
	global $wp_query;

	return $wp_query->is_404;
}

/*
 * The Loop.  Post loop control.
 */

function have_posts() {
	global $wp_query;

	return $wp_query->have_posts();
}

function in_the_loop() {
	global $wp_query;

	return $wp_query->in_the_loop;
}

function rewind_posts() {
	global $wp_query;

	return $wp_query->rewind_posts();
}

function the_post() {
	global $wp_query;

	$wp_query->the_post();
}

/*
 * Comments loop.
 */

function have_comments() {
	global $wp_query;
	return $wp_query->have_comments();
}

function the_comment() {
	global $wp_query;
	return $wp_query->the_comment();
}

/*
 * WP_Query
 */

class WP_Query {
	var $query;
	var $query_vars = array();
	var $queried_object;
	var $queried_object_id;
	var $request;

	var $posts;
	var $post_count = 0;
	var $current_post = -1;
	var $in_the_loop = false;
	var $post;

	var $comments;
	var $comment_count = 0;
	var $current_comment = -1;
	var $comment;

	var $found_posts = 0;
	var $max_num_pages = 0;

	var $is_single = false;
	var $is_preview = false;
	var $is_page = false;
	var $is_archive = false;
	var $is_date = false;
	var $is_year = false;
	var $is_month = false;
	var $is_day = false;
	var $is_time = false;
	var $is_author = false;
	var $is_category = false;
	var $is_tag = false;
	var $is_search = false;
	var $is_feed = false;
	var $is_comment_feed = false;
	var $is_trackback = false;
	var $is_home = false;
	var $is_404 = false;
	var $is_comments_popup = false;
	var $is_admin = false;
	var $is_attachment = false;
	var $is_singular = false;
	var $is_robots = false;
	var $is_posts_page = false;

	function init_query_flags() {
		$this->is_single = false;
		$this->is_page = false;
		$this->is_archive = false;
		$this->is_date = false;
		$this->is_year = false;
		$this->is_month = false;
		$this->is_day = false;
		$this->is_time = false;
		$this->is_author = false;
		$this->is_category = false;
		$this->is_tag = false;
		$this->is_search = false;
		$this->is_feed = false;
		$this->is_comment_feed = false;
		$this->is_trackback = false;
		$this->is_home = false;
		$this->is_404 = false;
		$this->is_paged = false;
		$this->is_admin = false;
		$this->is_attachment = false;
		$this->is_singular = false;
		$this->is_robots = false;
		$this->is_posts_page = false;
	}

	function init () {
		unset($this->posts);
		unset($this->query);
		$this->query_vars = array();
		unset($this->queried_object);
		unset($this->queried_object_id);
		$this->post_count = 0;
		$this->current_post = -1;
		$this->in_the_loop = false;

		$this->init_query_flags();
	}

	// Reparse the query vars.
	function parse_query_vars() {
		$this->parse_query('');
	}

	function fill_query_vars($array) {
		$keys = array(
			'error'
			, 'm'
			, 'p'
			, 'subpost'
			, 'subpost_id'
			, 'attachment'
			, 'attachment_id'
			, 'name'
			, 'hour'
			, 'static'
			, 'pagename'
			, 'page_id'
			, 'second'
			, 'minute'
			, 'hour'
			, 'day'
			, 'monthnum'
			, 'year'
			, 'w'
			, 'category_name'
			, 'tag'
			, 'tag_id'
			, 'author_name'
			, 'feed'
			, 'tb'
			, 'paged'
			, 'comments_popup'
			, 'preview'
		);

		foreach ($keys as $key) {
			if ( !isset($array[$key]))
				$array[$key] = '';
		}

		$array_keys = array('category__in', 'category__not_in', 'category__and',
			'tag__in', 'tag__not_in', 'tag__and', 'tag_slug__in', 'tag_slug__and');

		foreach ( $array_keys as $key ) {
			if ( !isset($array[$key]))
				$array[$key] = array();
		}
		return $array;
	}

	// Parse a query string and set query type booleans.
	function parse_query ($query) {
		if ( !empty($query) || !isset($this->query) ) {
			$this->init();
			if ( is_array($query) )
				$this->query_vars = $query;
			else
				parse_str($query, $this->query_vars);
			$this->query = $query;
		}

		$this->query_vars = $this->fill_query_vars($this->query_vars);
		$qv = &$this->query_vars;

		if ( ! empty($qv['robots']) )
			$this->is_robots = true;

		$qv['p'] =  (int) $qv['p'];
		$qv['page_id'] =  (int) $qv['page_id'];
		$qv['year'] = (int) $qv['year'];
		$qv['monthnum'] = (int) $qv['monthnum'];
		$qv['day'] = (int) $qv['day'];
		$qv['w'] = (int) $qv['w'];
		$qv['m'] =  (int) $qv['m'];
		if ( '' !== $qv['hour'] ) $qv['hour'] = (int) $qv['hour'];
		if ( '' !== $qv['minute'] ) $qv['minute'] = (int) $qv['minute'];
		if ( '' !== $qv['second'] ) $qv['second'] = (int) $qv['second'];

		// Compat.  Map subpost to attachment.
		if ( '' != $qv['subpost'] )
			$qv['attachment'] = $qv['subpost'];
		if ( '' != $qv['subpost_id'] )
			$qv['attachment_id'] = $qv['subpost_id'];

		$qv['attachment_id'] = (int) $qv['attachment_id'];

		if ( ('' != $qv['attachment']) || !empty($qv['attachment_id']) ) {
			$this->is_single = true;
			$this->is_attachment = true;
		} elseif ( '' != $qv['name'] ) {
			$this->is_single = true;
		} elseif ( $qv['p'] ) {
			$this->is_single = true;
		} elseif ( ('' !== $qv['hour']) && ('' !== $qv['minute']) &&('' !== $qv['second']) && ('' != $qv['year']) && ('' != $qv['monthnum']) && ('' != $qv['day']) ) {
			// If year, month, day, hour, minute, and second are set, a single
			// post is being queried.
			$this->is_single = true;
		} elseif ( '' != $qv['static'] || '' != $qv['pagename'] || !empty($qv['page_id']) ) {
			$this->is_page = true;
			$this->is_single = false;
		} elseif ( !empty($qv['s']) ) {
			$this->is_search = true;
		} else {
		// Look for archive queries.  Dates, categories, authors.

			if ( '' !== $qv['second'] ) {
				$this->is_time = true;
				$this->is_date = true;
			}

			if ( '' !== $qv['minute'] ) {
				$this->is_time = true;
				$this->is_date = true;
			}

			if ( '' !== $qv['hour'] ) {
				$this->is_time = true;
				$this->is_date = true;
			}

			if ( $qv['day'] ) {
				if (! $this->is_date) {
					$this->is_day = true;
					$this->is_date = true;
				}
			}

			if ( $qv['monthnum'] ) {
				if (! $this->is_date) {
					$this->is_month = true;
					$this->is_date = true;
				}
			}

			if ( $qv['year'] ) {
				if (! $this->is_date) {
					$this->is_year = true;
					$this->is_date = true;
				}
			}

			if ( $qv['m'] ) {
				$this->is_date = true;
				if (strlen($qv['m']) > 9) {
					$this->is_time = true;
				} else if (strlen($qv['m']) > 7) {
					$this->is_day = true;
				} else if (strlen($qv['m']) > 5) {
					$this->is_month = true;
				} else {
					$this->is_year = true;
				}
			}

			if ('' != $qv['w']) {
				$this->is_date = true;
			}

			if ( empty($qv['cat']) || ($qv['cat'] == '0') ) {
				$this->is_category = false;
			} else {
				if (strpos($qv['cat'], '-') !== false) {
					$this->is_category = false;
				} else {
					$this->is_category = true;
				}
			}

			if ( '' != $qv['category_name'] ) {
				$this->is_category = true;
			}

			if ( !is_array($qv['category__in']) || empty($qv['category__in']) ) {
				$qv['category__in'] = array();
			} else {
				$qv['category__in'] = array_map('intval', $qv['category__in']);
				$this->is_category = true;
			}

			if ( !is_array($qv['category__not_in']) || empty($qv['category__not_in']) ) {
				$qv['category__not_in'] = array();
			} else {
				$qv['category__not_in'] = array_map('intval', $qv['category__not_in']);
			}

			if ( !is_array($qv['category__and']) || empty($qv['category__and']) ) {
				$qv['category__and'] = array();
			} else {
				$qv['category__and'] = array_map('intval', $qv['category__and']);
				$this->is_category = true;
			}

			if (  '' != $qv['tag'] )
				$this->is_tag = true;

			$qv['tag_id'] = (int) $qv['tag_id'];
			if (  !empty($qv['tag_id']) )
				$this->is_tag = true;

			if ( !is_array($qv['tag__in']) || empty($qv['tag__in']) ) {
				$qv['tag__in'] = array();
			} else {
				$qv['tag__in'] = array_map('intval', $qv['tag__in']);
				$this->is_tag = true;
			}

			if ( !is_array($qv['tag__not_in']) || empty($qv['tag__not_in']) ) {
				$qv['tag__not_in'] = array();
			} else {
				$qv['tag__not_in'] = array_map('intval', $qv['tag__not_in']);
			}

			if ( !is_array($qv['tag__and']) || empty($qv['tag__and']) ) {
				$qv['tag__and'] = array();
			} else {
				$qv['tag__and'] = array_map('intval', $qv['tag__and']);
				$this->is_category = true;
			}

			if ( !is_array($qv['tag_slug__in']) || empty($qv['tag_slug__in']) ) {
				$qv['tag_slug__in'] = array();
			} else {
				$qv['tag_slug__in'] = array_map('sanitize_title', $qv['tag_slug__in']);
				$this->is_tag = true;
			}

			if ( !is_array($qv['tag_slug__and']) || empty($qv['tag_slug__and']) ) {
				$qv['tag_slug__and'] = array();
			} else {
				$qv['tag_slug__and'] = array_map('sanitize_title', $qv['tag_slug__and']);
				$this->is_tag = true;
			}

			if ( empty($qv['author']) || ($qv['author'] == '0') ) {
				$this->is_author = false;
			} else {
				$this->is_author = true;
			}

			if ( '' != $qv['author_name'] ) {
				$this->is_author = true;
			}

			if ( ($this->is_date || $this->is_author || $this->is_category || $this->is_tag ) )
				$this->is_archive = true;
		}

		if ( '' != $qv['feed'] )
			$this->is_feed = true;

		if ( '' != $qv['tb'] )
			$this->is_trackback = true;

		if ( '' != $qv['paged'] )
			$this->is_paged = true;

		if ( '' != $qv['comments_popup'] )
			$this->is_comments_popup = true;

		// if we're previewing inside the write screen
		if ('' != $qv['preview'])
			$this->is_preview = true;

		if ( is_admin() )
			$this->is_admin = true;

		if ( false !== strpos($qv['feed'], 'comments-') ) {
			$qv['feed'] = str_replace('comments-', '', $qv['feed']);
			$qv['withcomments'] = 1;
		}

		$this->is_singular = $this->is_single || $this->is_page || $this->is_attachment;

		if ( $this->is_feed && ( !empty($qv['withcomments']) || ( empty($qv['withoutcomments']) && $this->is_singular ) ) )
			$this->is_comment_feed = true;

		if ( !( $this->is_singular || $this->is_archive || $this->is_search || $this->is_feed || $this->is_trackback || $this->is_404 || $this->is_admin || $this->is_comments_popup ) )
			$this->is_home = true;

		// Correct is_* for page_on_front and page_for_posts
		if ( $this->is_home && ( empty($this->query) || $qv['preview'] == 'true' ) && 'page' == get_option('show_on_front') && get_option('page_on_front') ) {
			$this->is_page = true;
			$this->is_home = false;
			$qv['page_id'] = get_option('page_on_front');
		}

		if ( '' != $qv['pagename'] ) {
			$this->queried_object =& get_page_by_path($qv['pagename']);
			if ( !empty($this->queried_object) )
				$this->queried_object_id = (int) $this->queried_object->ID;
			else
				unset($this->queried_object);

			if  ( 'page' == get_option('show_on_front') && isset($this->queried_object_id) && $this->queried_object_id == get_option('page_for_posts') ) {
				$this->is_page = false;
				$this->is_home = true;
				$this->is_posts_page = true;
			}
		}

		if ( $qv['page_id'] ) {
			if  ( 'page' == get_option('show_on_front') && $qv['page_id'] == get_option('page_for_posts') ) {
				$this->is_page = false;
				$this->is_home = true;
				$this->is_posts_page = true;
			}
		}

		if ( !empty($qv['post_type']) )
			$qv['post_type'] = sanitize_user($qv['post_type'], true);

		if ( !empty($qv['post_status']) )
			$qv['post_status'] = sanitize_user($qv['post_status'], true);

		if ( $this->is_posts_page && !$qv['withcomments'] )
			$this->is_comment_feed = false;

		$this->is_singular = $this->is_single || $this->is_page || $this->is_attachment;
		// Done correcting is_* for page_on_front and page_for_posts

		if ('404' == $qv['error'])
			$this->set_404();

		if ( !empty($query) )
			do_action_ref_array('parse_query', array(&$this));
	}

	function set_404() {
		$is_feed = $this->is_feed;

		$this->init_query_flags();
		$this->is_404 = true;

		$this->is_feed = $is_feed;
	}

	function get($query_var) {
		if (isset($this->query_vars[$query_var])) {
			return $this->query_vars[$query_var];
		}

		return '';
	}

	function set($query_var, $value) {
		$this->query_vars[$query_var] = $value;
	}

	function &get_posts() {
		global $wpdb, $pagenow, $user_ID;

		do_action_ref_array('pre_get_posts', array(&$this));

		// Shorthand.
		$q = &$this->query_vars;

		$q = $this->fill_query_vars($q);

		// First let's clear some variables
		$distinct = '';
		$whichcat = '';
		$whichauthor = '';
		$whichpage = '';
		$result = '';
		$where = '';
		$limits = '';
		$join = '';
		$search = '';
		$groupby = '';

		if ( !isset($q['post_type']) )
			$q['post_type'] = 'post';
		$post_type = $q['post_type'];
		if ( !isset($q['posts_per_page']) || $q['posts_per_page'] == 0 )
			$q['posts_per_page'] = get_option('posts_per_page');
		if ( isset($q['showposts']) && $q['showposts'] ) {
			$q['showposts'] = (int) $q['showposts'];
			$q['posts_per_page'] = $q['showposts'];
		}
		if ( (isset($q['posts_per_archive_page']) && $q['posts_per_archive_page'] != 0) && ($this->is_archive || $this->is_search) )
			$q['posts_per_page'] = $q['posts_per_archive_page'];
		if ( !isset($q['nopaging']) ) {
			if ($q['posts_per_page'] == -1) {
				$q['nopaging'] = true;
			} else {
				$q['nopaging'] = false;
			}
		}
		if ( $this->is_feed ) {
			$q['posts_per_page'] = get_option('posts_per_rss');
			$q['nopaging'] = false;
		}
		$q['posts_per_page'] = (int) $q['posts_per_page'];
		if ( $q['posts_per_page'] < -1 )
			$q['posts_per_page'] = abs($q['posts_per_page']);
		else if ( $q['posts_per_page'] == 0 )
			$q['posts_per_page'] = 1;

		if ( $this->is_home && (empty($this->query) || $q['preview'] == 'true') && ( 'page' == get_option('show_on_front') ) && get_option('page_on_front') ) {
			$this->is_page = true;
			$this->is_home = false;
			$q['page_id'] = get_option('page_on_front');
		}

		if (isset($q['page'])) {
			$q['page'] = trim($q['page'], '/');
			$q['page'] = (int) $q['page'];
			$q['page'] = abs($q['page']);
		}

		$add_hours = intval(get_option('gmt_offset'));
		$add_minutes = intval(60 * (get_option('gmt_offset') - $add_hours));
		$wp_posts_post_date_field = "post_date"; // "DATE_ADD(post_date, INTERVAL '$add_hours:$add_minutes' HOUR_MINUTE)";

		// If a month is specified in the querystring, load that month
		if ( $q['m'] ) {
			$q['m'] = '' . preg_replace('|[^0-9]|', '', $q['m']);
			$where .= ' AND YEAR(post_date)=' . substr($q['m'], 0, 4);
			if (strlen($q['m'])>5)
				$where .= ' AND MONTH(post_date)=' . substr($q['m'], 4, 2);
			if (strlen($q['m'])>7)
				$where .= ' AND DAYOFMONTH(post_date)=' . substr($q['m'], 6, 2);
			if (strlen($q['m'])>9)
				$where .= ' AND HOUR(post_date)=' . substr($q['m'], 8, 2);
			if (strlen($q['m'])>11)
				$where .= ' AND MINUTE(post_date)=' . substr($q['m'], 10, 2);
			if (strlen($q['m'])>13)
				$where .= ' AND SECOND(post_date)=' . substr($q['m'], 12, 2);
		}

		if ( '' !== $q['hour'] )
			$where .= " AND HOUR(post_date)='" . $q['hour'] . "'";

		if ( '' !== $q['minute'] )
			$where .= " AND MINUTE(post_date)='" . $q['minute'] . "'";

		if ( '' !== $q['second'] )
			$where .= " AND SECOND(post_date)='" . $q['second'] . "'";

		if ( $q['year'] )
			$where .= " AND YEAR(post_date)='" . $q['year'] . "'";

		if ( $q['monthnum'] )
			$where .= " AND MONTH(post_date)='" . $q['monthnum'] . "'";

		if ( $q['day'] )
			$where .= " AND DAYOFMONTH(post_date)='" . $q['day'] . "'";

		if ('' != $q['name']) {
			$q['name'] = sanitize_title($q['name']);
			$where .= " AND post_name = '" . $q['name'] . "'";
		} else if ('' != $q['pagename']) {
			if ( isset($this->queried_object_id) )
				$reqpage = $this->queried_object_id;
			else {
				$reqpage = get_page_by_path($q['pagename']);
				if ( !empty($reqpage) )
					$reqpage = $reqpage->ID;
				else
					$reqpage = 0;
			}

			if  ( ('page' != get_option('show_on_front') ) || ( $reqpage != get_option('page_for_posts') ) ) {
				$q['pagename'] = str_replace('%2F', '/', urlencode(urldecode($q['pagename'])));
				$page_paths = '/' . trim($q['pagename'], '/');
				$q['pagename'] = sanitize_title(basename($page_paths));
				$q['name'] = $q['pagename'];
				$where .= " AND (ID = '$reqpage')";
			}
		} elseif ('' != $q['attachment']) {
			$q['attachment'] = str_replace('%2F', '/', urlencode(urldecode($q['attachment'])));
			$attach_paths = '/' . trim($q['attachment'], '/');
			$q['attachment'] = sanitize_title(basename($attach_paths));
			$q['name'] = $q['attachment'];
			$where .= " AND post_name = '" . $q['attachment'] . "'";
		}

		if ( $q['w'] )
			$where .= " AND WEEK(post_date, 1)='" . $q['w'] . "'";

		if ( intval($q['comments_popup']) )
			$q['p'] = intval($q['comments_popup']);

		// If an attachment is requested by number, let it supercede any post number.
		if ( $q['attachment_id'] )
			$q['p'] = $q['attachment_id'];

		// If a post number is specified, load that post
		if ( $q['p'] )
			$where = ' AND ID = ' . $q['p'];

		if ( $q['page_id'] ) {
			if  ( ('page' != get_option('show_on_front') ) || ( $q['page_id'] != get_option('page_for_posts') ) ) {
				$q['p'] = $q['page_id'];
				$where = ' AND ID = ' . $q['page_id'];
			}
		}

		// If a search pattern is specified, load the posts that match
		if ( !empty($q['s']) ) {
			// added slashes screw with quote grouping when done early, so done later
			$q['s'] = stripslashes($q['s']);
			if ($q['sentence']) {
				$q['search_terms'] = array($q['s']);
			}
			else {
				preg_match_all('/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $q[s], $matches);
				$q['search_terms'] = array_map(create_function('$a', 'return trim($a, "\\"\'\\n\\r ");'), $matches[0]);
			}
			$n = ($q['exact']) ? '' : '%';
			$searchand = '';
			foreach((array)$q['search_terms'] as $term) {
				$term = addslashes_gpc($term);
				$search .= "{$searchand}((post_title LIKE '{$n}{$term}{$n}') OR (post_content LIKE '{$n}{$term}{$n}'))";
				$searchand = ' AND ';
			}
			$term = addslashes_gpc($q['s']);
			if (!$q['sentence'] && count($q['search_terms']) > 1 && $q['search_terms'][0] != $q['s'] )
				$search .= " OR (post_title LIKE '{$n}{$term}{$n}') OR (post_content LIKE '{$n}{$term}{$n}')";

			if ( !empty($search) )
				$search = " AND ({$search}) ";
		}

		// Category stuff

		if ( empty($q['cat']) || ($q['cat'] == '0') ||
				// Bypass cat checks if fetching specific posts
				$this->is_singular ) {
			$whichcat = '';
		} else {
			$q['cat'] = ''.urldecode($q['cat']).'';
			$q['cat'] = addslashes_gpc($q['cat']);
			$cat_array = preg_split('/[,\s]+/', $q['cat']);
			foreach ( $cat_array as $cat ) {
				$cat = intval($cat);
				$in = ($cat > 0);
				$cat = abs($cat);
				if ( $in ) {
					$q['category__in'][] = $cat;
					$q['category__in'] = array_merge($q['category__in'], get_term_children($cat, 'category'));
				} else {
					$q['category__not_in'][] = $cat;
					$q['category__not_in'] = array_merge($q['category__not_in'], get_term_children($cat, 'category'));
				}
			}
		}

		if ( !empty($q['category__in']) || !empty($q['category__not_in']) || !empty($q['category__and']) ) {
			$groupby = "{$wpdb->posts}.ID";
		}

		if ( !empty($q['category__in']) ) {
			$join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
			$whichcat .= " AND $wpdb->term_taxonomy.taxonomy = 'category' ";
			$include_cats = "'" . implode("', '", $q['category__in']) . "'";
			$whichcat .= " AND $wpdb->term_taxonomy.term_id IN ($include_cats) ";
		}

		if ( !empty($q['category__not_in']) ) {
			$ids = get_objects_in_term($q['category__not_in'], 'category');
			if ( is_wp_error( $ids ) ) 
				return $ids;
			if ( is_array($ids) && count($ids > 0) ) {
				$out_posts = "'" . implode("', '", $ids) . "'";
				$whichcat .= " AND $wpdb->posts.ID NOT IN ($out_posts)";
			}
		}

		// Category stuff for nice URLs
		if ( '' != $q['category_name'] ) {
			$reqcat = get_category_by_path($q['category_name']);
			$q['category_name'] = str_replace('%2F', '/', urlencode(urldecode($q['category_name'])));
			$cat_paths = '/' . trim($q['category_name'], '/');
			$q['category_name'] = sanitize_title(basename($cat_paths));

			$cat_paths = '/' . trim(urldecode($q['category_name']), '/');
			$q['category_name'] = sanitize_title(basename($cat_paths));
			$cat_paths = explode('/', $cat_paths);
			$cat_path = '';
			foreach ( (array) $cat_paths as $pathdir )
				$cat_path .= ( $pathdir != '' ? '/' : '' ) . sanitize_title($pathdir);

			//if we don't match the entire hierarchy fallback on just matching the nicename
			if ( empty($reqcat) )
				$reqcat = get_category_by_path($q['category_name'], false);

			if ( !empty($reqcat) )
				$reqcat = $reqcat->term_id;
			else
				$reqcat = 0;

			$q['cat'] = $reqcat;

			$join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
			$whichcat = " AND $wpdb->term_taxonomy.taxonomy = 'category' ";
			$in_cats = array($q['cat']);
			$in_cats = array_merge($in_cats, get_term_children($q['cat'], 'category'));
			$in_cats = "'" . implode("', '", $in_cats) . "'";
			$whichcat .= "AND $wpdb->term_taxonomy.term_id IN ($in_cats)";
			$groupby = "{$wpdb->posts}.ID";
		}

		// Tags
		if ( '' != $q['tag'] ) {
			if ( strpos($q['tag'], ',') !== false ) {
				$tags = preg_split('/[,\s]+/', $q['tag']);
				foreach ( (array) $tags as $tag ) {
					$tag = sanitize_term_field('slug', $tag, 0, 'post_tag', 'db');
					$q['tag_slug__in'][] = $tag;
				}
			} else if ( preg_match('/[+\s]+/', $q['tag']) ) {
				$tags = preg_split('/[+\s]+/', $q['tag']);
				foreach ( (array) $tags as $tag ) {
					$tag = sanitize_term_field('slug', $tag, 0, 'post_tag', 'db');
					$q['tag_slug__and'][] = $tag;
				}
			} else {
				$q['tag'] = sanitize_term_field('slug', $q['tag'], 0, 'post_tag', 'db');
				$reqtag = is_term( $q['tag'], 'post_tag' );
				if ( !empty($reqtag) )
					$reqtag = $reqtag['term_id'];
				else
					$reqtag = 0;

				$q['tag_id'] = $reqtag;
				$q['tag__in'][] = $reqtag;
			}
		}

		if ( !empty($q['tag__in']) || !empty($q['tag__not_in']) || !empty($q['tag__and']) ||
			!empty($q['tag_slug__in']) || !empty($q['tag_slug__and']) ) {
			$groupby = "{$wpdb->posts}.ID";
		}

		if ( !empty($q['tag__in']) ) {
			$join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
			$whichcat .= " AND $wpdb->term_taxonomy.taxonomy = 'post_tag' ";
			$include_tags = "'" . implode("', '", $q['tag__in']) . "'";
			$whichcat .= " AND $wpdb->term_taxonomy.term_id IN ($include_tags) ";
			$reqtag = is_term( $q['tag__in'][0], 'post_tag' );
			if ( !empty($reqtag) )
				$q['tag_id'] = $reqtag['term_id'];
		}

		if ( !empty($q['tag_slug__in']) ) {
			$join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) INNER JOIN $wpdb->terms ON ($wpdb->term_taxonomy.term_id = $wpdb->terms.term_id) ";
			$whichcat .= " AND $wpdb->term_taxonomy.taxonomy = 'post_tag' ";
			$include_tags = "'" . implode("', '", $q['tag_slug__in']) . "'";
			$whichcat .= " AND $wpdb->terms.slug IN ($include_tags) ";
			$reqtag = is_term( $q['tag_slug__in'][0], 'post_tag' );
			if ( !empty($reqtag) )
				$q['tag_id'] = $reqtag['term_id'];
		}

		if ( !empty($q['tag__not_in']) ) {
			$ids = get_objects_in_term($q['tag__not_in'], 'post_tag');
			if ( is_array($ids) && count($ids > 0) ) {
				$out_posts = "'" . implode("', '", $ids) . "'";
				$whichcat .= " AND $wpdb->posts.ID NOT IN ($out_posts)";
			}
		}

		// Tag and slug intersections.
		$intersections = array('category__and' => 'category', 'tag__and' => 'post_tag', 'tag_slug__and' => 'post_tag');
		foreach ($intersections as $item => $taxonomy) {
			if ( empty($q[$item]) ) continue;

			if ( $item != 'category__and' ) {
				$reqtag = is_term( $q[$item][0], 'post_tag' );
				if ( !empty($reqtag) )
					$q['tag_id'] = $reqtag['term_id'];
			}

			$taxonomy_field = $item == 'tag_slug__and' ? 'slug' : 'term_id';

			$q[$item] = array_unique($q[$item]);
			$tsql = "SELECT p.ID FROM $wpdb->posts p INNER JOIN $wpdb->term_relationships tr ON (p.ID = tr.object_id) INNER JOIN $wpdb->term_taxonomy tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id) INNER JOIN $wpdb->terms t ON (tt.term_id = t.term_id)";
			$tsql .= " WHERE tt.taxonomy = '$taxonomy' AND t.$taxonomy_field IN ('" . implode("', '", $q[$item]) . "')";
			$tsql .= " GROUP BY p.ID HAVING count(p.ID) = " . count($q[$item]);

			$post_ids = $wpdb->get_col($tsql);

			if ( count($post_ids) )
				$whichcat .= " AND $wpdb->posts.ID IN (" . implode(', ', $post_ids) . ") ";
			else {
				$whichcat = " AND 0 = 1";
				break;
			}
		}

		// Author/user stuff

		if ( empty($q['author']) || ($q['author'] == '0') ) {
			$whichauthor='';
		} else {
			$q['author'] = ''.urldecode($q['author']).'';
			$q['author'] = addslashes_gpc($q['author']);
			if (strpos($q['author'], '-') !== false) {
				$eq = '!=';
				$andor = 'AND';
				$q['author'] = explode('-', $q['author']);
				$q['author'] = ''.intval($q['author'][1]);
			} else {
				$eq = '=';
				$andor = 'OR';
			}
			$author_array = preg_split('/[,\s]+/', $q['author']);
			$whichauthor .= ' AND (post_author '.$eq.' '.intval($author_array[0]);
			for ($i = 1; $i < (count($author_array)); $i = $i + 1) {
				$whichauthor .= ' '.$andor.' post_author '.$eq.' '.intval($author_array[$i]);
			}
			$whichauthor .= ')';
		}

		// Author stuff for nice URLs

		if ('' != $q['author_name']) {
			if (strpos($q['author_name'], '/') !== false) {
				$q['author_name'] = explode('/',$q['author_name']);
				if ($q['author_name'][count($q['author_name'])-1]) {
					$q['author_name'] = $q['author_name'][count($q['author_name'])-1];#no trailing slash
				} else {
					$q['author_name'] = $q['author_name'][count($q['author_name'])-2];#there was a trailling slash
				}
			}
			$q['author_name'] = sanitize_title($q['author_name']);
			$q['author'] = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_nicename='".$q['author_name']."'");
			$whichauthor .= ' AND (post_author = '.intval($q['author']).')';
		}

		$where .= $search.$whichcat.$whichauthor;

		if ( empty($q['order']) || ((strtoupper($q['order']) != 'ASC') && (strtoupper($q['order']) != 'DESC')) )
			$q['order'] = 'DESC';

		// Order by
		if ( empty($q['orderby']) ) {
			$q['orderby'] = 'post_date '.$q['order'];
		} else {
			// Used to filter values
			$allowed_keys = array('author', 'date', 'category', 'title', 'modified', 'menu_order');
			$q['orderby'] = urldecode($q['orderby']);
			$q['orderby'] = addslashes_gpc($q['orderby']);
			$orderby_array = explode(' ',$q['orderby']);
			if ( empty($orderby_array) )
				$orderby_array[] = $q['orderby'];
			$q['orderby'] = '';
			for ($i = 0; $i < count($orderby_array); $i++) {
				// Only allow certain values for safety
				$orderby = $orderby_array[$i];
				if ( 'menu_order' != $orderby )
					$orderby = 'post_' . $orderby;
				if ( in_array($orderby_array[$i], $allowed_keys) )
					$q['orderby'] .= (($i == 0) ? '' : ',') . "$orderby {$q['order']}";
			}
			if ( empty($q['orderby']) )
				$q['orderby'] = 'post_date '.$q['order'];
		}

		if ( $this->is_attachment ) {
			$where .= " AND post_type = 'attachment'";
		} elseif ($this->is_page) {
			$where .= " AND post_type = 'page'";
		} elseif ($this->is_single) {
			$where .= " AND post_type = 'post'";
		} else {
			$where .= " AND post_type = '$post_type'";
		}

		if ( isset($q['post_status']) && '' != $q['post_status'] ) {
			$q_status = explode(',', $q['post_status']);
			$r_status = array();
			if ( in_array( 'draft'  , $q_status ) )
				$r_status[] = "post_status = 'draft'";
			if ( in_array( 'pending', $q_status ) )
				$r_status[] = "post_status = 'pending'";
			if ( in_array( 'future' , $q_status ) )
				$r_status[] = "post_status = 'future'";
			if ( in_array( 'inherit' , $q_status ) )
				$r_status[] = "post_status = 'inherit'";
			if ( in_array( 'private', $q_status ) )
				$r_status[] = "post_status = 'private'";
			if ( in_array( 'publish', $q_status ) )
				$r_status[] = "post_status = 'publish'";
			if ( !empty($r_status) )
				$where .= " AND (" . join( ' OR ', $r_status ) . ")";
		} elseif ( !$this->is_singular ) {
			$where .= " AND (post_status = 'publish'";

			if ( is_admin() )
				$where .= " OR post_status = 'future' OR post_status = 'draft' OR post_status = 'pending'";

			if ( is_user_logged_in() ) {
				$where .= current_user_can( "read_private_{$post_type}s" ) ? " OR post_status = 'private'" : " OR post_author = $user_ID AND post_status = 'private'";
			}

			$where .= ')';
		}

		// Apply filters on where and join prior to paging so that any
		// manipulations to them are reflected in the paging by day queries.
		$where = apply_filters('posts_where', $where);
		$join = apply_filters('posts_join', $join);

		// Paging
		if ( empty($q['nopaging']) && !$this->is_singular ) {
			$page = abs(intval($q['paged']));
			if (empty($page)) {
				$page = 1;
			}

			if ( empty($q['offset']) ) {
				$pgstrt = '';
				$pgstrt = (intval($page) -1) * $q['posts_per_page'] . ', ';
				$limits = 'LIMIT '.$pgstrt.$q['posts_per_page'];
			} else { // we're ignoring $page and using 'offset'
				$q['offset'] = abs(intval($q['offset']));
				$pgstrt = $q['offset'] . ', ';
				$limits = 'LIMIT ' . $pgstrt . $q['posts_per_page'];
			}
		}

		// Comments feeds
		if ( $this->is_comment_feed && ( $this->is_archive || $this->is_search || !$this->is_singular ) ) {
			if ( $this->is_archive || $this->is_search ) {
				$cjoin = "LEFT JOIN $wpdb->posts ON ($wpdb->comments.comment_post_ID = $wpdb->posts.ID) $join ";
				$cwhere = "WHERE comment_approved = '1' $where";
				$cgroupby = "GROUP BY $wpdb->comments.comment_id";
			} else { // Other non singular e.g. front
				$cjoin = "LEFT JOIN $wpdb->posts ON ( $wpdb->comments.comment_post_ID = $wpdb->posts.ID )";
				$cwhere = "WHERE post_status = 'publish' AND comment_approved = '1'";
				$cgroupby = '';
			}

			$cjoin = apply_filters('comment_feed_join', $cjoin);
			$cwhere = apply_filters('comment_feed_where', $cwhere);
			$cgroupby = apply_filters('comment_feed_groupby', $cgroupby);

			$this->comments = (array) $wpdb->get_results("SELECT $distinct $wpdb->comments.* FROM $wpdb->comments $cjoin $cwhere $cgroupby ORDER BY comment_date_gmt DESC LIMIT " . get_option('posts_per_rss'));
			$this->comment_count = count($this->comments);

			$post_ids = array();

			foreach ($this->comments as $comment)
				$post_ids[] = (int) $comment->comment_post_ID;

			$post_ids = join(',', $post_ids);
			$join = '';
			if ( $post_ids )
				$where = "AND $wpdb->posts.ID IN ($post_ids) ";
			else
				$where = "AND 0";
		}

		// Apply post-paging filters on where and join.  Only plugins that
		// manipulate paging queries should use these hooks.

		// Announce current selection parameters.  For use by caching plugins.
		do_action( 'posts_selection', $where . $groupby . $q['orderby'] . $limits . $join );

		$where = apply_filters('posts_where_paged', $where);
		$groupby = apply_filters('posts_groupby', $groupby);
		if ( ! empty($groupby) )
			$groupby = 'GROUP BY ' . $groupby;
		$join = apply_filters('posts_join_paged', $join);
		$orderby = apply_filters('posts_orderby', $q['orderby']);
		if ( !empty( $orderby ) )
			$orderby = 'ORDER BY ' . $orderby;
		$distinct = apply_filters('posts_distinct', $distinct);
		$fields = apply_filters('posts_fields', "$wpdb->posts.*");
		$limits = apply_filters( 'post_limits', $limits );
		$found_rows = '';
		if ( !empty($limits) )
			$found_rows = 'SQL_CALC_FOUND_ROWS';

		$request = " SELECT $found_rows $distinct $fields FROM $wpdb->posts $join WHERE 1=1 $where $groupby $orderby $limits";
		$this->request = apply_filters('posts_request', $request);

		$this->posts = $wpdb->get_results($this->request);
		// Raw results filter.  Prior to status checks.
		$this->posts = apply_filters('posts_results', $this->posts);

		if ( $this->is_comment_feed && $this->is_singular ) {
			$cjoin = apply_filters('comment_feed_join', '');
			$cwhere = apply_filters('comment_feed_where', "WHERE comment_post_ID = {$this->posts[0]->ID} AND comment_approved = '1'");
			$comments_request = "SELECT $wpdb->comments.* FROM $wpdb->comments $cjoin $cwhere ORDER BY comment_date_gmt DESC LIMIT " . get_option('posts_per_rss');
			$this->comments = $wpdb->get_results($comments_request);
			$this->comment_count = count($this->comments);
		}

		if ( !empty($limits) ) {
			$found_posts_query = apply_filters( 'found_posts_query', 'SELECT FOUND_ROWS()' );
			$this->found_posts = $wpdb->get_var( $found_posts_query );
			$this->found_posts = apply_filters( 'found_posts', $this->found_posts );
			$this->max_num_pages = ceil($this->found_posts / $q['posts_per_page']);
		}

		// Check post status to determine if post should be displayed.
		if ( !empty($this->posts) && ($this->is_single || $this->is_page) ) {
			$status = get_post_status($this->posts[0]);
			//$type = get_post_type($this->posts[0]);
			if ( ('publish' != $status) ) {
				if ( ! is_user_logged_in() ) {
					// User must be logged in to view unpublished posts.
					$this->posts = array();
				} else {
					if  (in_array($status, array('draft', 'pending')) ) {
						// User must have edit permissions on the draft to preview.
						if (! current_user_can('edit_post', $this->posts[0]->ID)) {
							$this->posts = array();
						} else {
							$this->is_preview = true;
							$this->posts[0]->post_date = current_time('mysql');
						}
					}  else if ('future' == $status) {
						$this->is_preview = true;
						if (!current_user_can('edit_post', $this->posts[0]->ID)) {
							$this->posts = array ( );
						}
					} else {
						if (! current_user_can('read_post', $this->posts[0]->ID))
							$this->posts = array();
					}
				}
			}
		}

		$this->posts = apply_filters('the_posts', $this->posts);

		update_post_caches($this->posts);

		$this->post_count = count($this->posts);
		if ($this->post_count > 0) {
			$this->post = $this->posts[0];
		}

		return $this->posts;
	}

	function next_post() {

		$this->current_post++;

		$this->post = $this->posts[$this->current_post];
		return $this->post;
	}

	function the_post() {
		global $post;
		$this->in_the_loop = true;
		$post = $this->next_post();
		setup_postdata($post);

		if ( $this->current_post == 0 ) // loop has just started
			do_action('loop_start');
	}

	function have_posts() {
		if ($this->current_post + 1 < $this->post_count) {
			return true;
		} elseif ($this->current_post + 1 == $this->post_count) {
			do_action('loop_end');
			// Do some cleaning up after the loop
			$this->rewind_posts();
		}

		$this->in_the_loop = false;
		return false;
	}

	function rewind_posts() {
		$this->current_post = -1;
		if ($this->post_count > 0) {
			$this->post = $this->posts[0];
		}
	}

	function next_comment() {
		$this->current_comment++;

		$this->comment = $this->comments[$this->current_comment];
		return $this->comment;
	}

	function the_comment() {
		global $comment;

		$comment = $this->next_comment();

		if ($this->current_comment == 0) {
			do_action('comment_loop_start');
		}
	}

	function have_comments() {
		if ($this->current_comment + 1 < $this->comment_count) {
			return true;
		} elseif ($this->current_comment + 1 == $this->comment_count) {
			$this->rewind_comments();
		}

		return false;
	}

	function rewind_comments() {
		$this->current_comment = -1;
		if ($this->comment_count > 0) {
			$this->comment = $this->comments[0];
		}
	}

	function &query($query) {
		$this->parse_query($query);
		return $this->get_posts();
	}

	function get_queried_object() {
		if (isset($this->queried_object)) {
			return $this->queried_object;
		}

		$this->queried_object = NULL;
		$this->queried_object_id = 0;

		if ($this->is_category) {
			$cat = $this->get('cat');
			$category = &get_category($cat);
			$this->queried_object = &$category;
			$this->queried_object_id = (int) $cat;
		} else if ($this->is_tag) {
			$tag_id = $this->get('tag_id');
			$tag = &get_term($tag_id, 'post_tag');
			if ( is_wp_error( $tag ) )
				return $tag;
			$this->queried_object = &$tag;
			$this->queried_object_id = (int) $tag_id;
		} else if ($this->is_posts_page) {
			$this->queried_object = & get_page(get_option('page_for_posts'));
			$this->queried_object_id = (int) $this->queried_object->ID;
		} else if ($this->is_single) {
			$this->queried_object = $this->post;
			$this->queried_object_id = (int) $this->post->ID;
		} else if ($this->is_page) {
			$this->queried_object = $this->post;
			$this->queried_object_id = (int) $this->post->ID;
		} else if ($this->is_author) {
			$author_id = (int) $this->get('author');
			$author = get_userdata($author_id);
			$this->queried_object = $author;
			$this->queried_object_id = $author_id;
		}

		return $this->queried_object;
	}

	function get_queried_object_id() {
		$this->get_queried_object();

		if (isset($this->queried_object_id)) {
			return $this->queried_object_id;
		}

		return 0;
	}

	function WP_Query ($query = '') {
		if (! empty($query)) {
			$this->query($query);
		}
	}
}


// Redirect old slugs
function wp_old_slug_redirect () {
	global $wp_query;
	if ( is_404() && '' != $wp_query->query_vars['name'] ) :
		global $wpdb;

		$query = "SELECT post_id FROM $wpdb->postmeta, $wpdb->posts WHERE ID = post_id AND meta_key = '_wp_old_slug' AND meta_value='" . $wp_query->query_vars['name'] . "'";

		// if year, monthnum, or day have been specified, make our query more precise
		// just in case there are multiple identical _wp_old_slug values
		if ( '' != $wp_query->query_vars['year'] )
			$query .= " AND YEAR(post_date) = '{$wp_query->query_vars['year']}'";
		if ( '' != $wp_query->query_vars['monthnum'] )
			$query .= " AND MONTH(post_date) = '{$wp_query->query_vars['monthnum']}'";
		if ( '' != $wp_query->query_vars['day'] )
			$query .= " AND DAYOFMONTH(post_date) = '{$wp_query->query_vars['day']}'";

		$id = (int) $wpdb->get_var($query);

		if ( !$id )
			return;

		$link = get_permalink($id);

		if ( !$link )
			return;

		wp_redirect($link, '301'); // Permanent redirect
		exit;
	endif;
}


//
// Private helper functions
//

// Setup global post data.
function setup_postdata($post) {
	global $id, $postdata, $authordata, $day, $currentmonth, $page, $pages, $multipage, $more, $numpages, $wp_query;
	global $pagenow;

	$id = (int) $post->ID;

	$authordata = get_userdata($post->post_author);

	$day = mysql2date('d.m.y', $post->post_date);
	$currentmonth = mysql2date('m', $post->post_date);
	$numpages = 1;
	$page = get_query_var('page');
	if ( !$page )
		$page = 1;
	if ( is_single() || is_page() )
		$more = 1;
	$content = $post->post_content;
	if ( preg_match('/<!--nextpage-->/', $content) ) {
		if ( $page > 1 )
			$more = 1;
		$multipage = 1;
		$content = str_replace("\n<!--nextpage-->\n", '<!--nextpage-->', $content);
		$content = str_replace("\n<!--nextpage-->", '<!--nextpage-->', $content);
		$content = str_replace("<!--nextpage-->\n", '<!--nextpage-->', $content);
		$pages = explode('<!--nextpage-->', $content);
		$numpages = count($pages);
	} else {
		$pages[0] = $post->post_content;
		$multipage = 0;
	}
	return true;
}

?>
