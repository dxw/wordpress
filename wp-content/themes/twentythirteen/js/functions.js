/**
 * Functionality specific to Twenty Thirteen.
 *
 * Provides helper functions to enhance the theme experience.
 */

( function( $ ) {
	var twentyThirteen = {
		/**
		 * Adds a top margin to the footer if the sidebar widget area is
		 * higher than the rest of the page, to help the footer always
		 * visually clear the sidebar.
		 */
		adjustFooter : function() {
			var sidebar   = $( '#secondary .widget-area' ),
			    secondary = ( 0 == sidebar.length ) ? -40 : sidebar.height(),
			    margin    = $( '#tertiary .widget-area' ).height() - $( '#content' ).height() - secondary;

			if ( margin > 0 && window.innerWidth > 999 )
				$( '#colophon' ).css( 'margin-top', margin + 'px' );
			else
				console.log( 'nothing' );
		},

		/**
		 * Repositions the window on jump-to-anchor to account for navbar
		 * height.
		 */
		adjustAnchor : function() {
			if ( window.location.hash )
				window.scrollBy( 0, -49 );
		}
	};

	$( document ).on( 'ready', function() {
		twentyThirteen.adjustAnchor();

		if ( body.is( '.sidebar' ) )
			twentyThirteen.adjustFooter();
	} );
	$( window ).on( 'hashchange', twentyThirteen.adjustAnchor );

	/**
	 * Displays the fixed navbar based on screen position.
	 */
	var html          = $( 'html' ),
		body          = $( 'body' ),
		navbar        = $( '#navbar' ),
		navbarOffset  = -1,
		toolbarOffset = body.is( '.admin-bar' ) ? 28 : 0;

	$( window ).scroll( function() {
		if ( navbarOffset < 0 )
			navbarOffset = navbar.offset().top - toolbarOffset;

		if ( window.scrollY >= navbarOffset && 644 < window.innerWidth )
			html.addClass( 'navbar-fixed' );
		else
			html.removeClass( 'navbar-fixed' );
	} );

	/**
	 * Allows clicking the navbar to scroll to top.
	 */
	navbar.on( 'click', function( event ) {
		// Ensure that the navbar element was the target of the click.
		if ( 'navbar' == event.target.id  || 'site-navigation' == event.target.id )
			$( 'html, body' ).animate( { scrollTop: 0 }, 'fast' );
	} );

	/**
	 * Enables menu toggle for small screens.
	 */
	( function() {
		var nav = $( '#site-navigation' ), button, menu;
		if ( ! nav )
			return;

		button = nav.find( '.menu-toggle' );
		menu   = nav.find( '.nav-menu' );
		if ( ! button )
			return;

		// Hide button if menu is missing or empty.
		if ( ! menu || ! menu.children().length ) {
			button.hide();
			return;
		}

		$( '.menu-toggle' ).on( 'click', function() {
			nav.toggleClass( 'toggled-on' );
		} );
	} )();


	/**
	 * Makes "skip to content" link work correctly in IE9 and Chrome for better
	 * accessibility.
	 *
	 * @link http://www.nczonline.net/blog/2013/01/15/fixing-skip-to-content-links/
	 */
	$( window ).on( 'hashchange', function() {
		var element = $( location.hash );

		if ( element ) {
			if ( ! /^(?:a|select|input|button)$/i.test( element.tagName ) )
				element.attr( 'tabindex', -1 );

			element.focus();
		}
	} );

	/**
	 * Arranges footer widgets vertically.
	 */
	if ( $.isFunction( $.fn.masonry ) ) {
		var columnWidth = body.is( '.sidebar' ) ? 228 : 245;

		$( '#secondary .widget-area' ).masonry( {
			itemSelector: '.widget',
			columnWidth: columnWidth,
			gutterWidth: 20,
			isRTL: body.is( '.rtl' )
		} );
	}
} )( jQuery );