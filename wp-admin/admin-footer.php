<?php
/**
 * WordPress Administration Template Footer
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! $fixed_bar ) { ?>
<br class="clear" /></div><!-- wpbody-content -->
<?php } ?>

</div><!-- wpbody -->
</div><!-- wpcontent -->
</div><!-- wpwrap -->
<div id="footer">
<p><?php
do_action('in_admin_footer', '');
$upgrade = apply_filters( 'update_footer', '' );
echo __('Thank you for creating with <a href="http://wordpress.org/">WordPress</a>').' | '.__('<a href="http://codex.wordpress.org/">Documentation</a>').' | '.__('<a href="http://wordpress.org/support/forum/4">Feedback</a>').' '.$upgrade;
?></p>
</div>
<?php do_action('admin_footer', ''); ?>
<script type="text/javascript">if(typeof wpOnload=='function')wpOnload();</script>
</body>
</html>
