<?php

/* WP_Rewrite API
*******************************************************************************/

//Add a straight rewrite rule
function add_rewrite_rule($regex, $redirect, $after = 'bottom') {
	global $wp_rewrite;
	$wp_rewrite->add_rule($regex, $redirect, $after);
}

//Add a new tag (like %postname%)
//warning: you must call this on init or earlier, otherwise the query var addition stuff won't work
function add_rewrite_tag($tagname, $regex) {
	//validation
	if (strlen($tagname) < 3 || $tagname{0} != '%' || $tagname{strlen($tagname)-1} != '%') {
		return;
	}

	$qv = trim($tagname, '%');

	global $wp_rewrite, $wp;
	$wp->add_query_var($qv);
	$wp_rewrite->add_rewrite_tag($tagname, $regex, $qv . '=');
}

//Add a new feed type like /atom1/
function add_feed($feedname, $function) {
	global $wp_rewrite;
	if (!in_array($feedname, $wp_rewrite->feeds)) { //override the file if it is
		$wp_rewrite->feeds[] = $feedname;
	}
	$hook = 'do_feed_' . $feedname;
	// Remove default function hook
	remove_action($hook, $hook, 10, 1);
	add_action($hook, $function, 10, 1);
	return $hook;
}

define('EP_PERMALINK',  1   );
define('EP_ATTACHMENT', 2   );
define('EP_DATE',       4   );
define('EP_YEAR',       8   );
define('EP_MONTH',      16  );
define('EP_DAY',        32  );
define('EP_ROOT',       64  );
define('EP_COMMENTS',   128 );
define('EP_SEARCH',     256 );
define('EP_CATEGORIES', 512 );
define('EP_TAGS', 1024 );
define('EP_AUTHORS',    2048);
define('EP_PAGES',      4096);
//pseudo-places
define('EP_NONE',       0  );
define('EP_ALL',        8191);

//and an endpoint, like /trackback/
function add_rewrite_endpoint($name, $places) {
	global $wp_rewrite;
	$wp_rewrite->add_endpoint($name, $places);
}

/**
  * _wp_filter_taxonomy_base() - filter the URL base for taxonomies, to remove any manually prepended /index.php/
  * @param string $base the taxonomy base that we're going to filter
  * @return string
  * @author Mark Jaquith
  */
function _wp_filter_taxonomy_base( $base ) {
	if ( !empty( $base ) ) {
		$base = preg_replace( '|^/index\.php/|', '', $base );
		$base = trim( $base, '/' );
	}
	return $base;
}

// examine a url (supposedly from this blog) and try to
// determine the post ID it represents.
function url_to_postid($url) {
	global $wp_rewrite;

	$url = apply_filters('url_to_postid', $url);

	// First, check to see if there is a 'p=N' or 'page_id=N' to match against
	if ( preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values) )	{
		$id = absint($values[2]);
		if ($id)
			return $id;
	}

	// Check to see if we are using rewrite rules
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
	if ( empty($rewrite) )
		return 0;

	// $url cleanup by Mark Jaquith
	// This fixes things like #anchors, ?query=strings, missing 'www.',
	// added 'www.', or added 'index.php/' that will mess up our WP_Query
	// and return a false negative

	// Get rid of the #anchor
	$url_split = explode('#', $url);
	$url = $url_split[0];

	// Get rid of URL ?query=string
	$url_split = explode('?', $url);
	$url = $url_split[0];

	// Add 'www.' if it is absent and should be there
	if ( false !== strpos(get_option('home'), '://www.') && false === strpos($url, '://www.') )
		$url = str_replace('://', '://www.', $url);

	// Strip 'www.' if it is present and shouldn't be
	if ( false === strpos(get_option('home'), '://www.') )
		$url = str_replace('://www.', '://', $url);

	// Strip 'index.php/' if we're not using path info permalinks
	if ( !$wp_rewrite->using_index_permalinks() )
		$url = str_replace('index.php/', '', $url);

	if ( false !== strpos($url, get_option('home')) ) {
		// Chop off http://domain.com
		$url = str_replace(get_option('home'), '', $url);
	} else {
		// Chop off /path/to/blog
		$home_path = parse_url(get_option('home'));
		$home_path = $home_path['path'];
		$url = str_replace($home_path, '', $url);
	}

	// Trim leading and lagging slashes
	$url = trim($url, '/');

	$request = $url;

	// Done with cleanup

	// Look for matches.
	$request_match = $request;
	foreach ($rewrite as $match => $query) {
		// If the requesting file is the anchor of the match, prepend it
		// to the path info.
		if ( (! empty($url)) && (strpos($match, $url) === 0) && ($url != $request)) {
			$request_match = $url . '/' . $request;
		}

		if ( preg_match("!^$match!", $request_match, $matches) ) {
			// Got a match.
			// Trim the query of everything up to the '?'.
			$query = preg_replace("!^.+\?!", '', $query);

			// Substitute the substring matches into the query.
			eval("\$query = \"" . addslashes($query) . "\";");
			// Filter out non-public query vars
			global $wp;
			parse_str($query, $query_vars);
			$query = array();
			foreach ( (array) $query_vars as $key => $value ) {
				if ( in_array($key, $wp->public_query_vars) )
					$query[$key] = $value;
			}
			// Do the query
			$query = new WP_Query($query);
			if ( $query->is_single || $query->is_page )
				return $query->post->ID;
			else
				return 0;
		}
	}
	return 0;
}

/* WP_Rewrite class
*******************************************************************************/

class WP_Rewrite {
	var $permalink_structure;
	var $use_trailing_slashes;
	var $category_base;
	var $tag_base;
	var $category_structure;
	var $tag_structure;
	var $author_base = 'author';
	var $author_structure;
	var $date_structure;
	var $page_structure;
	var $search_base = 'search';
	var $search_structure;
	var $comments_base = 'comments';
	var $feed_base = 'feed';
	var $comments_feed_structure;
	var $feed_structure;
	var $front;
	var $root = '';
	var $index = 'index.php';
	var $matches = '';
	var $rules;
	var $extra_rules = array(); //those not generated by the class, see add_rewrite_rule()
	var $extra_rules_top = array(); //those not generated by the class, see add_rewrite_rule()
	var $non_wp_rules = array(); //rules that don't redirect to WP's index.php
	var $extra_permastructs = array();
	var $endpoints;
	var $use_verbose_rules = false;
	var $use_verbose_page_rules = true;
	var $rewritecode =
		array(
					'%year%',
					'%monthnum%',
					'%day%',
					'%hour%',
					'%minute%',
					'%second%',
					'%postname%',
					'%post_id%',
					'%category%',
					'%tag%',
					'%author%',
					'%pagename%',
					'%search%'
					);

	var $rewritereplace =
		array(
					'([0-9]{4})',
					'([0-9]{1,2})',
					'([0-9]{1,2})',
					'([0-9]{1,2})',
					'([0-9]{1,2})',
					'([0-9]{1,2})',
					'([^/]+)',
					'([0-9]+)',
					'(.+?)',
					'(.+?)',
					'([^/]+)',
					'([^/]+?)',
					'(.+)'
					);

	var $queryreplace =
		array (
					'year=',
					'monthnum=',
					'day=',
					'hour=',
					'minute=',
					'second=',
					'name=',
					'p=',
					'category_name=',
					'tag=',
					'author_name=',
					'pagename=',
					's='
					);

	var $feeds = array ( 'feed', 'rdf', 'rss', 'rss2', 'atom' );

	function using_permalinks() {
		if (empty($this->permalink_structure))
			return false;
		else
			return true;
	}

	function using_index_permalinks() {
		if (empty($this->permalink_structure)) {
			return false;
		}

		// If the index is not in the permalink, we're using mod_rewrite.
		if (preg_match('#^/*' . $this->index . '#', $this->permalink_structure)) {
			return true;
		}

		return false;
	}

	function using_mod_rewrite_permalinks() {
		if ( $this->using_permalinks() && ! $this->using_index_permalinks())
			return true;
		else
			return false;
	}

	function preg_index($number) {
		$match_prefix = '$';
		$match_suffix = '';

		if (! empty($this->matches)) {
			$match_prefix = '$' . $this->matches . '[';
			$match_suffix = ']';
		}

		return "$match_prefix$number$match_suffix";
	}

	function page_uri_index() {
		global $wpdb;

		//get pages in order of hierarchy, i.e. children after parents
		$posts = get_page_hierarchy($wpdb->get_results("SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_type = 'page'"));
		//now reverse it, because we need parents after children for rewrite rules to work properly
		$posts = array_reverse($posts, true);

		$page_uris = array();
		$page_attachment_uris = array();

		if ( !$posts )
			return array( array(), array() );


		foreach ($posts as $id => $post) {
			// URL => page name
			$uri = get_page_uri($id);
			$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_parent FROM $wpdb->posts WHERE post_type = 'attachment' AND post_parent = %d", $id ));
			if ( $attachments ) {
				foreach ( $attachments as $attachment ) {
					$attach_uri = get_page_uri($attachment->ID);
					$page_attachment_uris[$attach_uri] = $attachment->ID;
				}
			}

			$page_uris[$uri] = $id;
		}

		return array( $page_uris, $page_attachment_uris );
	}

	function page_rewrite_rules() {
		$rewrite_rules = array();
		$page_structure = $this->get_page_permastruct();

		if ( ! $this->use_verbose_page_rules ) {
			$this->add_rewrite_tag('%pagename%', "(.+?)", 'pagename=');
			$rewrite_rules = array_merge($rewrite_rules, $this->generate_rewrite_rules($page_structure, EP_PAGES));
			return $rewrite_rules;
		}

		$page_uris = $this->page_uri_index();
		$uris = $page_uris[0];
		$attachment_uris = $page_uris[1];


		if( is_array( $attachment_uris ) ) {
			foreach ($attachment_uris as $uri => $pagename) {
				$this->add_rewrite_tag('%pagename%', "($uri)", 'attachment=');
				$rewrite_rules = array_merge($rewrite_rules, $this->generate_rewrite_rules($page_structure, EP_PAGES));
			}
		}
		if( is_array( $uris ) ) {
			foreach ($uris as $uri => $pagename) {
				$this->add_rewrite_tag('%pagename%', "($uri)", 'pagename=');
				$rewrite_rules = array_merge($rewrite_rules, $this->generate_rewrite_rules($page_structure, EP_PAGES));
			}
		}

		return $rewrite_rules;
	}

	function get_date_permastruct() {
		if (isset($this->date_structure)) {
			return $this->date_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->date_structure = '';
			return false;
		}

		// The date permalink must have year, month, and day separated by slashes.
		$endians = array('%year%/%monthnum%/%day%', '%day%/%monthnum%/%year%', '%monthnum%/%day%/%year%');

		$this->date_structure = '';
		$date_endian = '';

		foreach ($endians as $endian) {
			if (false !== strpos($this->permalink_structure, $endian)) {
				$date_endian= $endian;
				break;
			}
		}

		if ( empty($date_endian) )
			$date_endian = '%year%/%monthnum%/%day%';

		// Do not allow the date tags and %post_id% to overlap in the permalink
		// structure. If they do, move the date tags to $front/date/.
		$front = $this->front;
		preg_match_all('/%.+?%/', $this->permalink_structure, $tokens);
		$tok_index = 1;
		foreach ( (array) $tokens[0] as $token) {
			if ( ($token == '%post_id%') && ($tok_index <= 3) ) {
				$front = $front . 'date/';
				break;
			}
			$tok_index++;
		}

		$this->date_structure = $front . $date_endian;

		return $this->date_structure;
	}

	function get_year_permastruct() {
		$structure = $this->get_date_permastruct($this->permalink_structure);

		if (empty($structure)) {
			return false;
		}

		$structure = str_replace('%monthnum%', '', $structure);
		$structure = str_replace('%day%', '', $structure);

		$structure = preg_replace('#/+#', '/', $structure);

		return $structure;
	}

	function get_month_permastruct() {
		$structure = $this->get_date_permastruct($this->permalink_structure);

		if (empty($structure)) {
			return false;
		}

		$structure = str_replace('%day%', '', $structure);

		$structure = preg_replace('#/+#', '/', $structure);

		return $structure;
	}

	function get_day_permastruct() {
		return $this->get_date_permastruct($this->permalink_structure);
	}

	function get_category_permastruct() {
		if (isset($this->category_structure)) {
			return $this->category_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->category_structure = '';
			return false;
		}

		if (empty($this->category_base))
			$this->category_structure = trailingslashit( $this->front . 'category' );
		else
			$this->category_structure = trailingslashit( '/' . $this->root . $this->category_base );

		$this->category_structure .= '%category%';

		return $this->category_structure;
	}

	function get_tag_permastruct() {
		if (isset($this->tag_structure)) {
			return $this->tag_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->tag_structure = '';
			return false;
		}

		if (empty($this->tag_base))
			$this->tag_structure = trailingslashit( $this->front . 'tag' );
		else
			$this->tag_structure = trailingslashit( '/' . $this->root . $this->tag_base );

		$this->tag_structure .= '%tag%';

		return $this->tag_structure;
	}

	function get_extra_permastruct($name) {
		if ( isset($this->extra_permastructs[$name]) )
			return $this->extra_permastructs[$name];
		return false;
	}

	function get_author_permastruct() {
		if (isset($this->author_structure)) {
			return $this->author_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->author_structure = '';
			return false;
		}

		$this->author_structure = $this->front . $this->author_base . '/%author%';

		return $this->author_structure;
	}

	function get_search_permastruct() {
		if (isset($this->search_structure)) {
			return $this->search_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->search_structure = '';
			return false;
		}

		$this->search_structure = $this->root . $this->search_base . '/%search%';

		return $this->search_structure;
	}

	function get_page_permastruct() {
		if (isset($this->page_structure)) {
			return $this->page_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->page_structure = '';
			return false;
		}

		$this->page_structure = $this->root . '%pagename%';

		return $this->page_structure;
	}

	function get_feed_permastruct() {
		if (isset($this->feed_structure)) {
			return $this->feed_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->feed_structure = '';
			return false;
		}

		$this->feed_structure = $this->root . $this->feed_base . '/%feed%';

		return $this->feed_structure;
	}

	function get_comment_feed_permastruct() {
		if (isset($this->comment_feed_structure)) {
			return $this->comment_feed_structure;
		}

		if (empty($this->permalink_structure)) {
			$this->comment_feed_structure = '';
			return false;
		}

		$this->comment_feed_structure = $this->root . $this->comments_base . '/' . $this->feed_base . '/%feed%';

		return $this->comment_feed_structure;
	}

	function add_rewrite_tag($tag, $pattern, $query) {
		// If the tag already exists, replace the existing pattern and query for
		// that tag, otherwise add the new tag, pattern, and query to the end of
		// the arrays.
		$position = array_search($tag, $this->rewritecode);
		if (FALSE !== $position && NULL !== $position) {
			$this->rewritereplace[$position] = $pattern;
			$this->queryreplace[$position] = $query;
		} else {
			$this->rewritecode[] = $tag;
			$this->rewritereplace[] = $pattern;
			$this->queryreplace[] = $query;
		}
	}

	//the main WP_Rewrite function. generate the rules from permalink structure
	function generate_rewrite_rules($permalink_structure, $ep_mask = EP_NONE, $paged = true, $feed = true, $forcomments = false, $walk_dirs = true, $endpoints = true) {
		//build a regex to match the feed section of URLs, something like (feed|atom|rss|rss2)/?
		$feedregex2 = '';
		foreach ( (array) $this->feeds as $feed_name) {
			$feedregex2 .= $feed_name . '|';
		}
		$feedregex2 = '(' . trim($feedregex2, '|') .  ')/?$';
		//$feedregex is identical but with /feed/ added on as well, so URLs like <permalink>/feed/atom
		//and <permalink>/atom are both possible
		$feedregex = $this->feed_base  . '/' . $feedregex2;

		//build a regex to match the trackback and page/xx parts of URLs
		$trackbackregex = 'trackback/?$';
		$pageregex = 'page/?([0-9]{1,})/?$';

		//build up an array of endpoint regexes to append => queries to append
		if ($endpoints) {
			$ep_query_append = array ();
			foreach ( (array) $this->endpoints as $endpoint) {
				//match everything after the endpoint name, but allow for nothing to appear there
				$epmatch = $endpoint[1] . '(/(.*))?/?$';
				//this will be appended on to the rest of the query for each dir
				$epquery = '&' . $endpoint[1] . '=';
				$ep_query_append[$epmatch] = array ( $endpoint[0], $epquery );
			}
		}

		//get everything up to the first rewrite tag
		$front = substr($permalink_structure, 0, strpos($permalink_structure, '%'));
		//build an array of the tags (note that said array ends up being in $tokens[0])
		preg_match_all('/%.+?%/', $permalink_structure, $tokens);

		$num_tokens = count($tokens[0]);

		$index = $this->index; //probably 'index.php'
		$feedindex = $index;
		$trackbackindex = $index;
		//build a list from the rewritecode and queryreplace arrays, that will look something like
		//tagname=$matches[i] where i is the current $i
		for ($i = 0; $i < $num_tokens; ++$i) {
			if (0 < $i) {
				$queries[$i] = $queries[$i - 1] . '&';
			} else {
				$queries[$i] = '';
			}

			$query_token = str_replace($this->rewritecode, $this->queryreplace, $tokens[0][$i]) . $this->preg_index($i+1);
			$queries[$i] .= $query_token;
		}

		//get the structure, minus any cruft (stuff that isn't tags) at the front
		$structure = $permalink_structure;
		if ($front != '/') {
			$structure = str_replace($front, '', $structure);
		}
		//create a list of dirs to walk over, making rewrite rules for each level
		//so for example, a $structure of /%year%/%month%/%postname% would create
		//rewrite rules for /%year%/, /%year%/%month%/ and /%year%/%month%/%postname%
		$structure = trim($structure, '/');
		if ($walk_dirs) {
			$dirs = explode('/', $structure);
		} else {
			$dirs[] = $structure;
		}
		$num_dirs = count($dirs);

		//strip slashes from the front of $front
		$front = preg_replace('|^/+|', '', $front);

		//the main workhorse loop
		$post_rewrite = array();
		$struct = $front;
		for ($j = 0; $j < $num_dirs; ++$j) {
			//get the struct for this dir, and trim slashes off the front
			$struct .= $dirs[$j] . '/'; //accumulate. see comment near explode('/', $structure) above
			$struct = ltrim($struct, '/');
			//replace tags with regexes
			$match = str_replace($this->rewritecode, $this->rewritereplace, $struct);
			//make a list of tags, and store how many there are in $num_toks
			$num_toks = preg_match_all('/%.+?%/', $struct, $toks);
			//get the 'tagname=$matches[i]'
			$query = ( isset($queries) && is_array($queries) ) ? $queries[$num_toks - 1] : '';

			//set up $ep_mask_specific which is used to match more specific URL types
			switch ($dirs[$j]) {
				case '%year%': $ep_mask_specific = EP_YEAR; break;
				case '%monthnum%': $ep_mask_specific = EP_MONTH; break;
				case '%day%': $ep_mask_specific = EP_DAY; break;
			}

			//create query for /page/xx
			$pagematch = $match . $pageregex;
			$pagequery = $index . '?' . $query . '&paged=' . $this->preg_index($num_toks + 1);

			//create query for /feed/(feed|atom|rss|rss2|rdf)
			$feedmatch = $match . $feedregex;
			$feedquery = $feedindex . '?' . $query . '&feed=' . $this->preg_index($num_toks + 1);

			//create query for /(feed|atom|rss|rss2|rdf) (see comment near creation of $feedregex)
			$feedmatch2 = $match . $feedregex2;
			$feedquery2 = $feedindex . '?' . $query . '&feed=' . $this->preg_index($num_toks + 1);

			//if asked to, turn the feed queries into comment feed ones
			if ($forcomments) {
				$feedquery .= '&withcomments=1';
				$feedquery2 .= '&withcomments=1';
			}

			//start creating the array of rewrites for this dir
			$rewrite = array();
			if ($feed) //...adding on /feed/ regexes => queries
				$rewrite = array($feedmatch => $feedquery, $feedmatch2 => $feedquery2);
			if ($paged) //...and /page/xx ones
				$rewrite = array_merge($rewrite, array($pagematch => $pagequery));

			//do endpoints
			if ($endpoints) {
				foreach ( (array) $ep_query_append as $regex => $ep) {
					//add the endpoints on if the mask fits
					if ($ep[0] & $ep_mask || $ep[0] & $ep_mask_specific) {
						$rewrite[$match . $regex] = $index . '?' . $query . $ep[1] . $this->preg_index($num_toks + 2);
					}
				}
			}

			//if we've got some tags in this dir
			if ($num_toks) {
				$post = false;
				$page = false;

				//check to see if this dir is permalink-level: i.e. the structure specifies an
				//individual post. Do this by checking it contains at least one of 1) post name,
				//2) post ID, 3) page name, 4) timestamp (year, month, day, hour, second and
				//minute all present). Set these flags now as we need them for the endpoints.
				if (strpos($struct, '%postname%') !== false || strpos($struct, '%post_id%') !== false
						|| strpos($struct, '%pagename%') !== false
						|| (strpos($struct, '%year%') !== false && strpos($struct, '%monthnum%') !== false && strpos($struct, '%day%') !== false && strpos($struct, '%hour%') !== false && strpos($struct, '%minute%') !== false && strpos($struct, '%second%') !== false)) {
					$post = true;
					if (strpos($struct, '%pagename%') !== false)
						$page = true;
				}

				//if we're creating rules for a permalink, do all the endpoints like attachments etc
				if ($post) {
					$post = true;
					//create query and regex for trackback
					$trackbackmatch = $match . $trackbackregex;
					$trackbackquery = $trackbackindex . '?' . $query . '&tb=1';
					//trim slashes from the end of the regex for this dir
					$match = rtrim($match, '/');
					//get rid of brackets
					$submatchbase = str_replace(array('(',')'),'',$match);

					//add a rule for at attachments, which take the form of <permalink>/some-text
					$sub1 = $submatchbase . '/([^/]+)/';
					$sub1tb = $sub1 . $trackbackregex; //add trackback regex <permalink>/trackback/...
					$sub1feed = $sub1 . $feedregex; //and <permalink>/feed/(atom|...)
					$sub1feed2 = $sub1 . $feedregex2; //and <permalink>/(feed|atom...)
					//add an ? as we don't have to match that last slash, and finally a $ so we
					//match to the end of the URL

					//add another rule to match attachments in the explicit form:
					//<permalink>/attachment/some-text
					$sub2 = $submatchbase . '/attachment/([^/]+)/';
					$sub2tb = $sub2 . $trackbackregex; //and add trackbacks <permalink>/attachment/trackback
					$sub2feed = $sub2 . $feedregex;    //feeds, <permalink>/attachment/feed/(atom|...)
					$sub2feed2 = $sub2 . $feedregex2;  //and feeds again on to this <permalink>/attachment/(feed|atom...)

					//create queries for these extra tag-ons we've just dealt with
					$subquery = $index . '?attachment=' . $this->preg_index(1);
					$subtbquery = $subquery . '&tb=1';
					$subfeedquery = $subquery . '&feed=' . $this->preg_index(2);

					//do endpoints for attachments
					if ( !empty($endpoint) ) { foreach ( (array) $ep_query_append as $regex => $ep ) {
						if ($ep[0] & EP_ATTACHMENT) {
							$rewrite[$sub1 . $regex] = $subquery . '?' . $ep[1] . $this->preg_index(2);
							$rewrite[$sub2 . $regex] = $subquery . '?' . $ep[1] . $this->preg_index(2);
						}
					} }

					//now we've finished with endpoints, finish off the $sub1 and $sub2 matches
					$sub1 .= '?$';
					$sub2 .= '?$';

					//allow URLs like <permalink>/2 for <permalink>/page/2
					$match = $match . '(/[0-9]+)?/?$';
					$query = $index . '?' . $query . '&page=' . $this->preg_index($num_toks + 1);
				} else { //not matching a permalink so this is a lot simpler
					//close the match and finalise the query
					$match .= '?$';
					$query = $index . '?' . $query;
				}

				//create the final array for this dir by joining the $rewrite array (which currently
				//only contains rules/queries for trackback, pages etc) to the main regex/query for
				//this dir
				$rewrite = array_merge($rewrite, array($match => $query));

				//if we're matching a permalink, add those extras (attachments etc) on
				if ($post) {
					//add trackback
					$rewrite = array_merge(array($trackbackmatch => $trackbackquery), $rewrite);

					//add regexes/queries for attachments, attachment trackbacks and so on
					if ( ! $page ) //require <permalink>/attachment/stuff form for pages because of confusion with subpages
						$rewrite = array_merge($rewrite, array($sub1 => $subquery, $sub1tb => $subtbquery, $sub1feed => $subfeedquery, $sub1feed2 => $subfeedquery));
					$rewrite = array_merge(array($sub2 => $subquery, $sub2tb => $subtbquery, $sub2feed => $subfeedquery, $sub2feed2 => $subfeedquery), $rewrite);
				}
			} //if($num_toks)
			//add the rules for this dir to the accumulating $post_rewrite
			$post_rewrite = array_merge($rewrite, $post_rewrite);
		} //foreach ($dir)
		return $post_rewrite; //the finished rules. phew!
	}

	function generate_rewrite_rule($permalink_structure, $walk_dirs = false) {
		return $this->generate_rewrite_rules($permalink_structure, EP_NONE, false, false, false, $walk_dirs);
	}

	/* rewrite_rules
	 * Construct rewrite matches and queries from permalink structure.
	 * Returns an associate array of matches and queries.
	 */
	function rewrite_rules() {
		$rewrite = array();

		if (empty($this->permalink_structure)) {
			return $rewrite;
		}

		// robots.txt
		$robots_rewrite = array('robots.txt$' => $this->index . '?robots=1');

		//Default Feed rules - These are require to allow for the direct access files to work with permalink structure starting with %category%
		$default_feeds = array(	'.*wp-atom.php$'	=>	$this->index .'?feed=atom',
								'.*wp-rdf.php$'	=>	$this->index .'?feed=rdf',
								'.*wp-rss.php$'	=>	$this->index .'?feed=rss',
								'.*wp-rss2.php$'	=>	$this->index .'?feed=rss2',
								'.*wp-feed.php$'	=>	$this->index .'?feed=feed',
								'.*wp-commentsrss2.php$'	=>	$this->index . '?feed=rss2&withcomments=1');

		// Post
		$post_rewrite = $this->generate_rewrite_rules($this->permalink_structure, EP_PERMALINK);
		$post_rewrite = apply_filters('post_rewrite_rules', $post_rewrite);

		// Date
		$date_rewrite = $this->generate_rewrite_rules($this->get_date_permastruct(), EP_DATE);
		$date_rewrite = apply_filters('date_rewrite_rules', $date_rewrite);

		// Root
		$root_rewrite = $this->generate_rewrite_rules($this->root . '/', EP_ROOT);
		$root_rewrite = apply_filters('root_rewrite_rules', $root_rewrite);

		// Comments
		$comments_rewrite = $this->generate_rewrite_rules($this->root . $this->comments_base, EP_COMMENTS, true, true, true, false);
		$comments_rewrite = apply_filters('comments_rewrite_rules', $comments_rewrite);

		// Search
		$search_structure = $this->get_search_permastruct();
		$search_rewrite = $this->generate_rewrite_rules($search_structure, EP_SEARCH);
		$search_rewrite = apply_filters('search_rewrite_rules', $search_rewrite);

		// Categories
		$category_rewrite = $this->generate_rewrite_rules($this->get_category_permastruct(), EP_CATEGORIES);
		$category_rewrite = apply_filters('category_rewrite_rules', $category_rewrite);

		// Tags
		$tag_rewrite = $this->generate_rewrite_rules($this->get_tag_permastruct(), EP_TAGS);
		$tag_rewrite = apply_filters('tag_rewrite_rules', $tag_rewrite);

		// Authors
		$author_rewrite = $this->generate_rewrite_rules($this->get_author_permastruct(), EP_AUTHORS);
		$author_rewrite = apply_filters('author_rewrite_rules', $author_rewrite);

		// Pages
		$page_rewrite = $this->page_rewrite_rules();
		$page_rewrite = apply_filters('page_rewrite_rules', $page_rewrite);

		// Extra permastructs
		foreach ( $this->extra_permastructs as $permastruct )
			$this->extra_rules_top = array_merge($this->extra_rules_top, $this->generate_rewrite_rules($permastruct, EP_NONE));

		// Put them together.
		if ( $this->use_verbose_page_rules )
			$this->rules = array_merge($this->extra_rules_top, $robots_rewrite, $default_feeds, $page_rewrite, $root_rewrite, $comments_rewrite, $search_rewrite, $category_rewrite, $tag_rewrite, $author_rewrite, $date_rewrite, $post_rewrite, $this->extra_rules);
		else
			$this->rules = array_merge($this->extra_rules_top, $robots_rewrite, $default_feeds, $root_rewrite, $comments_rewrite, $search_rewrite, $category_rewrite, $tag_rewrite, $author_rewrite, $date_rewrite, $post_rewrite, $page_rewrite, $this->extra_rules);

		do_action_ref_array('generate_rewrite_rules', array(&$this));
		$this->rules = apply_filters('rewrite_rules_array', $this->rules);

		return $this->rules;
	}

	function wp_rewrite_rules() {
		$this->rules = get_option('rewrite_rules');
		if ( empty($this->rules) ) {
			$this->matches = 'matches';
			$this->rewrite_rules();
			update_option('rewrite_rules', $this->rules);
		}

		return $this->rules;
	}

	function mod_rewrite_rules() {
		if ( ! $this->using_permalinks()) {
			return '';
		}

		$site_root = parse_url(get_option('siteurl'));
		$site_root = trailingslashit($site_root['path']);

		$home_root = parse_url(get_option('home'));
		$home_root = trailingslashit($home_root['path']);

		$rules = "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteBase $home_root\n";

		//add in the rules that don't redirect to WP's index.php (and thus shouldn't be handled by WP at all)
		foreach ( (array) $this->non_wp_rules as $match => $query) {
			// Apache 1.3 does not support the reluctant (non-greedy) modifier.
			$match = str_replace('.+?', '.+', $match);

			// If the match is unanchored and greedy, prepend rewrite conditions
			// to avoid infinite redirects and eclipsing of real files.
			if ($match == '(.+)/?$' || $match == '([^/]+)/?$' ) {
				//nada.
			}

			$rules .= 'RewriteRule ^' . $match . ' ' . $home_root . $query . " [QSA,L]\n";
		}

		if ($this->use_verbose_rules) {
			$this->matches = '';
			$rewrite = $this->rewrite_rules();
			$num_rules = count($rewrite);
			$rules .= "RewriteCond %{REQUEST_FILENAME} -f [OR]\n" .
				"RewriteCond %{REQUEST_FILENAME} -d\n" .
				"RewriteRule ^.*$ - [S=$num_rules]\n";

			foreach ( (array) $rewrite as $match => $query) {
				// Apache 1.3 does not support the reluctant (non-greedy) modifier.
				$match = str_replace('.+?', '.+', $match);

				// If the match is unanchored and greedy, prepend rewrite conditions
				// to avoid infinite redirects and eclipsing of real files.
				if ($match == '(.+)/?$' || $match == '([^/]+)/?$' ) {
					//nada.
				}

				if (strpos($query, $this->index) !== false) {
					$rules .= 'RewriteRule ^' . $match . ' ' . $home_root . $query . " [QSA,L]\n";
				} else {
					$rules .= 'RewriteRule ^' . $match . ' ' . $site_root . $query . " [QSA,L]\n";
				}
			}
		} else {
			$rules .= "RewriteCond %{REQUEST_FILENAME} !-f\n" .
				"RewriteCond %{REQUEST_FILENAME} !-d\n" .
				"RewriteRule . {$home_root}{$this->index} [L]\n";
		}

		$rules .= "</IfModule>\n";

		$rules = apply_filters('mod_rewrite_rules', $rules);
		$rules = apply_filters('rewrite_rules', $rules);  // Deprecated

		return $rules;
	}

	//Add a straight rewrite rule
	function add_rule($regex, $redirect, $after = 'bottom') {
		//get everything up to the first ?
		$index = (strpos($redirect, '?') == false ? strlen($redirect) : strpos($redirect, '?'));
		$front = substr($redirect, 0, $index);
		if ($front != $this->index) { //it doesn't redirect to WP's index.php
			$this->add_external_rule($regex, $redirect);
		} else {
			if ( 'bottom' == $after)
				$this->extra_rules = array_merge($this->extra_rules, array($regex => $redirect));
			else
				$this->extra_rules_top = array_merge($this->extra_rules_top, array($regex => $redirect));
			//$this->extra_rules[$regex] = $redirect;
		}
	}

	//add a rule that doesn't redirect to index.php
	function add_external_rule($regex, $redirect) {
		$this->non_wp_rules[$regex] = $redirect;
	}

	//add an endpoint, like /trackback/, to be inserted after certain URL types (specified in $places)
	function add_endpoint($name, $places) {
		global $wp;
		$this->endpoints[] = array ( $places, $name );
		$wp->add_query_var($name);
	}

	function add_permastruct($name, $struct, $with_front = true) {
		if ( $with_front )
			$struct = $this->front . $struct;
		$this->extra_permastructs[$name] = $struct;
	}

	function flush_rules() {
		delete_option('rewrite_rules');
		$this->wp_rewrite_rules();
		if ( function_exists('save_mod_rewrite_rules') )
			save_mod_rewrite_rules();
	}

	function init() {
		$this->extra_rules = $this->non_wp_rules = $this->endpoints = array();
		$this->permalink_structure = get_option('permalink_structure');
		$this->front = substr($this->permalink_structure, 0, strpos($this->permalink_structure, '%'));
		$this->root = '';
		if ($this->using_index_permalinks()) {
			$this->root = $this->index . '/';
		}
		$this->category_base = get_option( 'category_base' );
		$this->tag_base = get_option( 'tag_base' );
		unset($this->category_structure);
		unset($this->author_structure);
		unset($this->date_structure);
		unset($this->page_structure);
		unset($this->search_structure);
		unset($this->feed_structure);
		unset($this->comment_feed_structure);
		$this->use_trailing_slashes = ( substr($this->permalink_structure, -1, 1) == '/' ) ? true : false;

		// Enable generic rules for pages if permalink structure doesn't begin with a wildcard.
		$structure = ltrim($this->permalink_structure, '/');
		if ( $this->using_index_permalinks() )
			$structure = ltrim($this->permalink_structure, $this->index . '/');
		if ( 0 === strpos($structure, '%postname%') ||
			 0 === strpos($structure, '%category%') ||
			 0 === strpos($structure, '%tag%') ||
			 0 === strpos($structure, '%author%') )
			 $this->use_verbose_page_rules = true;
		else
			$this->use_verbose_page_rules = false;
	}

	function set_permalink_structure($permalink_structure) {
		if ($permalink_structure != $this->permalink_structure) {
			update_option('permalink_structure', $permalink_structure);
			$this->init();
		}
	}

	function set_category_base($category_base) {
		if ($category_base != $this->category_base) {
			update_option('category_base', $category_base);
			$this->init();
		}
	}

	function set_tag_base( $tag_base ) {
		if ( $tag_base != $this->tag_base ) {
			update_option( 'tag_base', $tag_base );
			$this->init();
		}
	}

	function WP_Rewrite() {
		$this->init();
	}
}

?>
