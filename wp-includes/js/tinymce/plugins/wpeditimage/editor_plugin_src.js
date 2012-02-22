
(function() {
	tinymce.create('tinymce.plugins.wpEditImage', {

		init : function(ed, url) {
			var t = this, mouse = {};

			t.url = url;
			t._createButtons();

			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('...');
			ed.addCommand('WP_EditImage', function() {
				var el = ed.selection.getNode(), vp, H, W, cls = ed.dom.getAttrib(el, 'class');

				if ( cls.indexOf('mceItem') != -1 || cls.indexOf('wpGallery') != -1 || el.nodeName != 'IMG' )
					return;

				vp = tinymce.DOM.getViewPort();
				H = 680 < (vp.h - 70) ? 680 : vp.h - 70;
				W = 650 < vp.w ? 650 : vp.w;

				ed.windowManager.open({
					file: url + '/editimage.html',
					width: W+'px',
					height: H+'px',
					inline: true
				});
			});

			ed.onInit.add(function(ed) {
				tinymce.dom.Event.add(ed.getBody(), 'dragstart', function(e) {
					if ( !tinymce.isGecko && e.target.nodeName == 'IMG' && ed.dom.getParent(e.target, 'dl.wp-caption') )
						return tinymce.dom.Event.cancel(e);
				});
			});

			// resize the caption <dl> when the image is soft-resized by the user (only possible in Firefox and IE)
			ed.onMouseUp.add(function(ed, e) {
				if ( tinymce.isWebKit || tinymce.isOpera )
					return;

				if ( mouse.x && (e.clientX != mouse.x || e.clientY != mouse.y) ) {
					var n = ed.selection.getNode();

					if ( 'IMG' == n.nodeName ) {
						window.setTimeout(function(){
							var DL, width;

							if ( n.width != mouse.img_w || n.height != mouse.img_h )
								n.className = n.className.replace(/size-[^ "']+/, '');

							if ( ed.dom.getParent(n, 'div.mceTemp') ) {
								DL = ed.dom.getParent(n, 'dl.wp-caption');

								if ( DL ) {
									width = ed.dom.getAttrib(n, 'width') || n.width;
									width = parseInt(width, 10);
									ed.dom.setStyle(DL, 'width', 10 + width);
									ed.execCommand('mceRepaint');
								}

							}
						}, 100);
					}
				}
				mouse = {};
			});

			// show editimage buttons
			ed.onMouseDown.add(function(ed, e) {

				if ( e.target && ( e.target.nodeName == 'IMG' || (e.target.firstChild && e.target.firstChild.nodeName == 'IMG') ) ) {
					mouse = {
						x: e.clientX,
						y: e.clientY,
						img_w: e.target.clientWidth,
						img_h: e.target.clientHeight
					};

					if ( ed.dom.getAttrib(e.target, 'class').indexOf('mceItem') == -1 )
						ed.plugins.wordpress._showButtons(e.target, 'wp_editbtns');
				}
			});

			// when pressing Return inside a caption move the cursor to a new parapraph under it
			ed.onKeyPress.add(function(ed, e) {
				var n, DL, DIV, P;

				if ( e.keyCode == 13 ) {
					n = ed.selection.getNode();
					DL = ed.dom.getParent(n, 'dl.wp-caption');
					DIV = ed.dom.getParent(DL, 'div.mceTemp');

					if ( DL && DIV ) {
						P = ed.dom.create('p', {}, '&nbsp;');
						ed.dom.insertAfter( P, DIV );
						
						if ( P.firstChild )
							ed.selection.select(P.firstChild);
						else
							ed.selection.select(P);
						
						tinymce.dom.Event.cancel(e);
						return false;
					}
				}
			});

			ed.onBeforeSetContent.add(function(ed, o) {
				o.content = t._do_shcode(o.content);
			});

			ed.onPostProcess.add(function(ed, o) {
				if (o.get)
					o.content = t._get_shcode(o.content);
			});
		},

		_do_shcode : function(co) {
			return co.replace(/(?:<p>)?\[(?:wp_)?caption([^\]]+)\]([\s\S]+?)\[\/(?:wp_)?caption\](?:<\/p>)?[\s\u00a0]*/g, function(a,b,c){
				var id, cls, w, cap, div_cls;
				
				b = b.replace(/\\'|\\&#39;|\\&#039;/g, '&#39;').replace(/\\"|\\&quot;/g, '&quot;');
				c = c.replace(/\\&#39;|\\&#039;/g, '&#39;').replace(/\\&quot;/g, '&quot;');
				id = b.match(/id=['"]([^'"]+)/i);
				cls = b.match(/align=['"]([^'"]+)/i);
				w = b.match(/width=['"]([0-9]+)/);
				cap = b.match(/caption=['"]([^'"]+)/i);

				id = ( id && id[1] ) ? id[1] : '';
				cls = ( cls && cls[1] ) ? cls[1] : 'alignnone';
				w = ( w && w[1] ) ? w[1] : '';
				cap = ( cap && cap[1] ) ? cap[1] : '';
				if ( ! w || ! cap ) return c;
				
				div_cls = (cls == 'aligncenter') ? 'mceTemp mceIEcenter' : 'mceTemp';

				return '<div class="'+div_cls+'" draggable><dl id="'+id+'" class="wp-caption '+cls+'" style="width: '+(10+parseInt(w))+
				'px"><dt class="wp-caption-dt">'+c+'</dt><dd class="wp-caption-dd">'+cap+'</dd></dl></div>';
			});
		},

		_get_shcode : function(co) {
			return co.replace(/<div class="mceTemp[^"]*">\s*<dl([^>]+)>\s*<dt[^>]+>([\s\S]+?)<\/dt>\s*<dd[^>]+>(.+?)<\/dd>\s*<\/dl>\s*<\/div>\s*/gi, function(a,b,c,cap){
				var id, cls, w;
				
				id = b.match(/id=['"]([^'"]+)/i);
				cls = b.match(/class=['"]([^'"]+)/i);
				w = c.match(/width=['"]([0-9]+)/);

				id = ( id && id[1] ) ? id[1] : '';
				cls = ( cls && cls[1] ) ? cls[1] : 'alignnone';
				w = ( w && w[1] ) ? w[1] : '';

				if ( ! w || ! cap ) return c;
				cls = cls.match(/align[^ '"]+/) || 'alignnone';
				cap = cap.replace(/<\S[^<>]*>/gi, '').replace(/'/g, '&#39;').replace(/"/g, '&quot;');

				return '[caption id="'+id+'" align="'+cls+'" width="'+w+'" caption="'+cap+'"]'+c+'[/caption]';
			});
		},

		_createButtons : function() {
			var t = this, ed = tinyMCE.activeEditor, DOM = tinymce.DOM, editButton, dellButton;

			DOM.remove('wp_editbtns');

			DOM.add(document.body, 'div', {
				id : 'wp_editbtns',
				style : 'display:none;'
			});

			editButton = DOM.add('wp_editbtns', 'img', {
				src : t.url+'/img/image.png',
				id : 'wp_editimgbtn',
				width : '24',
				height : '24',
				title : ed.getLang('wpeditimage.edit_img')
			});

			tinymce.dom.Event.add(editButton, 'mousedown', function(e) {
				var ed = tinyMCE.activeEditor;
				ed.windowManager.bookmark = ed.selection.getBookmark('simple');
				ed.execCommand("WP_EditImage");
			});

			dellButton = DOM.add('wp_editbtns', 'img', {
				src : t.url+'/img/delete.png',
				id : 'wp_delimgbtn',
				width : '24',
				height : '24',
				title : ed.getLang('wpeditimage.del_img')
			});

			tinymce.dom.Event.add(dellButton, 'mousedown', function(e) {
				var ed = tinyMCE.activeEditor, el = ed.selection.getNode(), p;

				if ( el.nodeName == 'IMG' && ed.dom.getAttrib(el, 'class').indexOf('mceItem') == -1 ) {
					if ( (p = ed.dom.getParent(el, 'div')) && ed.dom.hasClass(p, 'mceTemp') )
						ed.dom.remove(p);
					else if ( (p = ed.dom.getParent(el, 'A')) && p.childNodes.length == 1 )
						ed.dom.remove(p);
					else
						ed.dom.remove(el);

					ed.execCommand('mceRepaint');
					return false;
				}
			});
		},

		getInfo : function() {
			return {
				longname : 'Edit Image',
				author : 'WordPress',
				authorurl : 'http://wordpress.org',
				infourl : '',
				version : "1.0"
			};
		}
	});

	tinymce.PluginManager.add('wpeditimage', tinymce.plugins.wpEditImage);
})();
