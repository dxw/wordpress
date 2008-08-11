// send html to the post editor
function send_to_editor(h) {
	if ( typeof tinyMCE != 'undefined' && ( ed = tinyMCE.activeEditor ) && !ed.isHidden() ) {
		ed.focus();
		if (tinymce.isIE)
			ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);

		if ( h.indexOf('[caption') != -1 )
			h = ed.plugins.wpeditimage._do_shcode(h);

		ed.execCommand('mceInsertContent', false, h);
	} else
		edInsertContent(edCanvas, h);

	tb_remove();
}

// thickbox settings
jQuery(function($) {
	tb_position = function() {
		var tbWindow = $('#TB_window');
		var w = $(window).width();
		var h = $(window).height();
		var H = ( 340 < h ) ? 340 : h;
		var W = ( 385 < w ) ? 385 : w;

		if ( tbWindow.size() ) {
			tbWindow.width( W - 50 ).height( H - 45 );
			$('#TB_iframeContent').width( W - 50 ).height( H - 75 );
			tbWindow.css({'margin-left': '-' + parseInt((( W - 50 ) / 2),10) + 'px'});
			if ( typeof document.body.style.maxWidth != 'undefined' )
				tbWindow.css({'margin-top': '-' + parseInt((( H - 10 ) / 2),10) + 'px'});
			$('#TB_title').css({'background-color':'#222','color':'#cfcfcf'});
			$('#TB_ajaxWindowTitle').text('Add Media').css({'fontSize':'16px','fontWeight':'bold','paddingTop':'3px'});
		};

		return $('a.thickbox').each( function() {
			var href = $(this).attr('href');
			if ( ! href ) return;
			href = href.replace(/&width=[0-9]+/g, '');
			href = href.replace(/&height=[0-9]+/g, '');
			$(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 ) );
		});
	};

	jQuery('a.thickbox').click(function(){
		if ( typeof tinyMCE != 'undefined' &&  tinyMCE.activeEditor ) {
			tinyMCE.get('content').focus();
			tinyMCE.activeEditor.windowManager.bookmark = tinyMCE.activeEditor.selection.getBookmark('simple');
		}
	});

//	$(window).resize( function() { tb_position() } );
});

