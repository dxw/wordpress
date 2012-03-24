jQuery( function($) {
	var id = typeof( current_site_id ) != 'undefined' ? '&site_id=' + current_site_id : '';

	$( '#adduser-email, #newuser' ).autocomplete({
		source:   ajaxurl + '?action=autocomplete-user&autocomplete_type=add' + id,
		delay:    500,
		minLength: 2
	});

	$( '#user-search-input' ).autocomplete({
		source:   ajaxurl + '?action=autocomplete-user&autocomplete_type=search' + id,
		delay:    500,
		minLength: 2
	});

	$( '#all-user-search-input' ).autocomplete({
		source:   ajaxurl + '?action=autocomplete-user&autocomplete_type=search-all' + id,
		delay:    500,
		minLength: 2
	});
});
