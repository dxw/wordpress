<?php

// On which page are we ?
if ( is_admin() ) {
	// wp-admin pages are checked more carefully
	preg_match('#/wp-admin/?(.*?)$#i', $PHP_SELF, $self_matches);
	$pagenow = $self_matches[1];
	$pagenow = preg_replace('#\?.*?$#', '', $pagenow);
	if ( '' === $pagenow || 'index' === $pagenow || 'index.php' === $pagenow ) {
		$pagenow = 'index.php';
	} else {
		preg_match('#(.*?)(/|$)#', $pagenow, $self_matches);
		$pagenow = strtolower($self_matches[1]);
		if ( '.php' !== substr($pagenow, -4, 4) )
			$pagenow .= '.php'; // for Options +Multiviews: /wp-admin/themes/index.php (themes.php is queried)
	}
} else {
	if ( preg_match('#([^/]+\.php)([?/].*?)?$#i', $PHP_SELF, $self_matches) )
		$pagenow = strtolower($self_matches[1]);
	else
		$pagenow = 'index.php';
}

// Simple browser detection
$is_lynx = 0; $is_gecko = 0; $is_winIE = 0; $is_macIE = 0; $is_opera = 0; $is_NS4 = 0;

if (preg_match('/Lynx/', $_SERVER['HTTP_USER_AGENT'])) {
	$is_lynx = 1;
} elseif (preg_match('/Gecko/', $_SERVER['HTTP_USER_AGENT'])) {
	$is_gecko = 1;
} elseif ((preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) && (preg_match('/Win/', $_SERVER['HTTP_USER_AGENT']))) {
	$is_winIE = 1;
} elseif ((preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) && (preg_match('/Mac/', $_SERVER['HTTP_USER_AGENT']))) {
	$is_macIE = 1;
} elseif (preg_match('/Opera/', $_SERVER['HTTP_USER_AGENT'])) {
	$is_opera = 1;
} elseif ((preg_match('/Nav/', $_SERVER['HTTP_USER_AGENT']) ) || (preg_match('/Mozilla\/4\./', $_SERVER['HTTP_USER_AGENT']))) {
	$is_NS4 = 1;
}
$is_IE    = (($is_macIE) || ($is_winIE));

// Server detection
$is_apache = ( strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') || strstr($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') ) ? 1 : 0;
$is_IIS = strstr($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') ? 1 : 0;

// On OS X Server, $_SERVER['REMOTE_ADDR'] is the server's address. Workaround this 
// by using $_SERVER['HTTP_PC_REMOTE_ADDR'], which *is* the remote address.
if ( isset($_SERVER['HTTP_PC_REMOTE_ADDR']) )
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_PC_REMOTE_ADDR'];

// if the config file does not provide the smilies array, let's define it here
if (!isset($wpsmiliestrans)) {
	$wpsmiliestrans = array(
	':mrgreen:'	=> 'icon_mrgreen.gif',
	':neutral:'	=> 'icon_neutral.gif',
	':twisted:'	=> 'icon_twisted.gif',
	':arrow:'	=> 'icon_arrow.gif',
	':shock:'	=> 'icon_eek.gif',
	':smile:'	=> 'icon_smile.gif',
	' :???:'	=> 'icon_confused.gif',
	':cool:'	=> 'icon_cool.gif',
	':evil:'	=> 'icon_evil.gif',
	':grin:'	=> 'icon_biggrin.gif',
	':idea:'	=> 'icon_idea.gif',
	':oops:'	=> 'icon_redface.gif',
	':razz:'	=> 'icon_razz.gif',
	':roll:'	=> 'icon_rolleyes.gif',
	':wink:'	=> 'icon_wink.gif',
	':cry:'		=> 'icon_cry.gif',
	':eek:'		=> 'icon_surprised.gif',
	':lol:'		=> 'icon_lol.gif',
	':mad:'		=> 'icon_mad.gif',
	':sad:'		=> 'icon_sad.gif',
	' 8-)'		=> 'icon_cool.gif',
	' 8-O'		=> 'icon_eek.gif',
	' :-('		=> 'icon_sad.gif',
	' :-)'		=> 'icon_smile.gif',
	' :-?'		=> 'icon_confused.gif',
	' :-D'		=> 'icon_biggrin.gif',
	' :-P'		=> 'icon_razz.gif',
	' :-o'		=> 'icon_surprised.gif',
	' :-x'		=> 'icon_mad.gif',
	' :-|'		=> 'icon_neutral.gif',
	' ;-)'		=> 'icon_wink.gif',
	' 8)'		=> 'icon_cool.gif',
	' 8O'		=> 'icon_eek.gif',
	' :('		=> 'icon_sad.gif',
	' :)'		=> 'icon_smile.gif',
	' :?'		=> 'icon_confused.gif',
	' :D'		=> 'icon_biggrin.gif',
	' :P'		=> 'icon_razz.gif',
	' :o'		=> 'icon_surprised.gif',
	' :x'		=> 'icon_mad.gif',
	' :|'		=> 'icon_neutral.gif',
	' ;)'		=> 'icon_wink.gif',
	':!:'		=> 'icon_exclaim.gif',
	':?:'		=> 'icon_question.gif',
	);
}


// generates smilies' search & replace arrays
foreach($wpsmiliestrans as $smiley => $img) {
	$wp_smiliessearch[] = $smiley;
	$smiley_masked = htmlspecialchars( trim($smiley) , ENT_QUOTES);
	$wp_smiliesreplace[] = " <img src='" . get_settings('siteurl') . "/wp-includes/images/smilies/$img' alt='$smiley_masked' class='wp-smiley' /> ";
}

?>
