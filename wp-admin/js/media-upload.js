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
	// Stores the editors' `wp.media.controller.Workflow` instances.
	var workflows = {};

	wp.mce.media = {
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

				this.insert( selection.map( function( attachment ) {
					var detail = details[ attachment.cid ];

					if ( detail )
						detail = detail.toJSON();

					// Reset the attachment details.
					delete details[ attachment.cid ];

					if ( 'image' === attachment.get('type') )
						return wp.media.string.image( attachment, detail ) + ' ';
					else
						return wp.media.string.link( attachment, detail ) + ' ';
				}).join('') );
			}, this );

			workflow.get('gallery-edit').on( 'update', function( selection ) {
				var view = wp.mce.view.get('gallery'),
					shortcode;

				if ( ! view )
					return;

				shortcode = view.gallery.shortcode( selection );
				this.insert( shortcode.string() );
			}, this );

			workflow.get('embed').on( 'select', function() {
				var embed = workflow.state().toJSON(),
					options;

				if ( 'link' === embed.type ) {
					this.insert( wp.html.string({
						tag:     'a',
						content: embed.title || embed.url,
						attrs:   {
							href: embed.url
						}
					}) );

				} else if ( 'image' === embed.type ) {
					_.defaults( embed, {
						align:   'none',
						url:     '',
						alt:     '',
						linkUrl: '',
						link:    'none'
					});

					options = {
						single: true,
						tag:    'img',
						attrs:  {
							'class': 'align' + embed.align,
							src:     embed.url,
							alt:     embed.alt
						}
					};

					if ( 'custom' === embed.link || 'file' === embed.link ) {
						options = {
							tag:     'a',
							content: options,
							attrs:   {
								href: 'custom' === embed.link ? embed.linkUrl : embed.url
							}
						};
					}

					this.insert( wp.html.string( options ) );
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
			$('#wpbody').on('click', '.insert-media', function( event ) {
				var editor = $(this).data('editor'),
					workflow;

				event.preventDefault();

				if ( ! editor )
					return;

				workflow = wp.mce.media.get( editor );

				// If the workflow exists, just open it.
				if ( workflow ) {
					workflow.open();
					return;
				}

				// Initialize the editor's workflow if we haven't yet.
				wp.mce.media.add( editor );
			});
		}
	};

	$( wp.mce.media.init );
}(jQuery));