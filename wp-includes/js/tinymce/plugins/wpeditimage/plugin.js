/* global tinymce */
tinymce.PluginManager.add( 'wpeditimage', function( editor ) {
	function parseShortcode( content ) {
		return content.replace( /(?:<p>)?\[(?:wp_)?caption([^\]]+)\]([\s\S]+?)\[\/(?:wp_)?caption\](?:<\/p>)?/g, function( a, b, c ) {
			var id, cls, w, cap, img, width,
				trim = tinymce.trim;

			id = b.match( /id=['"]([^'"]*)['"] ?/ );
			if ( id ) {
				b = b.replace( id[0], '' );
			}

			cls = b.match( /align=['"]([^'"]*)['"] ?/ );
			if ( cls ) {
				b = b.replace( cls[0], '' );
			}

			w = b.match( /width=['"]([0-9]*)['"] ?/ );
			if ( w ) {
				b = b.replace( w[0], '' );
			}

			c = trim( c );
			img = c.match( /((?:<a [^>]+>)?<img [^>]+>(?:<\/a>)?)([\s\S]*)/i );

			if ( img && img[2] ) {
				cap = trim( img[2] );
				img = trim( img[1] );
			} else {
				// old captions shortcode style
				cap = trim( b ).replace( /caption=['"]/, '' ).replace( /['"]$/, '' );
				img = c;
			}

			id = ( id && id[1] ) ? id[1] : '';
			cls = ( cls && cls[1] ) ? cls[1] : 'alignnone';
			w = ( w && w[1] ) ? w[1] : '';

			if ( ! w || ! cap ) {
				return c;
			}

			width = parseInt( w, 10 ) + 10;

			return '<div class="mceTemp" draggable="true"><dl id="'+ id +'" class="wp-caption '+ cls +'" style="width: '+ width +'px">' +
				'<dt class="wp-caption-dt">'+ img +'</dt><dd class="wp-caption-dd">'+ cap +'</dd></dl></div>';
		});
	}

	function getShortcode( content ) {
		return content.replace( /<div (?:id="attachment_|class="mceTemp)[^>]*>([\s\S]+?)<\/div>/g, function( a, b ) {
			var ret = b.replace( /<dl ([^>]+)>\s*<dt [^>]+>([\s\S]+?)<\/dt>\s*<dd [^>]+>([\s\S]*?)<\/dd>\s*<\/dl>/gi, function( a, b, c, cap ) {
				var id, cls, w;

				w = c.match( /width="([0-9]*)"/ );
				w = ( w && w[1] ) ? w[1] : '';

				if ( ! w || ! cap ) {
					return c;
				}

				id = b.match( /id="([^"]*)"/ );
				id = ( id && id[1] ) ? id[1] : '';

				cls = b.match( /class="([^"]*)"/ );
				cls = ( cls && cls[1] ) ? cls[1] : '';
				cls = cls.match( /align[a-z]+/ ) || 'alignnone';

				cap = cap.replace( /\r\n|\r/g, '\n' ).replace( /<[a-zA-Z0-9]+( [^<>]+)?>/g, function( a ) {
					// no line breaks inside HTML tags
					return a.replace( /[\r\n\t]+/, ' ' );
				});

				// convert remaining line breaks to <br>
				cap = cap.replace( /\s*\n\s*/g, '<br />' );

				return '[caption id="'+ id +'" align="'+ cls +'" width="'+ w +'"]'+ c +' '+ cap +'[/caption]';
			});

			if ( ret.indexOf('[caption') !== 0 ) {
				// the caption html seems brocken, try to find the image that may be wrapped in a link
				// and may be followed by <p> with the caption text.
				ret = b.replace( /[\s\S]*?((?:<a [^>]+>)?<img [^>]+>(?:<\/a>)?)(<p>[\s\S]*<\/p>)?[\s\S]*/gi, '<p>$1</p>$2' );
			}

			return ret;
		});
	}

	editor.on( 'init', function() {
		var dom = editor.dom;

		// Add caption field to the default image dialog
		editor.on( 'wpLoadImageForm', function( e ) {
			if ( editor.getParam( 'wpeditimage_disable_captions' ) ) {
				return;
			}

			var captionField = {
				type: 'textbox',
				flex: 1,
				name: 'caption',
				minHeight: 60,
				multiline: true,
				scroll: true,
				label: 'Image caption'
			};

			e.data.splice( e.data.length - 1, 0, captionField );
		});

		// Fix caption parent width for images added from URL
		editor.on( 'wpNewImageRefresh', function( e ) {
			var parent, captionWidth;

			if ( parent = dom.getParent( e.node, 'dl.wp-caption' ) ) {
				if ( ! parent.style.width ) {
					captionWidth = parseInt( e.node.clientWidth, 10 ) + 10;
					captionWidth = captionWidth ? captionWidth + 'px' : '50%';
					dom.setStyle( parent, 'width', captionWidth );
				}
			}
		});

		editor.on( 'wpImageFormSubmit', function( e ) {
			var data = e.imgData.data,
				imgNode = e.imgData.node,
				caption = e.imgData.caption,
				captionId = '',
				captionAlign = '',
				captionWidth = '',
				wrap, parent, html, P, imgId;

			// Temp image id so we can find the node later
			data.id = '__wp-temp-img-id';
			// Cancel the original callback
			e.imgData.cancel = true;

			if ( ! data.style ) {
				data.style = null;
			}

			if ( ! data.src ) {
				// Delete the image and the caption
				if ( imgNode ) {
					if ( wrap = dom.getParent( imgNode, 'div.mceTemp' ) ) {
						dom.remove( wrap );
					} else if ( imgNode.parentNode.nodeName === 'A' ) {
						dom.remove( imgNode.parentNode );
					} else {
						dom.remove( imgNode );
					}

					editor.nodeChanged();
				}
				return;
			}

			if ( ! imgNode ) {
				// New image inserted
				html = dom.createHTML( 'img', data );

				if ( caption ) {
					node = editor.selection.getNode();

					if ( data.width ) {
						captionWidth = parseInt( data.width, 10 ) + 10;
						captionWidth = ' style="width: '+ captionWidth +'px"';
					}

					html = '<dl class="wp-caption alignnone"' + captionWidth + '>' +
						'<dt class="wp-caption-dt">'+ html +'</dt><dd class="wp-caption-dd">'+ caption +'</dd></dl>';

					if ( node.nodeName === 'P' ) {
						parent = node;
					} else {
						parent = dom.getParent( node, 'p' );
					}

					if ( parent && parent.nodeName === 'P' ) {
						wrap = dom.create( 'div', { 'class': 'mceTemp' }, html );
						dom.insertAfter( wrap, parent );
						editor.selection.select( wrap );
						editor.nodeChanged();

						if ( dom.isEmpty( parent ) ) {
							dom.remove( parent );
						}
					} else {
						editor.selection.setContent( '<div class="mceTemp">' + html + '</div>' );
					}
				} else {
					editor.selection.setContent( html );
				}
			} else {
				// Edit existing image

				// Store the original image id if any
				imgId = imgNode.id || null;
				// Update the image node
				dom.setAttribs( imgNode, data );
				wrap = dom.getParent( imgNode, 'dl.wp-caption' );

				if ( caption ) {
					if ( wrap ) {
						if ( parent = dom.select( 'dd.wp-caption-dd', wrap )[0] ) {
							parent.innerHTML = caption;
						}
					} else {
						if ( imgNode.className ) {
							captionId = imgNode.className.match( /wp-image-([0-9]+)/ );
							captionAlign = imgNode.className.match( /align(left|right|center|none)/ );
						}

						if ( captionAlign ) {
							captionAlign = captionAlign[0];
							imgNode.className = imgNode.className.replace( /align(left|right|center|none)/g, '' );
						} else {
							captionAlign = 'alignnone';
						}

						captionAlign = ' class="wp-caption ' + captionAlign + '"';

						if ( captionId ) {
							captionId = ' id="attachment_' + captionId[1] + '"';
						}

						captionWidth = data.width || imgNode.clientWidth;

						if ( captionWidth ) {
							captionWidth = parseInt( captionWidth, 10 ) + 10;
							captionWidth = ' style="width: '+ captionWidth +'px"';
						}

						if ( imgNode.parentNode && imgNode.parentNode.nodeName === 'A' ) {
							html = dom.getOuterHTML( imgNode.parentNode );
							node = imgNode.parentNode;
						} else {
							html = dom.getOuterHTML( imgNode );
							node = imgNode;
						}

						html = '<dl ' + captionId + captionAlign + captionWidth + '>' +
							'<dt class="wp-caption-dt">'+ html +'</dt><dd class="wp-caption-dd">'+ caption +'</dd></dl>';

						if ( parent = dom.getParent( imgNode, 'p' ) ) {
							wrap = dom.create( 'div', { 'class': 'mceTemp' }, html );
							dom.insertAfter( wrap, parent );
							editor.selection.select( wrap );
							editor.nodeChanged();

							// Delete the old image node
							dom.remove( node );

							if ( dom.isEmpty( parent ) ) {
								dom.remove( parent );
							}
						} else {
							editor.selection.setContent( '<div class="mceTemp">' + html + '</div>' );
						}
					}
				} else {
					if ( wrap ) {
						// Remove the caption wrapper and place the image in new paragraph
						if ( imgNode.parentNode.nodeName === 'A' ) {
							html = dom.getOuterHTML( imgNode.parentNode );
						} else {
							html = dom.getOuterHTML( imgNode );
						}

						parent = dom.create( 'p', {}, html );
						dom.insertAfter( parent, wrap.parentNode );
						editor.selection.select( parent );
						editor.nodeChanged();
						dom.remove( wrap.parentNode );
					}
				}
			}

			imgNode = dom.get('__wp-temp-img-id');
			dom.setAttrib( imgNode, 'id', imgId );
			e.imgData.node = imgNode;
		});

		editor.on( 'wpLoadImageData', function( e ) {
			var parent,
				data = e.imgData.data
				imgNode = e.imgData.node;

			if ( parent = dom.getParent( imgNode, 'dl.wp-caption' ) ) {
				parent = dom.select( 'dd.wp-caption-dd', parent )[0];
				data.caption = parent ? parent.innerHTML : '';
			}
		});

		// Prevent dragging images out of the caption elements
		dom.bind( editor.getDoc(), 'dragstart', function( event ) {
			var node = editor.selection.getNode();

			if ( node.nodeName === 'IMG' && dom.getParent( node, '.wp-caption' ) ) {
				event.preventDefault();
			}
		});
	});

	editor.on( 'BeforeExecCommand', function( e ) {
		var node, p, DL, align,
			cmd = e.command,
			dom = editor.dom;

		if ( cmd === 'mceInsertContent' ) {
			// When inserting content, if the caret is inside a caption create new paragraph under
			// and move the caret there
			if ( node = dom.getParent( editor.selection.getNode(), 'div.mceTemp' ) ) {
				p = dom.create( 'p' );
				dom.insertAfter( p, node );
				editor.selection.setCursorLocation( p, 0 );
				editor.nodeChanged();

				if ( tinymce.Env.ie > 8 ) {
					setTimeout( function() {
						editor.selection.setCursorLocation( p, 0 );
						editor.selection.setContent( e.value );
					}, 500 );

					return false;
				}
			}
		} else if ( cmd === 'JustifyLeft' || cmd === 'JustifyRight' || cmd === 'JustifyCenter' ) {
			// When inside an image caption, set the align* class on dt.wp-caption
			node = editor.selection.getNode();
			align = cmd.substr(7).toLowerCase();
			align = 'align' + align;

			if ( dom.is( node, 'dl.wp-caption' ) ) {
				DL = node;
			} else {
				DL = dom.getParent( node, 'dl.wp-caption' );
			}

			if ( DL ) {
				if ( dom.hasClass( DL, align ) ) {
					dom.removeClass( DL, align );
					dom.addClass( DL, 'alignnone' );
				} else {
					DL.className = DL.className.replace( /align[^ ]+/g, '' );
					dom.addClass( DL, align );
				}

				return false;
			}
		}
	});

	editor.on( 'keydown', function( e ) {
		var node, wrap, P, spacer,
			selection = editor.selection,
			dom = editor.dom;

		if ( e.keyCode === tinymce.util.VK.ENTER ) {
			// When pressing Enter inside a caption move the caret to a new parapraph under it
			wrap = dom.getParent( editor.selection.getNode(), 'div.mceTemp' );

			if ( wrap ) {
				dom.events.cancel(e); // Doesn't cancel all :(

				// Remove any extra dt and dd cleated on pressing Enter...
				tinymce.each( dom.select( 'dt, dd', wrap ), function( element ) {
					if ( dom.isEmpty( element ) ) {
						dom.remove( element );
					}
				});

				spacer = tinymce.Env.ie ? '' : '<br data-mce-bogus="1" />';
				P = dom.create( 'p', null, spacer );
				dom.insertAfter( P, wrap );
				selection.setCursorLocation( P, 0 );
				editor.nodeChanged();
			}
		} else if ( e.keyCode === tinymce.util.VK.DELETE || e.keyCode === tinymce.util.VK.BACKSPACE ) {
			node = selection.getNode();

			if ( node.nodeName === 'DIV' && dom.hasClass( node, 'mceTemp' ) ) {
				wrap = node;
			} else if ( node.nodeName === 'IMG' || node.nodeName === 'DT' || node.nodeName === 'A' ) {
				wrap = dom.getParent( node, 'div.mceTemp' );
			}

			if ( wrap ) {
				dom.events.cancel(e);

				if ( wrap.nextSibling ) {
					selection.select( wrap.nextSibling );
				} else if ( wrap.previousSibling ) {
					selection.select( wrap.previousSibling );
				} else {
					selection.select( wrap.parentNode );
				}

				selection.collapse( true );
				editor.nodeChanged();
				dom.remove( wrap );
				wrap = null;
				return false;
			}
		}
	});

	editor.wpSetImgCaption = function( content ) {
		return parseShortcode( content );
	};

	editor.wpGetImgCaption = function( content ) {
		return getShortcode( content );
	};

	editor.on( 'BeforeSetContent', function( e ) {
		e.content = editor.wpSetImgCaption( e.content );
	});

	editor.on( 'PostProcess', function( e ) {
		if ( e.get ) {
			e.content = editor.wpGetImgCaption( e.content );
		}
	});

	return {
		_do_shcode: parseShortcode,
		_get_shcode: getShortcode
	};
});
