<?php
/**
 * Post format functions.
 *
 * @package WordPress
 * @subpackage Post
 */

/**
 * Retrieve the format slug for a post
 *
 * @since 3.1.0
 *
 * @param int|object $post A post
 *
 * @return mixed The format if successful. False if no format is set. WP_Error if errors.
 */
function get_post_format( $post = null ) {
	$post = get_post($post);

	if ( ! post_type_supports( $post->post_type, 'post-formats' ) )
		return false;

	$_format = get_the_terms( $post->ID, 'post_format' );

	if ( empty( $_format ) )
		return false;

	$format = array_shift( $_format );

	return ( str_replace('post-format-', '', $format->slug ) );
}

/**
 * Check if a post has a particular format
 *
 * @since 3.1.0
 * @uses has_term()
 *
 * @param string $format The format to check for
 * @param object|id $post The post to check. If not supplied, defaults to the current post if used in the loop.
 * @return bool True if the post has the format, false otherwise.
 */
function has_post_format( $format, $post = null ) {
	return has_term('post-format-' . sanitize_key($format), 'post_format', $post);
}

/**
 * Assign a format to a post
 *
 * @since 3.1.0
 *
 * @param int|object $post The post for which to assign a format
 * @param string $format  A format to assign. Use an empty string or array to remove all formats from the post.
 * @return mixed WP_Error on error. Array of affected term IDs on success.
 */
function set_post_format( $post, $format ) {
	$post = get_post($post);

	if ( empty($post) )
		return new WP_Error('invalid_post', __('Invalid post'));

	if ( !empty($format) ) {
		$format = sanitize_key($format);
		if ( 'standard' == $format || !in_array( $format, array_keys( get_post_format_slugs() ) ) )
			$format = '';
		else
			$format = 'post-format-' . $format;
	}

	return wp_set_post_terms($post->ID, $format, 'post_format');
}

/**
 * Retrieve post format metadata for a post
 *
 * @since 3.6.0
 *
 * @param int $post_id
 * @return null
 */
function get_post_format_meta( $post_id = 0 ) {
	$values = array(
		'quote'        => '',
		'quote_source' => '',
		'url'          => '',
		'media'        => '',
	);

	foreach ( $values as $key => $value )
		$values[$key] = get_post_meta( $post_id, '_wp_format_' . $key, true );

	return $values;
}

/**
 * Returns an array of post format slugs to their translated and pretty display versions
 *
 * @since 3.1.0
 *
 * @return array The array of translations
 */
function get_post_format_strings() {
	$strings = array(
		'standard' => _x( 'Standard', 'Post format' ), // Special case. any value that evals to false will be considered standard
		'aside'    => _x( 'Aside',    'Post format' ),
		'chat'     => _x( 'Chat',     'Post format' ),
		'gallery'  => _x( 'Gallery',  'Post format' ),
		'link'     => _x( 'Link',     'Post format' ),
		'image'    => _x( 'Image',    'Post format' ),
		'quote'    => _x( 'Quote',    'Post format' ),
		'status'   => _x( 'Status',   'Post format' ),
		'video'    => _x( 'Video',    'Post format' ),
		'audio'    => _x( 'Audio',    'Post format' ),
	);
	return $strings;
}

/**
 * Retrieves an array of post format slugs.
 *
 * @since 3.1.0
 *
 * @return array The array of post format slugs.
 */
function get_post_format_slugs() {
	$slugs = array_keys( get_post_format_strings() );
	return array_combine( $slugs, $slugs );
}

/**
 * Returns a pretty, translated version of a post format slug
 *
 * @since 3.1.0
 *
 * @param string $slug A post format slug
 * @return string The translated post format name
 */
function get_post_format_string( $slug ) {
	$strings = get_post_format_strings();
	if ( !$slug )
		return $strings['standard'];
	else
		return ( isset( $strings[$slug] ) ) ? $strings[$slug] : '';
}

/**
 * Returns a link to a post format index.
 *
 * @since 3.1.0
 *
 * @param string $format Post format
 * @return string Link
 */
function get_post_format_link( $format ) {
	$term = get_term_by('slug', 'post-format-' . $format, 'post_format' );
	if ( ! $term || is_wp_error( $term ) )
		return false;
	return get_term_link( $term );
}

/**
 * Filters the request to allow for the format prefix.
 *
 * @access private
 * @since 3.1.0
 */
function _post_format_request( $qvs ) {
	if ( ! isset( $qvs['post_format'] ) )
		return $qvs;
	$slugs = get_post_format_slugs();
	if ( isset( $slugs[ $qvs['post_format'] ] ) )
		$qvs['post_format'] = 'post-format-' . $slugs[ $qvs['post_format'] ];
	$tax = get_taxonomy( 'post_format' );
	if ( ! is_admin() )
		$qvs['post_type'] = $tax->object_type;
	return $qvs;
}
add_filter( 'request', '_post_format_request' );

/**
 * Filters the post format term link to remove the format prefix.
 *
 * @access private
 * @since 3.1.0
 */
function _post_format_link( $link, $term, $taxonomy ) {
	global $wp_rewrite;
	if ( 'post_format' != $taxonomy )
		return $link;
	if ( $wp_rewrite->get_extra_permastruct( $taxonomy ) ) {
		return str_replace( "/{$term->slug}", '/' . str_replace( 'post-format-', '', $term->slug ), $link );
	} else {
		$link = remove_query_arg( 'post_format', $link );
		return add_query_arg( 'post_format', str_replace( 'post-format-', '', $term->slug ), $link );
	}
}
add_filter( 'term_link', '_post_format_link', 10, 3 );

/**
 * Remove the post format prefix from the name property of the term object created by get_term().
 *
 * @access private
 * @since 3.1.0
 */
function _post_format_get_term( $term ) {
	if ( isset( $term->slug ) ) {
		$term->name = get_post_format_string( str_replace( 'post-format-', '', $term->slug ) );
	}
	return $term;
}
add_filter( 'get_post_format', '_post_format_get_term' );

/**
 * Remove the post format prefix from the name property of the term objects created by get_terms().
 *
 * @access private
 * @since 3.1.0
 */
function _post_format_get_terms( $terms, $taxonomies, $args ) {
	if ( in_array( 'post_format', (array) $taxonomies ) ) {
		if ( isset( $args['fields'] ) && 'names' == $args['fields'] ) {
			foreach( $terms as $order => $name ) {
				$terms[$order] = get_post_format_string( str_replace( 'post-format-', '', $name ) );
			}
		} else {
			foreach ( (array) $terms as $order => $term ) {
				if ( isset( $term->taxonomy ) && 'post_format' == $term->taxonomy ) {
					$terms[$order]->name = get_post_format_string( str_replace( 'post-format-', '', $term->slug ) );
				}
			}
		}
	}
	return $terms;
}
add_filter( 'get_terms', '_post_format_get_terms', 10, 3 );

/**
 * Remove the post format prefix from the name property of the term objects created by wp_get_object_terms().
 *
 * @access private
 * @since 3.1.0
 */
function _post_format_wp_get_object_terms( $terms ) {
	foreach ( (array) $terms as $order => $term ) {
		if ( isset( $term->taxonomy ) && 'post_format' == $term->taxonomy ) {
			$terms[$order]->name = get_post_format_string( str_replace( 'post-format-', '', $term->slug ) );
		}
	}
	return $terms;
}
add_filter( 'wp_get_object_terms', '_post_format_wp_get_object_terms' );

/**
 * Return the class for a post format content wrapper
 *
 * @since 3.6.0
 *
 * @param string $format
 */
function get_post_format_content_class( $format ) {
	return apply_filters( 'post_format_content_class', 'post-format-content', $format );
}

/**
 * Ouput the class for a post format content wrapper
 *
 * @since 3.6.0
 *
 * @param string $format
 */
function post_format_content_class( $format ) {
	echo get_post_format_content_class( $format );
}

/**
 * Provide fallback behavior for Posts that have associated post format
 *
 * @since 3.6.0
 *
 * @param string $content
 */
function post_formats_compat( $content, $id = 0 ) {
	$post = empty( $id ) ? get_post() : get_post( $id );
	if ( empty( $post ) )
		return $content;

	$format = get_post_format( $post );
	if ( empty( $format ) || in_array( $format, array( 'status', 'aside', 'chat' ) ) )
		return $content;

	if ( current_theme_supports( 'structured-post-formats', $format ) )
		return $content;

	$defaults = array(
		'position' => 'after',
		'tag' => 'div',
		'class' => get_post_format_content_class( $format ),
		'link_class' => '',
	);

	$args = apply_filters( 'post_format_compat', array() );
	$compat = wp_parse_args( $args, $defaults );

	$show_content = true;
	$format_output = '';
	$meta = get_post_format_meta( $post->ID );
	// passed by ref in preg_match()
	$matches = array();

	switch ( $format ) {
		case 'link':
			$compat['tag'] = '';
			$compat['position'] = 'before';

			if ( ! empty( $meta['url'] ) ) {
				$esc_url = preg_quote( $meta['url'], '#' );
				// Make sure the same URL isn't in the post (modified/extended versions allowed)
				if ( ! preg_match( '#' . $esc_url . '[^/&\?]?#', $content ) ) {
					$url = $meta['url'];
				} else {
					$url = get_content_url( $content, true );
				}
			} else {
				$content_before = $content;
				$url = get_content_url( $content, true );
				if ( $content_before == $content )
					$url = '';
			}

			if ( ! empty( $url ) ) {
				$format_output .= sprintf(
					'<a %shref="%s">%s</a>',
					empty( $compat['link_class'] ) ? '' : sprintf( 'class="%s" ', esc_attr( $compat['link_class'] ) ),
					esc_url( $url ),
					empty( $post->post_title ) ? esc_url( $meta['url'] ) : apply_filters( 'the_title', $post->post_title )
				);
 			}
			break;

		case 'quote':
			if ( ! empty( $meta['quote'] ) && ! stristr( $content, $meta['quote'] ) ) {
				$format_output .= sprintf( '<blockquote>%s</blockquote>', $meta['quote'] );
				if ( ! empty( $meta['quote_source'] ) ) {
					$format_output .= sprintf(
						'<cite>%s</cite>',
						! empty( $meta['url'] ) ?
							sprintf( '<a href="%s">%s</a>', esc_url( $meta['url'] ), $meta['quote_source'] ) :
							$meta['quote_source']
					);
				}
			}
			break;

		case 'video':
		case 'audio':
			if ( ! has_shortcode( $post->post_content, $format ) && ! empty( $meta['media'] ) ) {
				// the metadata is a shortcode or an embed code
				if ( preg_match( '/' . get_shortcode_regex() . '/s', $meta['media'] ) || preg_match( '#<[^>]+>#', $meta['media'] ) ) {
					$format_output .= $meta['media'];
				} elseif ( ! stristr( $content, $meta['media'] ) ) {
					// attempt to embed the URL
					$format_output .= sprintf( '[embed]%s[/embed]', $meta['media'] );
				}
			}
			break;
		default:
			return $content;
			break;
	}

	if ( empty( $format_output ) )
		return $content;

	$output = '';

	if ( ! empty( $content ) && $show_content && 'before' !== $compat['position'] )
		$output .= $content . "\n\n";

	if ( ! empty( $compat['tag'] ) )
		$output .= sprintf( '<%s class="%s">', tag_escape( $compat['tag'] ), esc_attr( $compat['class'] ) );

	$output .= "\n\n" . $format_output;

	if ( ! empty( $compat['tag'] ) )
		$output .= sprintf( '</%s>', tag_escape( $compat['tag'] ) );

	if ( ! empty( $content ) && $show_content && 'before' === $compat['position'] )
		$output .= "\n\n" . $content;

	return $output;
}

/**
 * Add chat detection support to the `get_content_chat()` chat parser
 *
 * @since 3.6.0
 *
 * @global array $_wp_chat_parsers
 * @param string $name Unique identifier for chat format. Example: IRC
 * @param string $newline_regex RegEx to match the start of a new line, typically when a new "username:" appears
 *	The parser will handle up to 3 matched expressions
 *	$matches[0] = the string before the user's message starts
 *	$matches[1] = the time of the message, if present
 *	$matches[2] = the author/username
 *	OR
 *	$matches[0] = the string before the user's message starts
 *	$matches[1] = the author/username
 * @param string $delimiter_regex RegEx to determine where to split the username syntax from the chat message
 */
function add_chat_detection_format( $name, $newline_regex, $delimiter_regex ) {
	global $_wp_chat_parsers;

	if ( empty( $_wp_chat_parsers ) )
		$_wp_chat_parsers = array();

	$_wp_chat_parsers = array( $name => array( $newline_regex, $delimiter_regex ) ) + $_wp_chat_parsers;
}
add_chat_detection_format( 'IM', '#^([^:]+):#', '#[:]#' );
add_chat_detection_format( 'Skype', '#^(\[.+?\])\s([^:]+):#', '#[:]#' );

/**
 * Deliberately interpret passed content as a chat transcript that is optionally
 * followed by commentary
 *
 * If the content does not contain username syntax, assume that it does not contain
 * chat logs and return
 *
 * @since 3.6.0
 *
 * Example:
 *
 * One stanza of chat:
 * Scott: Hey, let's chat!
 * Helen: No.
 *
 * $stanzas = array(
 *     array(
 *         array(
 *             'time' => '',
 *             'author' => 'Scott',
 *             'messsage' => "Hey, let's chat!"
 *         ),
 *         array(
 *             'time' => '',
 *             'author' => 'Helen',
 *             'message' => 'No.'
 *         )
 *     )
 * )
 * @param string $content A string which might contain chat data.
 * @param boolean $remove Whether to remove the found data from the passed content.
 * @return array A chat log as structured data
 */
function get_content_chat( &$content, $remove = false ) {
	global $_wp_chat_parsers;

	$trimmed = trim( $content );
	if ( empty( $trimmed ) )
		return array();

	$has_match = false;
	$matched_parser = false;
	foreach ( $_wp_chat_parsers as $parser ) {
		@list( $newline_regex ) = $parser;
		if ( preg_match( $newline_regex, $trimmed ) ) {
			$has_match = true;
			$matched_parser = $parser;
			break;
		}
	}

	if ( false === $matched_parser )
		return array();

	@list( $newline_regex, $delimiter_regex ) = $parser;

	$last_index = 0;
	$stanzas = array();
	$lines = explode( "\n", make_clickable( $trimmed ) );

	$author = $time = '';
	$data = array();
	$stanza = array();

	foreach ( $lines as $index => $line ) {
		$line = trim( $line );

		if ( empty( $line ) ) {
			if ( ! empty( $author ) ) {
				$stanza[] = array(
					'time' => $time,
					'author' => $author,
					'message' => join( ' ', $data )
				);
			}

			$stanzas[] = $stanza;
			$last_index = $index;
			$stanza = array();
			$author = $time = '';
			$data = array();
			if ( ! empty( $lines[$index + 1] ) && ! preg_match( $delimiter_regex, $lines[$index + 1] ) )
				break;
		}

		$matches = array();
		$matched = preg_match( $newline_regex, $line, $matches );
		$author_match = empty( $matches[2] ) ? $matches[1] : $matches[2];
		// assume username syntax if no whitespace is present
		$no_ws = $matched && ! preg_match( '#\s#', $author_match );
		// allow script-like stanzas
		$has_ws = $matched && preg_match( '#\s#', $author_match ) && empty( $lines[$index + 1] ) && empty( $lines[$index - 1] );
		if ( $matched && ( ! empty( $matches[2] ) || ( $no_ws || $has_ws ) ) ) {
			if ( ! empty( $author ) ) {
				$stanza[] = array(
					'time' => $time,
					'author' => $author,
					'message' => join( ' ', $data )
				);
				$data = array();
			}

			$time = empty( $matches[2] ) ? '' : $matches[1];
			$author = $author_match;
			$data[] = trim( str_replace( $matches[0], '', $line ) );
		} elseif ( preg_match( '#\S#', $line ) ) {
			$data[] = $line;
		}
	}

	if ( ! empty( $author ) ) {
		$stanza[] = array(
			'time' => $time,
			'author' => $author,
			'message' => trim( join( ' ', $data ) )
		);
	}

	if ( ! empty( $stanza ) )
		$stanzas[] = $stanza;

	if ( $remove )
		$content = trim( join( "\n", array_slice( $lines, $last_index ) ) );

	return $stanzas;
}

/**
 * Retrieve structured chat data from the current or passed post
 *
 * @since 3.6.0
 *
 * @param int $id Optional. Post ID
 * @return array
 */
function get_the_chat( $id = 0 ) {
	$post = empty( $id ) ? clone get_post() : get_post( $id );
	if ( empty( $post ) )
		return array();

	$data = get_content_chat( get_paged_content( $post->post_content ) );
	if ( empty( $data ) )
		return array();

	return $data;
}

/**
 * Output HTML for a given chat's structured data. Themes can use this as a
 * template tag in place of the_content() for Chat post format templates.
 *
 * @since 3.6.0
 *
 * @uses get_the_chat()
 *
 * @print HTML
 */
function the_chat() {
	$output = '<dl class="chat-transcript">';

	$stanzas = get_the_chat();

	foreach ( $stanzas as $stanza ) {
		foreach ( $stanza as $row ) {
			$time = '';
			if ( ! empty( $row['time'] ) )
				$time = sprintf( '<time>%s</time>', esc_html( $row['time'] ) );

			$output .= sprintf(
				'<dt class="chat-author chat-author-%1$s vcard">%2$s <cite class="fn">%3$s</cite>: </dt>
					<dd class="chat-text">%4$s</dd>
				',
				esc_attr( strtolower( $row['author'] ) ), // Slug.
				$time,
				esc_html( $row['author'] ),
				esc_html( $row['message'] )
			);
		}
	}

	$output .= '</dl><!-- .chat-transcript -->';

	echo $output;
}

/**
 * Extract a URL from passed content, if possible
 * Checks for a URL on the first line of the content or the first encountered href attribute.
 *
 * @since 3.6.0
 *
 * @param string $content A string which might contain a URL.
 * @param boolean $remove Whether to remove the found URL from the passed content.
 * @return string The found URL.
 */
function get_content_url( &$content, $remove = false ) {
	if ( empty( $content ) )
		return '';

	$matches = array();

	// the content is a URL
	$trimmed = trim( $content );
	if ( 0 === stripos( $trimmed, 'http' ) && ! preg_match( '#\s#', $trimmed ) ) {
		if ( $remove )
			$content = '';

		return $trimmed;
	// the content is HTML so we grab the first href
	} elseif ( preg_match( '/<a\s[^>]*?href=[\'"](.+?)[\'"]/is', $content, $matches ) ) {
		return esc_url_raw( $matches[1] );
	}

	$lines = explode( "\n", $trimmed );
	$line = trim( array_shift( $lines ) );

	// the content is a URL followed by content
	if ( 0 === stripos( $line, 'http' ) ) {
		if ( $remove )
			$content = trim( join( "\n", $lines ) );

		return esc_url_raw( $line );
	}

	return '';
}

/**
 * Attempt to retrieve a URL from a post's content
 *
 * @since 3.6.0
 *
 * @param int $id Optional. Post ID.
 * @return string A URL, if found.
 */
function get_the_url( $id = 0 ) {
	$post = empty( $id ) ? get_post() : get_post( $id );
	if ( empty( $post ) )
		return '';

	if ( in_array( get_post_format( $post->ID ), array( 'link', 'quote' ) ) ) {
		$meta = get_post_format_meta( $post->ID );
		if ( ! empty( $meta['url'] ) )
			return apply_filters( 'get_the_url', esc_url_raw( $meta['url'] ), $post );
	}

	if ( ! empty( $post->post_content ) )
		return apply_filters( 'get_the_url', get_content_url( $post->post_content ), $post );
}

/**
 * Attempt to output a URL from a post's content
 *
 * @since 3.6.0
 *.
 */
function the_url() {
	echo esc_url( get_the_url() );
}
