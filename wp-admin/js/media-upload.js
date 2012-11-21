// send html to the post editor

var wpActiveEditor;

function send_to_editor(h) {
	var ed, mce = typeof(tinymce) != 'undefined', qt = typeof(QTags) != 'undefined';

	if ( !wpActiveEditor ) {
		if ( mce && tinymce.activeEditor ) {
			ed = tinymce.activeEditor;
			wpActiveEditor = ed.id;
		} else if ( !qt ) {
			return false;
		}
	} else if ( mce ) {
		if ( tinymce.activeEditor && (tinymce.activeEditor.id == 'mce_fullscreen' || tinymce.activeEditor.id == 'wp_mce_fullscreen') )
			ed = tinymce.activeEditor;
		else
			ed = tinymce.get(wpActiveEditor);
	}

	if ( ed && !ed.isHidden() ) {
		// restore caret position on IE
		if ( tinymce.isIE && ed.windowManager.insertimagebookmark )
			ed.selection.moveToBookmark(ed.windowManager.insertimagebookmark);

		if ( h.indexOf('[caption') === 0 ) {
			if ( ed.wpSetImgCaption )
				h = ed.wpSetImgCaption(h);
		} else if ( h.indexOf('[gallery') === 0 ) {
			if ( ed.plugins.wpgallery )
				h = ed.plugins.wpgallery._do_gallery(h);
		} else if ( h.indexOf('[embed') === 0 ) {
			if ( ed.plugins.wordpress )
				h = ed.plugins.wordpress._setEmbed(h);
		}

		ed.execCommand('mceInsertContent', false, h);
	} else if ( qt ) {
		QTags.insertContent(h);
	} else {
		document.getElementById(wpActiveEditor).value += h;
	}

	try{tb_remove();}catch(e){};
}

// thickbox settings
var tb_position;
(function($) {
	tb_position = function() {
		var tbWindow = $('#TB_window'), width = $(window).width(), H = $(window).height(), W = ( 720 < width ) ? 720 : width, adminbar_height = 0;

		if ( $('body.admin-bar').length )
			adminbar_height = 28;

		if ( tbWindow.size() ) {
			tbWindow.width( W - 50 ).height( H - 45 - adminbar_height );
			$('#TB_iframeContent').width( W - 50 ).height( H - 75 - adminbar_height );
			tbWindow.css({'margin-left': '-' + parseInt((( W - 50 ) / 2),10) + 'px'});
			if ( typeof document.body.style.maxWidth != 'undefined' )
				tbWindow.css({'top': 20 + adminbar_height + 'px','margin-top':'0'});
		};

		return $('a.thickbox').each( function() {
			var href = $(this).attr('href');
			if ( ! href ) return;
			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			$(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 - adminbar_height ) );
		});
	};

	$(window).resize(function(){ tb_position(); });

	// store caret position in IE
	$(document).ready(function($){
		$('a.thickbox').click(function(){
			var ed;

			if ( typeof(tinymce) != 'undefined' && tinymce.isIE && ( ed = tinymce.get(wpActiveEditor) ) && !ed.isHidden() ) {
				ed.focus();
				ed.windowManager.insertimagebookmark = ed.selection.getBookmark();
			}
		});
	});

})(jQuery);

// WordPress, TinyMCE, and Media
// -----------------------------
(function($){
	// Stores the editors' `wp.media.controller.Frame` instances.
	var workflows = {},
		linkToUrl;

	linkToUrl = function( props, attachment ) {
		var link = props.link || getUserSetting( 'urlbutton', 'post' ),
			url;

		if ( 'file' === link )
			url = attachment.url;
		else if ( 'post' === link )
			url = attachment.link;
		else if ( 'custom' === link )
			url = props.linkUrl;

		return url || '';
	};

	wp.media.string = {
		link: function( props, attachment ) {
			var options;

			props = _.defaults( props || {}, {
				title:   '',
				linkUrl: ''
			});

			if ( attachment ) {
				attachment = attachment.toJSON();

				_.extend( props, {
					title:   attachment.title || attachment.filename,
					linkUrl: linkToUrl( props, attachment ),
					rel:     'attachment wp-att-' + attachment.id
				});
			}

			options = {
				tag:     'a',
				content: props.title,
				attrs:   {
					href: props.linkUrl
				}
			};

			if ( props.rel )
				options.attrs.rel = props.rel;

			return wp.html.string( options );
		},

		image: function( props, attachment ) {
			var classes = [],
				img = {},
				options, sizes, size, shortcode, html;

			props = _.defaults( props || {}, {
				align: getUserSetting( 'align', 'none' ),
				size:  getUserSetting( 'imgsize', 'medium' ),
				url:   ''
			});

			if ( attachment ) {
				attachment = attachment.toJSON();

				classes.push( 'wp-image-' + attachment.id );

				sizes = attachment.sizes;
				size = sizes && sizes[ props.size ] ? sizes[ props.size ] : attachment;

				_.extend( props, _.pick( attachment, 'align', 'caption' ), {
					width:     size.width,
					height:    size.height,
					src:       size.url,
					linkUrl:   linkToUrl( props, attachment ),
					captionId: 'attachment_' + attachment.id
				});
			}

			img.src = props.url;
			_.extend( img, _.pick( props, 'width', 'height', 'alt' ) );

			// Only assign the align class to the image if we're not printing
			// a caption, since the alignment is sent to the shortcode.
			if ( props.align && ! props.caption )
				classes.push( 'align' + props.align );

			if ( props.size )
				classes.push( 'size-' + props.size );

			img['class'] = _.compact( classes ).join(' ');

			// Generate `img` tag options.
			options = {
				tag:    'img',
				attrs:  img,
				single: true
			};

			// Generate the `a` element options, if they exist.
			if ( props.linkUrl ) {
				options = {
					tag:   'a',
					attrs: {
						href: props.linkUrl
					},
					content: options
				};
			}

			html = wp.html.string( options );

			// Generate the caption shortcode.
			if ( props.caption ) {
				shortcode = {};

				if ( img.width )
					shortcode.width = img.width;

				if ( props.captionId )
					shortcode.id = props.captionId;

				if ( props.align )
					shortcode.align = 'align' + props.align;

				html = wp.shortcode.string({
					tag:     'caption',
					attrs:   shortcode,
					content: html + ' ' + props.caption
				});
			}

			return html;
		}
	};

	wp.media.gallery = (function() {
		var galleries = {};

		return {
			defaults: {
				order:      'ASC',
				id:         wp.media.view.settings.postId,
				itemtag:    'dl',
				icontag:    'dt',
				captiontag: 'dd',
				columns:    3,
				size:       'thumbnail'
			},

			attachments: function( shortcode ) {
				var shortcodeString = shortcode.string(),
					result = galleries[ shortcodeString ],
					attrs, args, query, others;

				delete galleries[ shortcodeString ];

				if ( result )
					return result;

				attrs = shortcode.attrs.named;
				args  = _.pick( attrs, 'orderby', 'order' );

				args.type    = 'image';
				args.perPage = -1;

				// Map the `ids` param to the correct query args.
				if ( attrs.ids ) {
					args.post__in = attrs.ids.split(',');
					args.orderby  = 'post__in';
				} else if ( attrs.include ) {
					args.post__in = attrs.include.split(',');
				}

				if ( attrs.exclude )
					args.post__not_in = attrs.exclude.split(',');

				if ( ! args.post__in )
					args.parent = attrs.id;

				// Collect the attributes that were not included in `args`.
				others = {};
				_.filter( attrs, function( value, key ) {
					if ( _.isUndefined( args[ key ] ) )
						others[ key ] = value;
				});

				query = media.query( args );
				query.gallery = new Backbone.Model( others );
				return query;
			},

			shortcode: function( attachments ) {
				var props = attachments.props.toJSON(),
					attrs = _.pick( props, 'include', 'exclude', 'orderby', 'order' ),
					shortcode, clone;

				if ( attachments.gallery )
					_.extend( attrs, attachments.gallery.toJSON() );

				attrs.ids = attachments.pluck('id');

				// If the `ids` attribute is set and `orderby` attribute
				// is the default value, clear it for cleaner output.
				if ( attrs.ids && 'post__in' === attrs.orderby )
					delete attrs.orderby;

				// Remove default attributes from the shortcode.
				_.each( wp.media.gallery.defaults, function( value, key ) {
					if ( value === attrs[ key ] )
						delete attrs[ key ];
				});

				shortcode = new wp.shortcode({
					tag:    'gallery',
					attrs:  attrs,
					type:   'single'
				});

				// Use a cloned version of the gallery.
				clone = new wp.media.model.Attachments( attachments.models, {
					props: props
				});
				clone.gallery = attachments.gallery;
				galleries[ shortcode.string() ] = clone;

				return shortcode;
			},

			edit: function( content ) {
				var shortcode = wp.shortcode.next( 'gallery', content ),
					defaultPostId = wp.media.gallery.defaults.id,
					attachments, selection;

				// Bail if we didn't match the shortcode or all of the content.
				if ( ! shortcode || shortcode.content !== content )
					return;

				// Ignore the rest of the match object.
				shortcode = shortcode.shortcode;

				if ( _.isUndefined( shortcode.get('id') ) && ! _.isUndefined( defaultPostId ) )
					shortcode.set( 'id', defaultPostId );

				attachments = wp.media.gallery.attachments( shortcode );

				selection = new wp.media.model.Selection( attachments.models, {
					props:    attachments.props.toJSON(),
					multiple: true
				});

				selection.gallery = attachments.gallery;

				// Fetch the query's attachments, and then break ties from the
				// query to allow for sorting.
				selection.more().done( function() {
					// Break ties with the query.
					selection.props.set({ query: false });
					selection.unmirror();
					selection.props.unset('orderby');
				});

				return wp.media({
					frame:     'post',
					state:     'gallery-edit',
					title:     wp.media.view.l10n.editGalleryTitle,
					editing:   true,
					multiple:  true,
					selection: selection
				});
			}
		};
	}());

	wp.media.editor = {
		insert: send_to_editor,

		add: function( id, options ) {
			var workflow = this.get( id );

			if ( workflow )
				return workflow;

			workflow = workflows[ id ] = wp.media( _.defaults( options || {}, {
				frame:    'post',
				title:    wp.media.view.l10n.insertMedia,
				multiple: true
			} ) );

			workflow.on( 'insert', function( selection ) {
				var state = workflow.state(),
					details = state.get('details');

				selection = selection || state.get('selection');

				if ( ! selection || ! details )
					return;

				selection.each( function( attachment ) {
					var detail = details[ attachment.cid ];

					if ( detail )
						detail = detail.toJSON();

					// Reset the attachment details.
					delete details[ attachment.cid ];

					if ( 'image' === attachment.get('type') )
						this.insert( wp.media.string.image( detail, attachment ) + ' ' );
					else
						this.insert( wp.media.string.link( detail, attachment ) + ' ' );
				}, this );
			}, this );

			workflow.get('gallery-edit').on( 'update', function( selection ) {
				this.insert( wp.media.gallery.shortcode( selection ).string() );
			}, this );

			workflow.get('embed').on( 'select', function() {
				var embed = workflow.state().toJSON();

				embed.url = embed.url || '';

				if ( 'link' === embed.type ) {
					_.defaults( embed, {
						title:   embed.url,
						linkUrl: embed.url
					});

					this.insert( wp.media.string.link( embed ) );

				} else if ( 'image' === embed.type ) {
					_.defaults( embed, {
						title:   embed.url,
						linkUrl: '',
						align:   'none',
						link:    'none'
					});

					if ( 'none' === embed.link )
						embed.linkUrl = '';
					else if ( 'file' === embed.link )
						embed.linkUrl = embed.url;

					this.insert( wp.media.string.image( embed ) );
				}
			}, this );

			return workflow;
		},

		get: function( id ) {
			return workflows[ id ];
		},

		remove: function( id ) {
			delete workflows[ id ];
		},

		init: function() {
			$(document.body).on('click', '.insert-media', function( event ) {
				var $this = $(this),
					editor = $this.data('editor'),
					workflow;

				event.preventDefault();

				// Remove focus from the `.insert-media` button.
				// Prevents Opera from showing the outline of the button
				// above the modal.
				//
				// See: http://core.trac.wordpress.org/ticket/22445
				$this.blur();

				if ( ! _.isString( editor ) )
					return;

				workflow = wp.media.editor.get( editor );

				// If the workflow exists, just open it.
				if ( workflow ) {
					workflow.open();
					return;
				}

				// Initialize the editor's workflow if we haven't yet.
				wp.media.editor.add( editor );
			});
		}
	};

	$( wp.media.editor.init );
}(jQuery));