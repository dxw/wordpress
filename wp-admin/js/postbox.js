function add_postbox_toggles(page) {
	jQuery('.postbox h3').before('<a class="togbox">+</a> ');
	jQuery('.postbox a.togbox').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); save_postboxes_state(page); } );

	jQuery('#side-sortables').append( '<div id="make-it-tall" style="margin-bottom: -2000px; padding-bottom: 2001px"></div>' );
	jQuery('#wpbody-content').css( 'overflow', 'hidden' );

	var expandSidebar = function( doIt ) {
		if ( doIt || jQuery.trim( jQuery( '#side-info-column' ).text() ) ) {
			jQuery( '#post-body' ).addClass( 'has-sidebar' );
		} else {
			jQuery( '#post-body' ).removeClass( 'has-sidebar' );
		}
	};

	var postingMetaBox = false; // post once, not once per sortable
	jQuery('.meta-box-sortables').sortable( {
		connectWith: [ '.meta-box-sortables' ],
		items: '> .postbox',
		handle: 'h3',
		update: function() {
			if ( 'side-sortables' == this.id ) { // doing this with jQuery doesn't work for some reason: make-it-tall gets duplicated
				var makeItTall = document.getElementById( 'make-it-tall' );
				var sideSort = makeItTall.parentNode;
				sideSort.removeChild( makeItTall );
				sideSort.appendChild( makeItTall );
				
			}
			if ( postingMetaBox ) {
				return;
			}
			postingMetaBox = true;
			var postVars = {
				action: 'meta-box-order',
				_ajax_nonce: jQuery('#meta-box-order-nonce').val(),
				page: page
			}
			jQuery('.meta-box-sortables').each( function() {
				postVars["order[" + this.id.split('-')[0] + "]"] = jQuery(this).sortable( 'toArray' ).join(',');
			} );
			jQuery.post( postboxL10n.requestFile, postVars, function() {
				postingMetaBox = false;
				expandSidebar();
			} );
		},
		over: function(e, ui) {
			if ( !ui.element.is( '#side-sortables' ) )
				return;
			expandSidebar( true );
		}
	} );
}

function save_postboxes_state(page) {
	var closed = jQuery('.postbox').filter('.closed').map(function() { return this.id; }).get().join(',');
	jQuery.post(postboxL10n.requestFile, {
		action: 'closed-postboxes',
		closed: closed,
		closedpostboxesnonce: jQuery('#closedpostboxesnonce').val(),
		page: page
	});
}
