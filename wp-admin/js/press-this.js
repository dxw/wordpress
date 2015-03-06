/**
 * PressThis App
 *
 */
( function( $, window ) {
	var PressThis = function() {
		var editor,
			saveAlert             = false,
			textarea              = document.createElement( 'textarea' ),
			sidebarIsOpen         = false,
			siteConfig            = window.wpPressThisConfig || {},
			data                  = window.wpPressThisData || {},
			smallestWidth         = 128,
			interestingImages	  = getInterestingImages( data ) || [],
			interestingEmbeds	  = getInterestingEmbeds( data ) || [],
			hasEmptyTitleStr      = false,
			suggestedTitleStr     = getSuggestedTitle( data ),
			suggestedContentStr   = getSuggestedContent( data ),
			hasSetFocus           = false,
			catsCache             = [],
			isOffScreen           = 'is-off-screen',
			isHidden              = 'is-hidden',
			offscreenHidden       = isOffScreen + ' ' + isHidden,
			transitionEndEvent    = ( function() {
				var style = document.documentElement.style;

				if ( typeof style.transition !== 'undefined' ) {
					return 'transitionend';
				}

				if ( typeof style.WebkitTransition !== 'undefined' ) {
					return 'webkitTransitionEnd';
				}

				return false;
			}() );

		/* ***************************************************************
		 * HELPER FUNCTIONS
		 *************************************************************** */

		/**
		 * Emulates our PHP __() gettext function, powered by the strings exported in pressThisL10n.
		 *
		 * @param key string Key of the string to be translated, as found in pressThisL10n.
		 * @returns string Original or translated string, or empty string if no key.
		 */
		function __( key ) {
			if ( key && window.pressThisL10n ) {
				return window.pressThisL10n[key] || key;
			}

			return key || '';
		}

		/**
		 * Strips HTML tags
		 *
		 * @param string string Text to have the HTML tags striped out of.
		 * @returns string Stripped text.
		 */
		function stripTags( string ) {
			string = string || '';

			return string
				.replace( /<!--[\s\S]*?(-->|$)/g, '' )
				.replace( /<(script|style)[^>]*>[\s\S]*?(<\/\1>|$)/ig, '' )
				.replace( /<\/?[a-z][\s\S]*?(>|$)/ig, '' );
		}

		/**
		 * Strip HTML tags and convert HTML entities.
		 *
		 * @param text string Text.
		 * @returns string Sanitized text.
		 */
		function sanitizeText( text ) {
			text = stripTags( text );
			textarea.innerHTML = text;

			return stripTags( textarea.value );
		}

		/**
		 * Allow only HTTP or protocol relative URLs.
		 *
		 * @param url string The URL.
		 * @returns string Processed URL.
		 */
		function checkUrl( url ) {
			url = $.trim( url || '' );

			if ( /^(?:https?:)?\/\//.test( url ) ) {
				url = stripTags( url );
				return url.replace( /["\\]+/g, '' );
			}

			return '';
		}

		/**
		 * Gets the source page's canonical link, based on passed location and meta data.
		 *
		 * @returns string Discovered canonical URL, or empty
		 */
		function getCanonicalLink() {
			var link = '';

			if ( data._links && data._links.canonical ) {
				link = data._links.canonical;
			}

			if ( ! link && data.u ) {
				link = data.u;
			}

			if ( ! link && data._meta ) {
				if ( data._meta['twitter:url'] ) {
					link = data._meta['twitter:url'];
				} else if ( data._meta['og:url'] ) {
					link = data._meta['og:url'];
				}
			}

			return checkUrl( decodeURI( link ) );
		}

		/**
		 * Gets the source page's site name, based on passed meta data.
		 *
		 * @returns string Discovered site name, or empty
		 */
		function getSourceSiteName() {
			var name = '';

			if ( data._meta ) {
				if ( data._meta['og:site_name'] ) {
					name = data._meta['og:site_name'];
				} else if ( data._meta['application-name'] ) {
					name = data._meta['application-name'];
				}
			}

			return sanitizeText( name );
		}

		/**
		 * Gets the source page's title, based on passed title and meta data.
		 *
		 * @returns string Discovered page title, or empty
		 */
		function getSuggestedTitle() {
			var title = '';

			if ( data.t ) {
				title = data.t;
			}

			if ( ! title && data._meta ) {
				if ( data._meta['twitter:title'] ) {
					title = data._meta['twitter:title'];
				} else if ( data._meta['og:title'] ) {
					title = data._meta['og:title'];
				} else if ( data._meta.title ) {
					title = data._meta.title;
				}
			}

			if ( ! title ) {
				title = __( 'newPost' );
				hasEmptyTitleStr = true;
			}

			return sanitizeText( title );
		}

		/**
		 * Gets the source page's suggested content, based on passed data (description, selection, etc).
		 * Features a blockquoted excerpt, as well as content attribution, if any.
		 *
		 * @returns string Discovered content, or empty
		 */
		function getSuggestedContent() {
			var content  = '',
				text     = '',
				title    = getSuggestedTitle(),
				url      = getCanonicalLink(),
				siteName = getSourceSiteName();

			if ( data.s ) {
				text = data.s;
			} else if ( data._meta ) {
				if ( data._meta['twitter:description'] ) {
					text = data._meta['twitter:description'];
				} else if ( data._meta['og:description'] ) {
					text = data._meta['og:description'];
				} else if ( data._meta.description ) {
					text = data._meta.description;
				}
			}

			if ( text && siteConfig.html.quote ) {
				// Wrap suggested content in specified HTML.
				content = siteConfig.html.quote.replace( /%1\$s/g, sanitizeText( text ) );
			}

			// Add a source attribution if there is one available.
			if ( url && siteConfig.html.link && ( ( title && __( 'newPost' ) !== title ) || siteName ) ) {
				content += siteConfig.html.link.replace( /%1\$s/g, encodeURI( url ) ).replace( /%2\$s/g, ( title || siteName ) );
			}

			return content || '';
		}

		/**
		 * Get a list of valid embeds from what was passed via WpPressThis_App.data._embed on page load.
		 *
		 * @returns array
		 */
		function getInterestingEmbeds() {
			var embeds             = data._embed || [],
				interestingEmbeds  = [],
				alreadySelected    = [];

			if ( embeds.length ) {
				$.each( embeds, function ( i, src ) {
					if ( ! src ) {
						// Skip: no src value
						return;
					}

					var schemelessSrc = src.replace( /^https?:/, '' );

					if ( $.inArray( schemelessSrc, alreadySelected ) > -1 ) {
						// Skip: already shown
						return;
					}

					interestingEmbeds.push( src );
					alreadySelected.push( schemelessSrc );
				} );
			}

			return interestingEmbeds;
		}

		/**
		 * Get a list of valid images from what was passed via WpPressThis_App.data._img and WpPressThis_App.data._meta on page load.
		 *
		 * @returns array
		 */
		function getInterestingImages( data ) {
			var imgs             = data._img || [],
				interestingImgs  = [],
				alreadySelected  = [];

			if ( imgs.length ) {
				$.each( imgs, function ( i, src ) {
					src = src.replace( /http:\/\/[\d]+\.gravatar\.com\//, 'https://secure.gravatar.com/' );
					src = checkUrl( src );

					if ( ! src ) {
						// Skip: no src value
						return;
					}

					var schemelessSrc = src.replace( /^https?:/, '' );

					if ( Array.prototype.indexOf && alreadySelected.indexOf( schemelessSrc ) > -1 ) {
						// Skip: already shown
						return;
					} else if ( src.indexOf( 'avatar' ) > -1 && interestingImgs.length >= 15 ) {
						// Skip:  some type of avatar and we've already gathered more than 23 diff images to show
						return;
					}

					interestingImgs.push( src );
					alreadySelected.push( schemelessSrc );
				} );
			}

			return interestingImgs;
		}

		/**
		 * Show UX spinner
		 */
		function showSpinner() {
			$( '#spinner' ).addClass( 'show' );
			$( '.post-actions button' ).each( function() {
				$( this ).attr( 'disabled', 'disabled' );
			} );
		}

		/**
		 * Hide UX spinner
		 */
		function hideSpinner() {
			$( '#spinner' ).removeClass( 'show' );
			$( '.post-actions button' ).each( function() {
				$( this ).removeAttr( 'disabled' );
			} );
		}

		/**
		 * Submit the post form via AJAX, and redirect to the proper screen if published vs saved as a draft.
		 *
		 * @param action string publish|draft
		 */
		function submitPost( action ) {
			saveAlert = false;
			showSpinner();

			var $form = $( '#pressthis-form' );

			if ( 'publish' === action ) {
				$( '#post_status' ).val( 'publish' );
			}

			editor && editor.save();

			$( '#title-field' ).val( sanitizeText( $( '#title-container' ).text() ) );

			// Make sure to flush out the tags with tagBox before saving
			if ( window.tagBox ) {
				$( 'div.tagsdiv' ).each( function() {
					window.tagBox.flushTags( this, false, 1 );
				} );
			}

			var data = $form.serialize();

			$.ajax( {
				type: 'post',
				url: window.ajaxurl,
				data: data,
				success: function( response ) {
					if ( ! response.success ) {
						renderError( response.data.errorMessage );
						hideSpinner();
					} else if ( response.data.redirect ) {
						if ( window.opener && siteConfig.redirInParent ) {
							try {
								window.opener.location.href = response.data.redirect;
							} catch( er ) {}

							window.self.close();
						} else {
							window.location.href = response.data.redirect;
						}
					}
				}
			} );
		}

		/**
		 * Inserts the media a user has selected from the presented list inside the editor, as an image or embed, based on type
		 *
		 * @param type string img|embed
		 * @param src string Source URL
		 * @param link string Optional destination link, for images (defaults to src)
		 */
		function insertSelectedMedia( type, src, link ) {
			var newContent = '';

			if ( ! editor ) {
				return;
			}

			src = checkUrl( src );
			link = checkUrl( link );

			if ( 'img' === type ) {
				if ( ! link ) {
					link = src;
				}

				newContent = '<a href="' + link + '"><img class="alignnone size-full" src="' + src + '" /></a>\n';
			} else {
				newContent = '[embed]' + src + '[/embed]\n';
			}

			if ( ! hasSetFocus ) {
				editor.focus();
			}

			editor.execCommand( 'mceInsertContent', false, newContent );
			hasSetFocus = true;
		}

		/**
		 * Save a new user-generated category via AJAX
		 */
		function saveNewCategory() {
			var data,
				name = $( '#new-category' ).val();

			if ( ! name ) {
				return;
			}

			data = {
				action: 'press-this-add-category',
				post_id: $( '#post_ID' ).val() || 0,
				name: name,
				new_cat_nonce: $( '#_ajax_nonce-add-category' ).val() || '',
				parent: $( '#new-category-parent' ).val() || 0
			};

			$.post( window.ajaxurl, data, function( response ) {
				if ( ! response.success ) {
					renderError( response.data.errorMessage );
				} else {
					// TODO: change if/when the html changes.
					var $parent, $ul,
						$wrap = $( 'ul.categories-select' );

					$.each( response.data, function( i, newCat ) {
						var $node = $( '<li>' ).attr( 'id', 'category-' + newCat.term_id )
							.append( $( '<label class="selectit">' ).text( newCat.name )
								.append( $( '<input type="checkbox" name="post_category[]" checked>' ).attr( 'value', newCat.term_id ) ) );

						if ( newCat.parent ) {
							if ( ! $ul || ! $ul.length ) {
								$parent = $wrap.find( '#category-' + newCat.parent );
								$ul = $parent.find( 'ul.children:first' );

								if ( ! $ul.length ) {
									$ul = $( '<ul class="children">' ).appendTo( $parent );
								}
							}

							$ul.append( $node );
							// TODO: set focus on
						} else {
							$wrap.prepend( $node );
						}
					} );

					refreshCatsCache();
				}
			} );
		}

		/* ***************************************************************
		 * RENDERING FUNCTIONS
		 *************************************************************** */

		/**
		 * Hide the form letting users enter a URL to be scanned, if a URL was already passed.
		 */
		function renderToolsVisibility() {
			if ( data.u && data.u.match( /^https?:/ ) ) {
				$( '#scanbar' ).hide();
			}
		}

		/**
		 * Render error notice
		 *
		 * @param msg string Notice/error message
		 * @param error string error|notice CSS class for display
		 */
		function renderNotice( msg, error ) {
			var $alerts = $( '.editor-wrapper div.alerts' ),
				className = error ? 'is-error' : 'is-notice';

			$alerts.append( $( '<p class="alert ' + className + '">' ).text( msg ) );
		}

		/**
		 * Render error notice
		 *
		 * @param msg string Error message
		 */
		function renderError( msg ) {
			renderNotice( msg, true );
		}

		/**
		 * Render notices on page load, if any already
		 */
		function renderStartupNotices() {
			// Render errors sent in the data, if any
			if ( data.errors ) {
				$.each( data.errors, function( i, msg ) {
					renderError( msg );
				} );
			}

			// Prompt user to upgrade their bookmarklet if there is a version mismatch.
			if ( data.v && data._version && ( data.v + '' ) !== ( data._version + '' ) ) {
				$( '.should-upgrade-bookmarklet' ).removeClass( 'is-hidden' );
			}
		}

		/**
		 * Render the suggested title, if any
		 */
		function renderSuggestedTitle() {
			var suggestedTitle = suggestedTitleStr || '',
				$title = $( '#title-container' );

			if ( ! hasEmptyTitleStr ) {
				$( '#title-field' ).val( suggestedTitle );
				$title.text( suggestedTitle );
				$( '.post-title-placeholder' ).addClass( 'is-hidden' );
			}

			$title.on( 'keyup', function() {
				saveAlert = true;
			}).on( 'paste', function() {
				saveAlert = true;

				setTimeout( function() {
					$title.text( $title.text() );
				}, 100 );
			} );

		}

		/**
		 * Render the suggested content, if any
		 */
		function renderSuggestedContent() {
			if ( ! suggestedContentStr ) {
				return;
			}

			if ( ! editor ) {
				editor = window.tinymce.get( 'pressthis' );
			}

			if ( editor ) {
				editor.setContent( suggestedContentStr );
				editor.on( 'focus', function() {
					hasSetFocus = true;
				} );
			}
		}

		/**
		 * Render the detected images and embed for selection, if any
		 */
		function renderDetectedMedia() {
			var mediaContainer = $( '#featured-media-container'),
				listContainer  = $( '#all-media-container' ),
				found          = 0;

			listContainer.empty();

			if ( interestingEmbeds || interestingImages ) {
				listContainer.append( '<h2 class="screen-reader-text">' + __( 'allMediaHeading' ) + '</h2><ul class="wppt-all-media-list"/>' );
			}

			if ( interestingEmbeds ) {
				$.each( interestingEmbeds, function ( i, src ) {
					src = checkUrl( src );

					var displaySrc = '',
						cssClass   = 'suggested-media-thumbnail suggested-media-embed';

					if ( src.indexOf( 'youtube.com/' ) > -1 ) {
						displaySrc = 'https://i.ytimg.com/vi/' + src.replace( /.+v=([^&]+).*/, '$1' ) + '/hqdefault.jpg';
						cssClass += ' is-video';
					} else if ( src.indexOf( 'youtu.be/' ) > -1 ) {
						displaySrc = 'https://i.ytimg.com/vi/' + src.replace( /\/([^\/])$/, '$1' ) + '/hqdefault.jpg';
						cssClass += ' is-video';
					} else if ( src.indexOf( 'dailymotion.com' ) > -1 ) {
						displaySrc = src.replace( '/video/', '/thumbnail/video/' );
						cssClass += ' is-video';
					} else if ( src.indexOf( 'soundcloud.com' ) > -1 ) {
						cssClass += ' is-audio';
					} else if ( src.indexOf( 'twitter.com' ) > -1 ) {
						cssClass += ' is-tweet';
					} else {
						cssClass += ' is-video';
					}

					$( '<li></li>', {
						'id': 'embed-' + i + '-container',
						'class': cssClass,
						'tabindex': '0'
					} ).css( {
						'background-image': ( displaySrc ) ? 'url(' + displaySrc + ')' : null
					} ).html(
						'<span class="screen-reader-text">' + __( 'suggestedEmbedAlt' ).replace( '%d', i + 1 ) + '</span>'
					).on( 'click keypress', function ( e ) {
						if ( e.type === 'click' || e.which === 13 ) {
							insertSelectedMedia( 'embed',src );
						}
					} ).appendTo( '.wppt-all-media-list', listContainer );

					found++;
				} );
			}

			if ( interestingImages ) {
				$.each( interestingImages, function ( i, src ) {
					src = checkUrl( src );

					var displaySrc = src.replace(/^(http[^\?]+)(\?.*)?$/, '$1');
					if ( src.indexOf( 'files.wordpress.com/' ) > -1 ) {
						displaySrc = displaySrc.replace(/\?.*$/, '') + '?w=' + smallestWidth;
					} else if ( src.indexOf( 'gravatar.com/' ) > -1 ) {
						displaySrc = displaySrc.replace( /\?.*$/, '' ) + '?s=' + smallestWidth;
					} else {
						displaySrc = src;
					}

					$( '<li></li>', {
						'id': 'img-' + i + '-container',
						'class': 'suggested-media-thumbnail is-image',
						'tabindex': '0'
					} ).css( {
						'background-image': 'url(' + displaySrc + ')'
					} ).html(
						'<span class="screen-reader-text">' +__( 'suggestedImgAlt' ).replace( '%d', i + 1 ) + '</span>'
					).on( 'click keypress', function ( e ) {
						if ( e.type === 'click' || e.which === 13 ) {
							insertSelectedMedia( 'img', src, data.u );
						}
					} ).appendTo( '.wppt-all-media-list', listContainer );

					found++;
				} );
			}

			if ( ! found ) {
				mediaContainer.removeClass( 'all-media-visible' ).addClass( 'no-media');
				return;
			}

			mediaContainer.removeClass( 'no-media' ).addClass( 'all-media-visible' );
		}

		/* ***************************************************************
		 * MONITORING FUNCTIONS
		 *************************************************************** */

		/**
		 * Interactive navigation behavior for the options modal (post format, tags, categories)
		 */
		function monitorOptionsModal() {
			var $postOptions  = $( '.post-options' ),
				$postOption   = $( '.post-option' ),
				$settingModal = $( '.setting-modal' ),
				$modalClose   = $( '.modal-close' );

			$postOption.on( 'click', function( event ) {
				var index = $( this ).index(),
					$targetSettingModal = $settingModal.eq( index );

				$postOptions.addClass( isOffScreen )
					.one( transitionEndEvent, function() {
						$( this ).addClass( isHidden );
					} );

				$targetSettingModal.removeClass( offscreenHidden )
					.one( transitionEndEvent, function() {
						$( this ).find( '.modal-close' ).focus();
					} );
			} );

			$modalClose.on( 'click', function( event ) {
				var $targetSettingModal = $( this ).parent(),
					index = $targetSettingModal.index();

				$postOptions.removeClass( offscreenHidden );
				$targetSettingModal.addClass( isOffScreen );

				if ( transitionEndEvent ) {
					$targetSettingModal.one( transitionEndEvent, function() {
						$( this ).addClass( isHidden );
						$postOption.eq( index - 1 ).focus();
					} );
				} else {
					setTimeout( function() {
						$targetSettingModal.addClass( isHidden );
						$postOption.eq( index - 1 ).focus();
					}, 350 );
				}
			} );
		}

		/**
		 * Interactive behavior for the sidebar toggle, to show the options modals
		 */
		function openSidebar() {
			sidebarIsOpen = true;

			$( '.options-open, .press-this-actions, #scanbar' ).addClass( isHidden );
			$( '.options-close, .options-panel-back' ).removeClass( isHidden );

			$( '.options-panel' ).removeClass( offscreenHidden )
				.one( 'transitionend', function() {
					$( '.post-option:first' ).focus();
				} );
		}
		
		function closeSidebar() {
			sidebarIsOpen = false;

			$( '.options-close, .options-panel-back' ).addClass( isHidden );
			$( '.options-open, .press-this-actions, #scanbar' ).removeClass( isHidden );

			$( '.options-panel' ).addClass( isOffScreen )
				.one( 'transitionend', function() {
					$( this ).addClass( isHidden );
					// Reset to options list
					$( '.post-options' ).removeClass( offscreenHidden );
					$( '.setting-modal').addClass( offscreenHidden );
				} );
		}

		/**
		 * Interactive behavior for the post title's field placeholder
		 */
		function monitorPlaceholder() {
			var $selector = $( '#title-container'),
				$placeholder = $('.post-title-placeholder');

			$selector.on( 'focus', function() {
				$placeholder.addClass('is-hidden');
			} );

			$selector.on( 'blur', function() {
				if ( ! $( this ).text() ) {
					$placeholder.removeClass('is-hidden');
				}
			} );
		}

		/* ***************************************************************
		 * PROCESSING FUNCTIONS
		 *************************************************************** */

		/**
		 * Calls all the rendring related functions to happen on page load
		 */
		function render(){
			// We're on!
			renderToolsVisibility();
			renderSuggestedTitle();
			renderDetectedMedia();
			$( document ).on( 'tinymce-editor-init', renderSuggestedContent );
			renderStartupNotices();
		}

		/**
		 * Set app events and other state monitoring related code.
		 */
		function monitor(){
			$( '#current-site a').click( function( e ) {
				e.preventDefault();
			} );

			// Publish and Draft buttons and submit

			$( '#draft-field' ).on( 'click', function() {
				submitPost( 'draft' );
			} );

			$( '#publish-field' ).on( 'click', function() {
				submitPost( 'publish' );
			} );

			monitorOptionsModal();
			monitorPlaceholder();

			$( '.options-open' ).on( 'click.press-this', openSidebar );
			$( '.options-close' ).on( 'click.press-this', closeSidebar );

			// Close the sidebar when focus moves outside of it.
			$( '.options-panel, .options-panel-back' ).on( 'focusout.press-this', function() {
				setTimeout( function() {
					var node = document.activeElement,
						$node = $( node );

					if ( sidebarIsOpen && node && ! $node.hasClass( 'options-panel-back' ) &&
						( node.nodeName === 'BODY' ||
							( ! $node.closest( '.options-panel' ).length &&
							! $node.closest( '.options-open' ).length ) ) ) {

						closeSidebar();
					}
				}, 50 );
			});

			$( '#post-formats-select input' ).on( 'change', function() {
				var $this = $( this );

				if ( $this.is( ':checked' ) ) {
					$( '#post-option-post-format' ).text( $( 'label[for="' + $this.attr( 'id' ) + '"]' ).text() || '' );
				}
			} );

			$( window ).on( 'beforeunload.press-this', function() {
				if ( saveAlert || ( editor && editor.isDirty() ) ) {
					return __( 'saveAlert' );
				}
			} );

			$( 'button.add-cat-toggle' ).on( 'click.press-this', function() {
				var $this = $( this );

				$this.toggleClass( 'is-toggled' );
				$this.attr( 'aria-expanded', 'false' === $this.attr( 'aria-expanded' ) ? 'true' : 'false' );
				$( '.setting-modal .add-category, .categories-search-wrapper' ).toggleClass( 'is-hidden' );
			} );

			$( 'button.add-cat-submit' ).on( 'click.press-this', saveNewCategory );

			$( '.categories-search' ).on( 'keyup.press-this', function() {
				var search = $( this ).val().toLowerCase() || '';

				// Don't search when less thasn 3 extended ASCII chars
				if ( /[\x20-\xFF]+/.test( search ) && search.length < 2 ) {
					return;
				}

				$.each( catsCache, function( i, cat ) {
					cat.node.removeClass( 'is-hidden searched-parent' );
				} );

				if ( search ) {
					$.each( catsCache, function( i, cat ) {
						if ( cat.text.indexOf( search ) === -1 ) {
							cat.node.addClass( 'is-hidden' );
						} else {
							cat.parents.addClass( 'searched-parent' );
						}
					} );
				}
			} );

			return true;
		}

		function refreshCatsCache() {
			$( '.categories-select' ).find( 'li' ).each( function() {
				var $this = $( this );

				catsCache.push( {
					node: $this,
					parents: $this.parents( 'li' ),
					text: $this.children( 'label' ).text().toLowerCase()
				} );
			} );
		}

		// Let's go!
		$( document ).ready( function() {
			render();
			monitor();
			refreshCatsCache();
		});

		// Expose public methods
		// TODO: which are needed?
		return {
			renderNotice: renderNotice,
			renderError: renderError
		};
	};

	window.wp = window.wp || {};
	window.wp.pressThis = new PressThis();

}( jQuery, window ));
