<?php
/**
 * Press This class and display functionality
 *
 * @package WordPress
 * @subpackage Press_This
 * @since 4.2.0
 */

/**
 * Press This class
 *
 * @since 4.2.0
 */
class WP_Press_This {

	/**
	 * Constructor.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function __construct() {}

	/**
	 * App and site settings data, including i18n strings for the client-side.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @return array Site settings.
	 */
	public function site_settings() {
		$html = '<p class="press-this-suggested-source">' . _x( 'Source:', 'Used in Press This to indicate where the content comes from.' ) .
			' <cite><a href="%1$s">%2$s</a></cite></p>';

		return array(
			// Used to trigger the bookmarklet update notice.
			// Needs to be set here and in get_shortcut_link() in wp-includes/link-template.php.
			'version' => '5',

			/**
			 * Filter whether or not Press This should redirect the user in the parent window upon save.
			 *
			 * @since 4.2.0
			 *
			 * @param bool false Whether to redirect in parent window or not. Default false.
			 */
			'redirInParent' => apply_filters( 'press_this_redirect_in_parent', false ),

			/**
			 * Filter the HTML for the Press This source attribution.
			 *
			 * @since 4.2.0
			 *
			 * @param string $html Default HTML, %1$s is link href, %2$s is link text.
			 */
			'suggestedHTML' => apply_filters( 'press_this_suggested_html', $html ),
		);
	}

	/**
	 * Get the sources images and save them locally, fr posterity, unless we can't.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Optional. Current expected markup for Press This. Default empty.
	 * @return string New markup with old image URLs replaced with the local attachment ones if swapped.
	 */
	public function side_load_images( $post_id, $content = '' ) {
		$new_content = $content;

		preg_match_all( '/<img [^>]+>/', $content, $matches );

		if ( ! empty( $matches ) && current_user_can( 'upload_files' ) ) {
			foreach ( (array) $matches[0] as $key => $image ) {
				preg_match( '/src=["\']{1}([^"\']+)["\']{1}/', stripslashes( $image ), $url_matches );

				if ( empty( $url_matches[1] ) ) {
					continue;
				}

				$image_url = $url_matches[1];

				// Don't try to sideload a file without a file extension, leads to WP upload error.
				if ( ! preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $image_url ) )
					 continue;

				// See if files exist in content - we don't want to upload non-used selected files.
				if ( false !== strpos( $new_content, htmlspecialchars( $image_url ) ) ) {

					// Sideload image, which ives us a new image tag, strip the empty alt that comes with it.
					$upload = str_replace( ' alt=""', '', media_sideload_image( $image_url, $post_id ) );

					// Preserve assigned class, id, width, height and alt attributes.
					if ( preg_match_all( '/(class|width|height|id|alt)=\\\?(\"|\')[^"\']+\\\?(\2)/', $image, $attr_matches )
					     && is_array( $attr_matches[0] )
					) {
						foreach ( $attr_matches[0] as $attr ) {
							$upload = str_replace( '<img', '<img ' . $attr, $upload );
						}
					}

					/*
					 * Replace the POSTED content <img> with correct uploaded ones.
					 * Regex contains fix for Magic Quotes.
					 */
					if ( ! is_wp_error( $upload ) ) {
						$new_content = str_replace( $image, $upload, $new_content );
					}
				}
			}
		}

		// Error handling for media_sideload, send original content back.
		if ( is_wp_error( $new_content ) ) {
			return $content;
		}

		return $new_content;
	}

	/**
	 * AJAX handler for saving the post as draft or published.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function save_post() {
		if ( empty( $_POST['pressthis-nonce'] ) || ! wp_verify_nonce( $_POST['pressthis-nonce'], 'press-this' ) ) {
			wp_send_json_error( array( 'errorMessage' => __( 'Cheatin&#8217; uh?' ) ) );
		}

		if ( empty( $_POST['post_ID'] ) || ! $post_id = (int) $_POST['post_ID'] ) {
			wp_send_json_error( array( 'errorMessage' => __( 'Missing post ID.' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'errorMessage' => __( 'Cheatin&#8217; uh?' ) ) );
		}

		$post = array(
			'ID'            => $post_id,
			'post_title'    => ( ! empty( $_POST['title'] ) ) ? sanitize_text_field( trim( $_POST['title'] ) ) : '',
			'post_content'  => ( ! empty( $_POST['pressthis'] ) ) ? trim( $_POST['pressthis'] ) : '',
			'post_type'     => 'post',
			'post_status'   => 'draft',
			'post_format'   => ( ! empty( $_POST['post_format'] ) ) ? sanitize_text_field( $_POST['post_format'] ) : '',
			'tax_input'     => ( ! empty( $_POST['tax_input'] ) ) ? $_POST['tax_input'] : array(),
			'post_category' => ( ! empty( $_POST['post_category'] ) ) ? $_POST['post_category'] : array(),
		);

		if ( ! empty( $_POST['post_status'] ) && 'publish' === $_POST['post_status'] ) {
			if ( current_user_can( 'publish_posts' ) ) {
				$post['post_status'] = 'publish';
			} else {
				$post['post_status'] = 'pending';
			}
		}

		$new_content = $this->side_load_images( $post_id, $post['post_content'] );

		if ( ! is_wp_error( $new_content ) ) {
			$post['post_content'] = $new_content;
		}

		$updated = wp_update_post( $post, true );

		if ( is_wp_error( $updated ) || intval( $updated ) < 1 ) {
			wp_send_json_error( array( 'errorMessage' => __( 'Error while saving the post. Please try again later.' ) ) );
		} else {
			if ( isset( $post['post_format'] ) ) {
				if ( current_theme_supports( 'post-formats', $post['post_format'] ) ) {
					set_post_format( $post_id, $post['post_format'] );
				} elseif ( $post['post_format'] ) {
					set_post_format( $post_id, false );
				}
			}

			if ( 'publish' === get_post_status( $post_id ) ) {
				/**
				 * Filter the URL to redirect to when Press This saves.
				 *
				 * @since 4.2.0
				 *
				 * @param string $url     Redirect URL. If `$status` is 'publish', this will be the post permalink.
				 *                        Otherwise, the post edit URL will be used.
				 * @param int    $post_id Post ID.
				 * @param string $status  Post status.
				 */
				$redirect = apply_filters( 'press_this_save_redirect', get_post_permalink( $post_id ), $post_id, $post['post_status'] );
			} else {
				/** This filter is documented in wp-admin/includes/class-wp-press-this.php */
				$redirect = apply_filters( 'press_this_save_redirect', get_edit_post_link( $post_id, 'raw' ), $post_id, $post['post_status'] );
			}

			wp_send_json_success( array( 'redirect' => $redirect ) );
		}
	}

	/**
	 * AJAX handler for adding a new category.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function add_category() {
		if ( false === wp_verify_nonce( $_POST['new_cat_nonce'], 'add-category' ) ) {
			wp_send_json_error();
		}

		$taxonomy = get_taxonomy( 'category' );

		if ( ! current_user_can( $taxonomy->cap->edit_terms ) || empty( $_POST['name'] ) ) {
			wp_send_json_error();
		}

		$parent = isset( $_POST['parent'] ) && (int) $_POST['parent'] > 0 ? (int) $_POST['parent'] : 0;
		$names = explode( ',', $_POST['name'] );
		$added = $data = array();

		foreach ( $names as $cat_name ) {
			$cat_name = trim( $cat_name );
			$cat_nicename = sanitize_title( $cat_name );

			if ( empty( $cat_nicename ) ) {
				continue;
			}

			// @todo Find a more performant to check existence, maybe get_term() with a separate parent check.
			if ( ! $cat_id = term_exists( $cat_name, $taxonomy->name, $parent ) ) {
				$cat_id = wp_insert_term( $cat_name, $taxonomy->name, array( 'parent' => $parent ) );
			}

			if ( is_wp_error( $cat_id ) ) {
				continue;
			} elseif ( is_array( $cat_id ) ) {
				$cat_id = $cat_id['term_id'];
			}

			$added[] = $cat_id;
		}

		if ( empty( $added ) ) {
			wp_send_json_error( array( 'errorMessage' => __( 'This category cannot be added. Please change the name and try again.' ) ) );
		}

		foreach ( $added as $new_cat_id ) {
			$new_cat = get_category( $new_cat_id );

			if ( is_wp_error( $new_cat ) ) {
				wp_send_json_error( array( 'errorMessage' => __( 'Error while adding the category. Please try again later.' ) ) );
			}

			$data[] = array(
				'term_id' => $new_cat->term_id,
				'name' => $new_cat->name,
				'parent' => $new_cat->parent,
			);
		}
		wp_send_json_success( $data );
	}

	/**
	 * Downloads the source's HTML via server-side call for the given URL.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $url URL to scan.
	 * @return string Source's HTML sanitized markup
	 */
	public function fetch_source_html( $url ) {
		// Download source page to tmp file.
		$source_tmp_file = ( ! empty( $url ) ) ? download_url( $url ) : '';
		$source_content  = '';

		if ( ! is_wp_error( $source_tmp_file ) && file_exists( $source_tmp_file ) ) {
			// Get the content of the source page from the tmp file..

			$source_content = wp_kses(
				file_get_contents( $source_tmp_file ),
				array(
					'img' => array(
						'src'      => array(),
					),
					'iframe' => array(
						'src'      => array(),
					),
					'link' => array(
						'rel'      => array(),
						'itemprop' => array(),
						'href'     => array(),
					),
					'meta' => array(
						'property' => array(),
						'name'     => array(),
						'content'  => array(),
					)
				)
			);

			// All done with backward compatibility. Let's do some cleanup, for good measure :)
			unlink( $source_tmp_file );

		} else if ( is_wp_error( $source_tmp_file ) ) {
			$source_content = new WP_Error( 'upload-error',  sprintf( __( 'Error: %s' ), sprintf( __( 'Could not download the source URL (native error: %s).' ), $source_tmp_file->get_error_message() ) ) );
		} else if ( ! file_exists( $source_tmp_file ) ) {
			$source_content = new WP_Error( 'no-local-file',  sprintf( __( 'Error: %s' ), __( 'Could not save or locate the temporary download file for the source URL.' ) ) );
		}

		return $source_content;
	}

	/**
	 * Fetches and parses _meta, _img, and _links data from the source.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $url  URL to scan.
	 * @param array  $data Optional. Existing data array if you have one. Default empty array.
	 * @return array New data array.
	 */
	public function source_data_fetch_fallback( $url, $data = array() ) {
		if ( empty( $url ) ) {
			return array();
		}

		// Download source page to tmp file.
		$source_content = $this->fetch_source_html( $url );
		if ( is_wp_error( $source_content ) ) {
			return array( 'errors' => $source_content->get_error_messages() );
		}

		// Fetch and gather <img> data.
		if ( empty( $data['_img'] ) ) {
			$data['_img'] = array();
		}

		if ( preg_match_all( '/<img (.+)[\s]?\/>/', $source_content, $matches ) ) {
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $value ) {
					if ( preg_match( '/<img[^>]+src="([^"]+)"[^>]+\/>/', $value, $new_matches ) ) {
						if ( ! in_array( $new_matches[1], $data['_img'] ) ) {
							$data['_img'][] = $new_matches[1];
						}
					}
				}
			}
		}

		// Fetch and gather <iframe> data.
		if ( empty( $data['_embed'] ) ) {
			$data['_embed'] = array();
		}

		if ( preg_match_all( '/<iframe (.+)[\s][^>]*>/', $source_content, $matches ) ) {
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $value ) {
					if ( preg_match( '/<iframe[^>]+src=(\'|")([^"]+)(\'|")/', $value, $new_matches ) ) {
						if ( ! in_array( $new_matches[2], $data['_embed'] ) ) {
							if ( preg_match( '/\/\/www\.youtube\.com\/embed\/([^\?]+)\?.+$/', $new_matches[2], $src_matches ) ) {
								$data['_embed'][] = 'https://www.youtube.com/watch?v=' . $src_matches[1];
							} else if ( preg_match( '/\/\/player\.vimeo\.com\/video\/([\d]+)([\?\/]{1}.*)?$/', $new_matches[2], $src_matches ) ) {
								$data['_embed'][] = 'https://vimeo.com/' . (int) $src_matches[1];
							} else if ( preg_match( '/\/\/vine\.co\/v\/([^\/]+)\/embed/', $new_matches[2], $src_matches ) ) {
								$data['_embed'][] = 'https://vine.co/v/' . $src_matches[1];
							}
						}
					}
				}
			}
		}

		// Fetch and gather <meta> data.
		if ( empty( $data['_meta'] ) ) {
			$data['_meta'] = array();
		}

		if ( preg_match_all( '/<meta ([^>]+)[\s]?\/?>/', $source_content, $matches ) ) {
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $key => $value ) {
					if ( preg_match( '/<meta[^>]+(property|name)="(.+)"[^>]+content="(.+)"/', $value, $new_matches ) ) {
						if ( empty( $data['_meta'][ $new_matches[2] ] ) ) {
							if ( preg_match( '/:?(title|description|keywords)$/', $new_matches[2] ) ) {
								$data['_meta'][ $new_matches[2] ] = str_replace( '&#039;', "'", str_replace( '&#034;', '', html_entity_decode( $new_matches[3] ) ) );
							} else {
								$data['_meta'][ $new_matches[2] ] = $new_matches[3];
								if ( 'og:url' == $new_matches[2] ) {
									if ( false !== strpos( $new_matches[3], '//www.youtube.com/watch?' )
									     || false !== strpos( $new_matches[3], '//www.dailymotion.com/video/' )
									     || preg_match( '/\/\/vimeo\.com\/[\d]+$/', $new_matches[3] )
									     || preg_match( '/\/\/soundcloud\.com\/.+$/', $new_matches[3] )
									     || preg_match( '/\/\/twitter\.com\/[^\/]+\/status\/[\d]+$/', $new_matches[3] )
									     || preg_match( '/\/\/vine\.co\/v\/[^\/]+/', $new_matches[3] ) ) {
										if ( ! in_array( $new_matches[3], $data['_embed'] ) ) {
											$data['_embed'][] = $new_matches[3];
										}
									}
								} else if ( 'og:video' == $new_matches[2] || 'og:video:secure_url' == $new_matches[2] ) {
									if ( preg_match( '/\/\/www\.youtube\.com\/v\/([^\?]+)/', $new_matches[3], $src_matches ) ) {
										if ( ! in_array( 'https://www.youtube.com/watch?v=' . $src_matches[1], $data['_embed'] ) ) {
											$data['_embed'][] = 'https://www.youtube.com/watch?v=' . $src_matches[1];
										}
									} else if ( preg_match( '/\/\/vimeo.com\/moogaloop\.swf\?clip_id=([\d]+)$/', $new_matches[3], $src_matches ) ) {
										if ( ! in_array( 'https://vimeo.com/' . $src_matches[1], $data['_embed'] ) ) {
											$data['_embed'][] = 'https://vimeo.com/' . $src_matches[1];
										}
									}
								} else if ( 'og:image' == $new_matches[2] || 'og:image:secure_url' == $new_matches[2] ) {
									if ( ! in_array( $new_matches[3], $data['_img'] ) ) {
										$data['_img'][] = $new_matches[3];
									}
								}
							}
						}
					}
				}
			}
		}

		// Fetch and gather <link> data
		if ( empty( $data['_links'] ) ) {
			$data['_links'] = array();
		}

		if ( preg_match_all( '/<link ([^>]+)[\s]?\/>/', $source_content, $matches ) ) {
			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $key => $value ) {
					if ( preg_match( '/<link[^>]+(rel|itemprop)="([^"]+)"[^>]+href="([^"]+)"[^>]+\/>/', $value, $new_matches ) ) {
						if ( 'alternate' == $new_matches[2] || 'thumbnailUrl' == $new_matches[2] || 'url' == $new_matches[2] ) {
							if ( empty( $data['_links'][ $new_matches[2] ] ) ) {
								$data['_links'][ $new_matches[2] ] = $new_matches[3];
							}
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Handles backward-compat with the legacy version of Press This by supporting its query string params.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @return array
	 */
	public function merge_or_fetch_data() {
		// Merge $_POST and $_GET, as appropriate ($_POST > $_GET), to remain backward compatible.
		$data = array_merge_recursive( $_POST, $_GET );

		// Get the legacy QS params, or equiv POST data
		$data['u'] = ( ! empty( $data['u'] ) && preg_match( '/^https?:/', $data['u'] ) ) ? $data['u'] : '';
		$data['s'] = ( ! empty( $data['s'] ) ) ? $data['s'] : '';
		$data['t'] = ( ! empty( $data['t'] ) ) ? $data['t'] : '';

		/**
		 * Filter whether to enable in-source media discovery in Press This.
		 *
		 * @since 4.2.0
		 *
		 * @param bool $enable Whether to enable media discovery.
		 */
		if ( apply_filters( 'enable_press_this_media_discovery', true ) ) {
			/*
			 * If no _meta (a new thing) was passed via $_POST, fetch data from source as fallback,
			 * makes PT fully backward compatible
			 */
			if ( empty( $data['_meta'] ) && ! empty( $data['u'] ) ) {
				$data = $this->source_data_fetch_fallback( $data['u'], $data );
			}
		} else {
			if ( ! empty( $data['_img'] ) ) {
				$data['_img'] = array();
			}
			if ( ! empty( $data['_embed'] ) ) {
				$data['_embed'] = array();
			}
			if ( ! empty( $data['_meta'] ) ) {
				$data['_meta'] = array();
			}
		}

		/**
		 * Filter the Press This data array.
		 *
		 * @since 4.2.0
		 *
		 * @param array $data Press This Data array.
		 */
		return apply_filters( 'press_this_data', $data );
	}

	/**
	 * Adds another stylesheet inside TinyMCE.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param string $styles URL to editor stylesheet.
	 * @return string Possibly modified stylesheets list.
	 */
	public function add_editor_style( $styles ) {
		if ( ! empty( $styles ) ) {
			$styles .= ',';
		}

		$press_this = admin_url( 'css/press-this-editor.css' );
		if ( is_rtl() ) {
			$press_this = str_replace( '.css', '-rtl.css', $press_this );
		}

		return $styles . $press_this;
	}

	/**
	 * Outputs the post format selection HTML.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param WP_Post $post Post object.
	 */
	public function post_formats_html( $post ) {
		if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) ) {
			$post_formats = get_theme_support( 'post-formats' );

			if ( is_array( $post_formats[0] ) ) {
				$post_format = get_post_format( $post->ID );

				if ( ! $post_format ) {
					$post_format = '0';
				}

				// Add in the current one if it isn't there yet, in case the current theme doesn't support it.
				if ( $post_format && ! in_array( $post_format, $post_formats[0] ) ) {
					$post_formats[0][] = $post_format;
				}

				?>
				<div id="post-formats-select">
				<fieldset><legend class="screen-reader-text"><?php _e( 'Post formats' ); ?></legend>
					<input type="radio" name="post_format" class="post-format" id="post-format-0" value="0" <?php checked( $post_format, '0' ); ?> />
					<label for="post-format-0" class="post-format-icon post-format-standard"><?php echo get_post_format_string( 'standard' ); ?></label>
					<?php

					foreach ( $post_formats[0] as $format ) {
						$attr_format = esc_attr( $format );
						?>
						<br />
						<input type="radio" name="post_format" class="post-format" id="post-format-<?php echo $attr_format; ?>" value="<?php echo $attr_format; ?>" <?php checked( $post_format, $format ); ?> />
						<label for="post-format-<?php echo $attr_format ?>" class="post-format-icon post-format-<?php echo $attr_format; ?>"><?php echo esc_html( get_post_format_string( $format ) ); ?></label>
						<?php
					 }
					 ?>
				</fieldset>
				</div>
				<?php
			}
		}
	}

	/**
	 * Outputs the categories HTML.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param WP_Post $post Post object.
	 */
	public function categories_html( $post ) {
		$taxonomy = get_taxonomy( 'category' );

		if ( current_user_can( $taxonomy->cap->edit_terms ) ) {
			?>
			<button type="button" class="add-cat-toggle button-subtle" aria-expanded="false">
				<span class="dashicons dashicons-plus"></span><span class="screen-reader-text"><?php _e( 'Toggle add category' ); ?></span>
			</button>
			<div class="add-category is-hidden">
				<label class="screen-reader-text" for="new-category"><?php echo $taxonomy->labels->add_new_item; ?></label>
				<input type="text" id="new-category" class="add-category-name" placeholder="<?php echo esc_attr( $taxonomy->labels->new_item_name ); ?>" value="" aria-required="true">
				<label class="screen-reader-text" for="new-category-parent"><?php echo $taxonomy->labels->parent_item_colon; ?></label>
				<div class="postform-wrapper">
					<?php
					wp_dropdown_categories( array(
						'taxonomy'         => 'category',
						'hide_empty'       => 0,
						'name'             => 'new-category-parent',
						'orderby'          => 'name',
						'hierarchical'     => 1,
						'show_option_none' => '&mdash; ' . $taxonomy->labels->parent_item . ' &mdash;'
					) );
					?>
				</div>
				<button type="button" class="button add-cat-submit"><?php _e( 'Add' ); ?></button>
			</div>
		<?php } ?>
		<div class="categories-search-wrapper">
			<input id="categories-search" type="search" class="categories-search" placeholder="<?php esc_attr_e( 'Search categories by name' ) ?>">
			<label for="categories-search">
				<span class="dashicons dashicons-search"></span><span class="screen-reader-text"><?php _e( 'Search categories' ); ?></span>
			</label>
		</div>
		<ul class="categories-select" aria-label="<?php esc_attr_e( 'Categories' ); ?>">
			<?php wp_terms_checklist( $post->ID, array( 'taxonomy' => 'category' ) ); ?>
		</ul>
		<?php
	}

	/**
	 * Outputs the tags HTML.
	 *
	 * @since 4.2.0
	 * @access public
	 *
	 * @param WP_Post $post Post object.
	 */
	public function tags_html( $post ) {
		$taxonomy              = get_taxonomy( 'post_tag' );
		$user_can_assign_terms = current_user_can( $taxonomy->cap->assign_terms );
		$esc_tags              = get_terms_to_edit( $post->ID, 'post_tag' );

		if ( ! $esc_tags || is_wp_error( $esc_tags ) ) {
			$esc_tags = '';
		}
		?>
		<div class="tagsdiv" id="post_tag">
			<div class="jaxtag">
			<input type="hidden" name="tax_input[post_tag]" class="the-tags" value="<?php echo $esc_tags; // escaped in get_terms_to_edit() ?>">

		 	<?php
			if ( $user_can_assign_terms ) {
				?>
				<div class="ajaxtag hide-if-no-js">
					<label class="screen-reader-text" for="new-tag-post_tag"><?php _e( 'Tags' ); ?></label>
					<div class="taghint"><?php echo $taxonomy->labels->add_new_item; ?></div>
					<p>
						<input type="text" id="new-tag-post_tag" name="newtag[post_tag]" class="newtag form-input-tip" size="16" autocomplete="off" value="" />
						<button type="button" class="button tagadd"><?php _e( 'Add' ); ?></button>
					</p>
				</div>
				<p class="howto">
					<?php echo $taxonomy->labels->separate_items_with_commas; ?>
				</p>
			<?php } ?>
			</div>
			<div class="tagchecklist"></div>
		</div>
		<?php
		if ( $user_can_assign_terms ) {
			?>
			<button type="button" class="button-reset button-link tagcloud-link" id="link-post_tag"><?php echo $taxonomy->labels->choose_from_most_used; ?></button>
			<?php
		}
	}

	/**
	 * Serves the app's base HTML, which in turns calls the load script.
	 *
	 * @since 4.2.0
	 * @access public
	 */
	public function html() {
		global $wp_locale, $wp_version;

		// Get data, new (POST) and old (GET).
		$data = $this->merge_or_fetch_data();

		// Get site settings array/data.
		$site_settings = $this->site_settings();

		// Set the passed data.
		$data['_version'] = $site_settings['version'];

		// Add press-this-editor.css and remove theme's editor-style.css, if any.
		remove_editor_styles();

		add_filter( 'mce_css', array( $this, 'add_editor_style' ) );

		if ( ! empty( $GLOBALS['is_IE'] ) ) {
			@header( 'X-UA-Compatible: IE=edge' );
		}

		@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

?>
<!DOCTYPE html>
<!--[if IE 7]>         <html class="lt-ie9 lt-ie8" <?php language_attributes(); ?>> <![endif]-->
<!--[if IE 8]>         <html class="lt-ie9" <?php language_attributes(); ?>> <![endif]-->
<!--[if gt IE 8]><!--> <html <?php language_attributes(); ?>> <!--<![endif]-->
<head>
	<meta http-equiv="Content-Type" content="<?php esc_attr( bloginfo( 'html_type' ) ); ?>; charset=<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>" />
	<meta name="viewport" content="width=device-width">
	<title><?php esc_html_e( 'Press This!' ) ?></title>

	<script>
		window.wpPressThisData   = <?php echo wp_json_encode( $data ) ?>;
		window.wpPressThisConfig = <?php echo wp_json_encode( $site_settings ) ?>;
	</script>

	<script type="text/javascript">
		var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php', 'relative' ) ); ?>',
			pagenow = 'press-this',
			typenow = 'post',
			adminpage = 'press-this-php',
			thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
			decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>',
			isRtl = <?php echo (int) is_rtl(); ?>;
	</script>

	<?php
		/*
		 * $post->ID is needed for the embed shortcode so we can show oEmbed previews in the editor.
		 * Maybe find a way without it.
		 */
		$post = get_default_post_to_edit( 'post', true );
		$post_ID = (int) $post->ID;

		wp_enqueue_style( 'press-this' );
		wp_enqueue_script( 'press-this' );
		wp_enqueue_script( 'json2' );
		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_script( 'editor' );

		$supports_formats = false;
		$post_format      = 0;

		if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) ) {
			$supports_formats = true;

			if ( ! ( $post_format = get_post_format( $post->ID ) ) ) {
				$post_format = 0;
			}
		}

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_enqueue_scripts', 'press-this.php' );

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_print_styles-press-this.php' );

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_print_styles' );

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_print_scripts-press-this.php' );

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_print_scripts' );

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_head-press-this.php' );

		/** This action is documented in wp-admin/admin-header.php */
		do_action( 'admin_head' );
	?>
</head>
<?php
$admin_body_class  = 'press-this';
$admin_body_class .= ( is_rtl() ) ? ' rtl' : '';
$admin_body_class .= ' branch-' . str_replace( array( '.', ',' ), '-', floatval( $wp_version ) );
$admin_body_class .= ' version-' . str_replace( '.', '-', preg_replace( '/^([.0-9]+).*/', '$1', $wp_version ) );
$admin_body_class .= ' admin-color-' . sanitize_html_class( get_user_option( 'admin_color' ), 'fresh' );
$admin_body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

/** This filter is documented in wp-admin/admin-header.php */
$admin_body_classes = apply_filters( 'admin_body_class', '' );
?>
<body class="wp-admin wp-core-ui <?php echo $admin_body_classes . ' ' . $admin_body_class; ?>">
	<div id="adminbar" class="adminbar">
		<h1 id="current-site" class="current-site">
			<span class="dashicons dashicons-wordpress"></span>
			<span><?php bloginfo( 'name' ); ?></span>
		</h1>
		<button type="button" class="options-open button-subtle">
			<span class="dashicons dashicons-tag"></span><span class="screen-reader-text"><?php _e( 'Show post options' ); ?></span>
		</button>
		<button type="button" class="options-close button-subtle is-hidden"><?php _e( 'Done' ); ?></button>
	</div>

	<div id="scanbar" class="scan">
		<form method="GET">
			<label for="url-scan" class="screen-reader-text"><?php _e( 'Scan site for content' ); ?></label>
			<input type="url" name="u" id="url-scan" class="scan-url" value="" placeholder="<?php esc_attr_e( 'Enter a URL to scan' ) ?>" />
			<input type="submit" name="url-scan-submit" id="url-scan-submit" class="scan-submit" value="<?php esc_attr_e( 'Scan' ) ?>" />
		</form>
	</div>

	<form id="pressthis-form" name="pressthis-form" method="POST" autocomplete="off">
		<input type="hidden" name="post_ID" id="post_ID" value="<?php echo esc_attr( $post_ID ); ?>" />
		<input type="hidden" name="action" value="press-this-save-post" />
		<input type="hidden" name="post_status" id="post_status" value="draft" />
		<?php
		wp_nonce_field( 'press-this', 'pressthis-nonce', false );
		wp_nonce_field( 'add-category', '_ajax_nonce-add-category', false );
		?>
		<input type="hidden" name="title" id="title-field" value="" />

	<div class="wrapper">
		<div class="editor-wrapper">
			<div class="alerts">
				<p class="alert is-notice is-hidden should-upgrade-bookmarklet">
					<?php printf( __( 'You should upgrade <a href="%s" target="_blank">your bookmarklet</a> to the latest version!' ), admin_url( 'tools.php' ) ); ?>
				</p>
			</div>

			<div id='app-container' class="editor">
				<span id="title-container-label" class="post-title-placeholder" aria-hidden="true"><?php _e( 'Post title' ); ?></span>
				<h2 id="title-container" class="post-title" contenteditable="true" spellcheck="true" aria-label="<?php esc_attr_e( 'Post title' ); ?>" tabindex="0"></h2>
				<div id='featured-media-container' class="featured-container no-media">
					<div id='all-media-widget' class="all-media">
						<div id='all-media-container'></div>
					</div>
				</div>

				<?php
				wp_editor( '', 'pressthis', array(
					'drag_drop_upload' => true,
					'editor_height'    => 600,
					'media_buttons'    => false,
					'teeny'            => true,
					'tinymce'          => array(
						'resize'                => false,
						'wordpress_adv_hidden'  => false,
						'add_unload_trigger'    => false,
						'statusbar'             => false,
						'autoresize_min_height' => 600,
						'wp_autoresize_on'      => true,
						'plugins'               => 'lists,media,paste,tabfocus,fullscreen,wordpress,wpautoresize,wpeditimage,wpgallery,wplink,wpview',
						'toolbar1'              => 'bold,italic,bullist,numlist,blockquote,link,unlink',
						'toolbar2'              => 'undo,redo',
					),
					'quicktags'        => false,
				) );

				?>
			</div>
		</div>

		<div class="options-panel is-off-screen is-hidden">
			<div class="post-options">

				<?php if ( $supports_formats ) : ?>
					<button type="button" class="button-reset post-option">
						<span class="dashicons dashicons-admin-post"></span>
						<span class="post-option-title"><?php _e( 'Format' ); ?></span>
						<span class="post-option-contents" id="post-option-post-format"><?php echo esc_html( get_post_format_string( $post_format ) ); ?></span>
						<span class="dashicons post-option-forward"></span>
					</button>
				<?php endif; ?>

				<button type="button" class="button-reset post-option">
					<span class="dashicons dashicons-category"></span>
					<span class="post-option-title"><?php _e( 'Categories' ); ?></span>
					<span class="post-option-contents" id="post-option-category"></span>
					<span class="dashicons post-option-forward"></span>
				</button>

				<button type="button" class="button-reset post-option">
					<span class="dashicons dashicons-tag"></span>
					<span class="post-option-title"><?php _e( 'Tags' ); ?></span>
					<span class="post-option-contents" id="post-option-tags"></span>
					<span class="dashicons post-option-forward"></span>
				</button>
			</div>

			<?php if ( $supports_formats ) : ?>
				<div class="setting-modal is-off-screen is-hidden">
					<button type="button" class="button-reset modal-close">
						<span class="dashicons post-option-back"></span>
						<span class="setting-title" aria-hidden="true"><?php _e( 'Post format' ); ?></span>
						<span class="screen-reader-text"><?php _e( 'Back to post options' ) ?></span>
					</button>
					<?php $this->post_formats_html( $post ); ?>
				</div>
			<?php endif; ?>

			<div class="setting-modal is-off-screen is-hidden">
				<button type="button" class="button-reset modal-close">
					<span class="dashicons post-option-back"></span>
					<span class="setting-title" aria-hidden="true"><?php _e( 'Categories' ); ?></span>
					<span class="screen-reader-text"><?php _e( 'Back to post options' ) ?></span>
				</button>
				<?php $this->categories_html( $post ); ?>
			</div>

			<div class="setting-modal tags is-off-screen is-hidden">
				<button type="button" class="button-reset modal-close">
					<span class="dashicons post-option-back"></span>
					<span class="setting-title" aria-hidden="true"><?php _e( 'Tags' ); ?></span>
					<span class="screen-reader-text"><?php _e( 'Back to post options' ) ?></span>
				</button>
				<?php $this->tags_html( $post ); ?>
			</div>
		</div><!-- .options-panel -->
	</div><!-- .wrapper -->

	<div class="press-this-actions">
		<div class="pressthis-media-buttons">
			<button type="button" class="insert-media button-subtle" data-editor="pressthis">
				<span class="dashicons dashicons-admin-media"></span>
				<span class="screen-reader-text"><?php _e( 'Add Media' ); ?></span>
			</button>
		</div>
		<div class="post-actions">
			<button type="button" class="button-subtle" id="draft-field"><?php _e( 'Save Draft' ); ?></button>
			<button type="button" class="button-primary" id="publish-field"><?php _e( 'Publish' ); ?></button>
		</div>
	</div>
	</form>

	<?php
	/** This action is documented in wp-admin/admin-footer.php */
	do_action( 'admin_footer' );

	/** This action is documented in wp-admin/admin-footer.php */
	do_action( 'admin_print_footer_scripts' );

	/** This action is documented in wp-admin/admin-footer.php */
	do_action( 'admin_footer-press-this.php' );
	?>
</body>
</html>
<?php
		die();
	}
}

$GLOBALS['wp_press_this'] = new WP_Press_This;
