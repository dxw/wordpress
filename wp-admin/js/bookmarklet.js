( function( window, document, href, pt_url ) {
	var encURI = window.encodeURIComponent,
		form = document.createElement( 'form' ),
		head = document.getElementsByTagName( 'head' )[0],
		img = new Image(),
		target = '_press_this_app',
		canPost = true,
		windowWidth, windowHeight,
		metas, links, content, imgs, ifrs,
		selection;

	if ( ! pt_url ) {
		return;
	}

	if ( href.match( /^https?:/ ) ) {
		pt_url += '&u=' + encURI( href );
		if ( href.match( /^https:/ ) && pt_url.match( /^http:/ ) ) {
			canPost = false;
		}
	} else {
		top.location.href = pt_url;
		return;
	}

	if ( window.getSelection ) {
		selection = window.getSelection() + '';
	} else if ( document.getSelection ) {
		selection = document.getSelection() + '';
	} else if ( document.selection ) {
		selection = document.selection.createRange().text || '';
	}

	pt_url += ( pt_url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'buster=' + ( new Date().getTime() );

	if ( ! canPost ) {
		if ( document.title ) {
			pt_url += '&t=' + encURI( document.title.substr( 0, 256 ) );
		}

		if ( selection ) {
			pt_url += '&s=' + encURI( selection.substr( 0, 512 ) );
		}
	}

	windowWidth  = window.outerWidth || document.documentElement.clientWidth || 600;
	windowHeight = window.outerHeight || document.documentElement.clientHeight || 700;

	windowWidth = ( windowWidth < 800 || windowWidth > 5000 ) ? 600 : ( windowWidth * 0.7 );
	windowHeight = ( windowHeight < 800 || windowHeight > 3000 ) ? 700 : ( windowHeight * 0.9 );

	if ( ! canPost ) {
		window.open( pt_url, target, 'location,resizable,scrollbars,width=' + windowWidth + ',height=' + windowHeight );
		return;
	}

	function add( name, value ) {
		if ( typeof value === 'undefined' ) {
			return;
		}

		var input = document.createElement( 'input' );

		input.name = name;
		input.value = value;
		input.type = 'hidden';

		form.appendChild( input );
	}

	if ( href.match( /\/\/www\.youtube\.com\/watch/ ) ) {
		add( '_embed[]', href );
	} else if ( href.match( /\/\/vimeo\.com\/(.+\/)?([\d]+)$/ ) ) {
		add( '_embed[]', href );
	} else if ( href.match( /\/\/(www\.)?dailymotion\.com\/video\/.+$/ ) ) {
		add( '_embed[]', href );
	} else if ( href.match( /\/\/soundcloud\.com\/.+$/ ) ) {
		add( '_embed[]', href );
	} else if ( href.match( /\/\/twitter\.com\/[^\/]+\/status\/[\d]+$/ ) ) {
		add( '_embed[]', href );
	} else if ( href.match( /\/\/vine\.co\/v\/[^\/]+/ ) ) {
		add( '_embed[]', href );
	}

	metas = head.getElementsByTagName( 'meta' ) || [];

	for ( var m = 0; m < metas.length; m++ ) {
		if ( m >= 50 ) {
			break;
		}

		var q = metas[ m ],
			q_name = q.getAttribute( 'name' ),
			q_prop = q.getAttribute( 'property' ),
			q_cont = q.getAttribute( 'content' );

		if ( q_name ) {
			add( '_meta[' + q_name + ']', q_cont );
		} else if ( q_prop ) {
			add( '_meta[' + q_prop + ']', q_cont );
		}
	}

	links = head.getElementsByTagName( 'link' ) || [];

	for ( var y = 0; y < links.length; y++ ) {
		if ( y >= 50 ) {
			break;
		}

		var g = links[ y ],
			g_rel = g.getAttribute( 'rel' );

		if ( g_rel ) {
			switch ( g_rel ) {
				case 'canonical':
				case 'icon':
				case 'shortlink':
					add( '_links[' + g_rel + ']', g.getAttribute( 'href' ) );
					break;
				case 'alternate':
					if ( 'application/json+oembed' === g.getAttribute( 'type' ) ) {
						add( '_links[' + g_rel + ']', g.getAttribute( 'href' ) );
					} else if ( 'handheld' === g.getAttribute( 'media' ) ) {
						add( '_links[' + g_rel + ']', g.getAttribute( 'href' ) );
					}
			}
		}
	}

	if ( document.body.getElementsByClassName ) {
		content = document.body.getElementsByClassName( 'hfeed' )[0];
	}

	content = document.getElementById( 'content' ) || content || document.body;
	imgs = content.getElementsByTagName( 'img' ) || [];

	for ( var n = 0; n < imgs.length; n++ ) {
		if ( n >= 50 ) {
			break;
		}

		if ( imgs[ n ].src.indexOf( 'avatar' ) > -1 || imgs[ n ].className.indexOf( 'avatar' ) > -1 ) {
			continue;
		}

		img.src = imgs[ n ].src;

		if ( img.width >= 256 && img.height >= 128 ) {
			add( '_img[]', img.src );
		}
	}

	ifrs = document.body.getElementsByTagName( 'iframe' ) || [];

	for ( var p = 0; p < ifrs.length; p++ ) {
		if ( p >= 50 ) {
			break;
		}

		add( '_embed[]', ifrs[ p ].src );
	}

	if ( document.title ) {
		add( 't', document.title );
	}

	if ( selection ) {
		add( 's', selection );
	}

	form.setAttribute( 'method', 'POST' );
	form.setAttribute( 'action', pt_url );
	form.setAttribute( 'target', target );
	form.setAttribute( 'style', 'display: none;' );

	window.open( 'about:blank', target, 'location,resizable,scrollbars,width=' + windowWidth + ',height=' + windowHeight );

	document.body.appendChild( form );
	form.submit();
} )( window, document, top.location.href, window.pt_url );
