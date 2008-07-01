function add_postbox_toggles(page) {
	jQuery('.postbox h3').before('<a class="togbox">+</a> ');
	jQuery('.postbox a.togbox').click( function() { jQuery(jQuery(this).parent().get(0)).toggleClass('closed'); save_postboxes_state(page); } );

	var postingMetaBox = false; // post once, not once per sortable
	jQuery('.meta-box-sortables').sortable( {
		connectWith: [ '.meta-box-sortables' ],
		handle: 'h3',
		update: function() {
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
			} );
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
