<?php
/**
 * @package TinyMCE
 * @author Moxiecode
 * @copyright Copyright © 2005-2006, Moxiecode Systems AB, All rights reserved.
 */

/** @ignore */
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );
header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
<title><?php _e('Editor Help'); ?></title>

<?php wp_admin_css( 'wp-admin', true ); ?>
<style type="text/css">

	html {
		background: #fcfcfc;
		overflow: hidden;
	}

	body {
		min-width: 0;
	}

	.wp-core-ui #tabs {
		margin-top: 1px;
		padding: 0 6px;
		position: absolute;
		top: 0;
		right: 0;
		left: 0;
		height: 31px;
		z-index: 1;
	}

	.wp-core-ui #tabs a {
		position: relative;
		float: left;
		padding: 6px 10px;
		margin: 0;
		height: 18px;
		line-height: 18px;
		text-decoration: none;
		-webkit-transition: none;
		transition: none;
	}

	#tabs a.active {
		margin: -1px -1px 0;
		background: #fff;
		border: 1px solid #ddd;
		border-bottom: none;
		color: #333;
	}

	#tabs .active:after {
		display: none;
	}

	#flipper {
		background-color: #fff;
		border-top: 1px solid #ddd;
		height: 360px;
		margin: 0;
		margin-top: 30px;
		overflow: auto;
		padding: 10px 16px;
	}

	th {
		text-align: center;
	}

	.top th {
		text-decoration: underline;
	}

	.top .key {
		text-align: center;
		width: 5em;
	}

	.keys {
		border: 0 none;
		margin-bottom: 15px;
		width: 100%;
	}

	.keys p {
		display: inline-block;
		margin: 0px;
		padding: 0px;
	}

	.keys .left {
		text-align: left;
	}

	.keys .center {
		text-align: center;
	}

	.keys .right {
		text-align: right;
	}

	.macos .win,
	.windows .mac {
		display: none;
	}

</style>
<?php if ( is_rtl() ) : ?>
<style type="text/css">

	.keys .left {
		text-align: right;
	}

	.keys .right {
		text-align: left;
	}

</style>
<?php endif; ?>
</head>
<body class="windows wp-core-ui">
<script type="text/javascript">
var win = window.dialogArguments || opener || parent || top;

if ( win && win.tinymce && win.tinymce.isMac ) {
	document.body.className = document.body.className.replace(/windows/, 'macos');
}
</script>

<div id="tabs">
	<a id="tab1" href="javascript:flipTab(1)" title="<?php esc_attr_e('Basics of Rich Editing'); ?>" accesskey="1" class="active"><?php _e('Basics'); ?></a>
	<a id="tab2" href="javascript:flipTab(2)" title="<?php esc_attr_e('Advanced use of the Rich Editor'); ?>" accesskey="2"><?php _e('Advanced'); ?></a>
	<a id="tab3" href="javascript:flipTab(3)" title="<?php esc_attr_e('Hotkeys'); ?>" accesskey="3"><?php _e('Hotkeys'); ?></a>
	<a id="tab4" href="javascript:flipTab(4)" title="<?php esc_attr_e('About the software'); ?>" accesskey="4"><?php _e('About'); ?></a>
</div>

<div id="flipper" class="wrap">

<div id="content1">
	<h2><?php _e('Rich Editing Basics'); ?></h2>
	<p><?php _e('<em>Rich editing</em>, also called WYSIWYG for What You See Is What You Get, means your text is formatted as you type. The rich editor creates HTML code behind the scenes while you concentrate on writing. Font styles, links and images all appear approximately as they will on the internet.'); ?></p>
	<p><?php _e('WordPress includes a rich HTML editor that works well in all major web browsers used today. However, editing HTML is not the same as typing text.' );
		echo ' ' . __( 'Each web page has two major components: the structure, which is the actual HTML code and is produced by the editor as you type, and the display, that is applied to it by the currently selected WordPress theme and is defined in style.css.' );
		echo ' ' . __( 'WordPress is producing valid HTML 5 which means that inserting multiple line breaks (BR tags) after a paragraph would not produce white space on the web page. The BR tags will be removed as invalid by the internal HTML correcting functions.');
	?></p>
	<p><?php _e('While using the editor, most basic keyboard shortcuts work like in any other text editor. For example: Shift+Enter inserts line break, Ctrl+C = copy, Ctrl+X = cut, Ctrl+Z = undo, Ctrl+Y = redo, Ctrl+A = select all, etc. (on Mac use the Command key instead of Ctrl). See the Hotkeys tab for all available keyboard shortcuts.'); ?></p>
	<p><?php _e('If you do not like the way the rich editor works, you may turn it off from Your Profile submenu, under Users in the admin menu.'); ?></p>
</div>

<div id="content2" class="hidden">
	<h2><?php _e('Advanced Rich Editing'); ?></h2>
	<h3><?php _e('Images and Attachments'); ?></h3>
	<p><?php _e( 'If you want to upload an image or another media file from your computer, you can use the Add Media button above the editor. Select Upload Files or drag the files you wish to upload from the desktop and drop them into the browser window.' );
		echo ' ' . __( 'To insert your image into the post, first click on the thumbnail to reveal a menu of options. When you have selected the options you like, click "Insert into Post" and your image or file will appear in the post you are editing.' );
	?><p>
	<p><?php _e( 'The Add Media button can also be used for inserting images that are already hosted somewhere on the internet. If you have a URL for an image, click this button, select Insert from URL, and enter the URL in the box which appears.' ); ?></p>
	<h3><?php _e('HTML in the Rich Editor'); ?></h3>
	<p><?php _e('Any HTML entered directly into the rich editor will show up as text when the post is viewed. What you see is what you get. When you want to include HTML elements that cannot be generated with the toolbar buttons, you must enter it by hand in the Text editor. Examples are tables and &lt;code&gt;. To do this, click the Text tab and edit the code, then switch back to Visual mode. If the code is valid and understood by the editor, you should see it rendered immediately.'); ?></p>
	<h3><?php _e('Pasting in the Rich Editor'); ?></h3>
	<p><?php _e('When pasting content from another web page the results can be inconsistent and depend on your browser and on the web page you are pasting from. The editor tries to correct any invalid HTML code that was pasted, but for best results try using the Text tab or the "Paste as text" button on the second row. Alternatively try pasting paragraph by paragraph. In most browsers to select one paragraph at a time, triple-click on it.'); ?></p>
	<p><?php _e('Pasting content from another application, like Word or Excel, is automatically handled. If you want it to appear as plain text instead, you can use "Paste as text" button on the second row, or paste directly in Text mode.'); ?></p>
</div>

<div id="content3" class="hidden">
	<h2><?php _e('Writing at Full Speed'); ?></h2>
	<p><?php _e('Rather than reaching for your mouse to click on the toolbar, use these access keys. Windows and Linux use Ctrl + letter. Macintosh uses Command + letter.'); ?></p>

	<table class="keys">
		<tr class="top"><th class="key center"><?php _e('Letter'); ?></th><th class="left"><?php _e('Action'); ?></th><th class="key center"><?php _e('Letter'); ?></th><th class="left"><?php _e('Action'); ?></th></tr>
		<tr><th>c</th><td><?php _e('Copy'); ?></td><th>v</th><td><?php _e('Paste'); ?></td></tr>
		<tr><th>a</th><td><?php _e('Select all'); ?></td><th>x</th><td><?php _e('Cut'); ?></td></tr>
		<tr><th>z</th><td><?php _e('Undo'); ?></td><th>y</th><td><?php _e('Redo'); ?></td></tr>
		<tr><th>b</th><td><?php _e('Bold'); ?></td><th>i</th><td><?php _e('Italic'); ?></td></tr>
		<tr><th>u</th><td><?php _e('Underline'); ?></td><th>1</th><td><?php _e('Heading 1'); ?></td></tr>
		<tr><th>2</th><td><?php _e('Heading 2'); ?></td><th>3</th><td><?php _e('Heading 3'); ?></td></tr>
		<tr><th>4</th><td><?php _e('Heading 4'); ?></td><th>5</th><td><?php _e('Heading 5'); ?></td></tr>
		<tr><th>6</th><td><?php _e('Heading 6'); ?></td><th>9</th><td><?php _e('Address'); ?></td></tr>
		<tr><th>k</th><td><?php _e('Insert/edit link'); ?></td><th> </th><td>&nbsp;</td></tr>
	</table>

	<p><?php _e('The following shortcuts use different access keys: Alt + Shift + letter.'); ?></p>
	<table class="keys">
		<tr class="top"><th class="key center"><?php _e('Letter'); ?></th><th class="left"><?php _e('Action'); ?></th><th class="key center"><?php _e('Letter'); ?></th><th class="left"><?php _e('Action'); ?></th></tr>
		<tr><th>n</th><td><?php _e('Check Spelling'); ?></td><th>l</th><td><?php _e('Align Left'); ?></td></tr>
		<tr><th>j</th><td><?php _e('Justify Text'); ?></td><th>c</th><td><?php _e('Align Center'); ?></td></tr>
		<tr><th>d</th><td><span style="text-decoration: line-through;"><?php _e('Strikethrough'); ?></span></td><th>r</th><td><?php _e('Align Right'); ?></td></tr>
		<tr><th>u</th><td><strong>&bull;</strong> <?php _e('List'); ?></td><th>a</th><td><?php _e('Insert link'); ?></td></tr>
		<tr><th>o</th><td>1. <?php _e('List'); ?></td><th>s</th><td><?php _e('Remove link'); ?></td></tr>
		<tr><th>q</th><td><?php _e('Quote'); ?></td><th>m</th><td><?php _e('Insert Image'); ?></td></tr>
		<tr><th>w</th><td><?php _e('Distraction Free Writing mode'); ?></td><th>t</th><td><?php _e('Insert More Tag'); ?></td></tr>
		<tr><th>p</th><td><?php _e('Insert Page Break tag'); ?></td><th>h</th><td><?php _e('Help'); ?></td></tr>
		<tr><th>x</th><td><?php _e('Add/remove code tag'); ?></td><th> </th><td>&nbsp;</td></tr>
	</table>

	<p style="padding: 15px 10px 10px;"><?php _e('Editor width in Distraction Free Writing mode:'); ?></p>
	<table class="keys">
		<tr><th><span class="win">Alt +</span><span class="mac">Ctrl +</span></th><td><?php _e('Wider'); ?></td>
			<th><span class="win">Alt -</span><span class="mac">Ctrl -</span></th><td><?php _e('Narrower'); ?></td></tr>
		<tr><th><span class="win">Alt 0</span><span class="mac">Ctrl 0</span></th><td><?php _e('Default width'); ?></td><th></th><td></td></tr>
	</table>
</div>

<div id="content4" class="hidden">
	<h2><?php _e('About TinyMCE'); ?></h2>

	<p><?php _e('Version:'); ?> <span id="version"></span> (<span id="date"></span>)</p>
	<p><?php printf(__('TinyMCE is a platform independent web based Javascript HTML WYSIWYG editor released as Open Source under %sLGPL</a>	by Moxiecode Systems AB. It has the ability to convert HTML TEXTAREA fields or other HTML elements to editor instances.'), '<a href="'.home_url('/wp-includes/js/tinymce/license.txt').'" target="_blank" title="'.esc_attr__('GNU Library General Public License').'">'); ?></p>
	<p><?php _e('Copyright &copy; 2003-2014, <a href="http://www.moxiecode.com" target="_blank">Moxiecode Systems AB</a>, All rights reserved.'); ?></p>
	<p><?php _e('For more information about this software visit the <a href="http://tinymce.com" target="_blank">TinyMCE website</a>.'); ?></p>
</div>

</div>
<script type="text/javascript">
	function d(id) {
		return document.getElementById(id);
	}

	function flipTab(n) {
		var i, c, t;

		for ( i = 1; i <= 4; i++ ) {
			c = d('content'+i.toString());
			t = d('tab'+i.toString());
			if ( n == i ) {
				c.className = '';
				t.className = 'active';
			} else {
				c.className = 'hidden';
				t.className = '';
			}
		}
	}

	if ( win && win.tinymce ) {
		d('version').innerHTML = win.tinymce.majorVersion + "." + win.tinymce.minorVersion;
		d('date').innerHTML = win.tinymce.releaseDate;
	}
</script>
</body>
</html>
