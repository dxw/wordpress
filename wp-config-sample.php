<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'db_cc482220');

/** MySQL database username */
define('DB_USER', 'user_cc482220');

/** MySQL database password */
define('DB_PASSWORD', 'UbGR$zc,P!8l2f');

/** MySQL hostname */
define('DB_HOST', '10.194.111.8');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'q!-WXA;o;P7y&17-pfZP%]2#RQ+3AmegB9NI|$#U>9F<;W09pTZNhd-tc7!JR@#0');
define('SECURE_AUTH_KEY',  'Y%<fwt0D?e#SP@I]( pB~uCqnB4T5g8bZnj5%%/|(JpF=[&0 nVW<0;bR/;)~C#j');
define('LOGGED_IN_KEY',    '6o6<cBXk;n:f?oQnnp*[eh1:xPoYbQQ:@?DpYYjkRFc^9A~E$C!~r59-!CJZNIYU');
define('NONCE_KEY',        'Bf +G3YW8Xxu2:M}8-/@.H~&6C2Fwo0b$E*FUR6E$@dbH5XN:^R6ea;Wdc!5A%R2');
define('AUTH_SALT',        'ItD4cnXl*JU@4|Tc8X{&;>1o,mDopOT}UT$xyW{_JN*acncUk1CleF<Ep&i]q!+s');
define('SECURE_AUTH_SALT', '23hy.~iFL8NY6ugF;6^:4pk#]~_n]}TGiPGi%x1bg?M-l&]@|&P>iVYy-%;3F0h.');
define('LOGGED_IN_SALT',   '-3/+35,kW71GiO;i-*<J0@7++>M=:aH|Jd]++Lj+)K4wi0K.rKGW;|/}.cQAdkx%');
define('NONCE_SALT',       '9wA;h{pYsH$>|El!^MiN/o$$,hI6J}(=OIyFhMXH;G<Cfa&8+{|L5`QHy-F,UnA1');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
