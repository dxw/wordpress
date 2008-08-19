<?php
/**
 * WordPress FTP Sockets Filesystem.
 *
 * @package WordPress
 * @subpackage Filesystem
 */

/**
 * WordPress Filesystem Class for implementing FTP Sockets.
 *
 * @since 2.5
 * @package WordPress
 * @subpackage Filesystem
 * @uses WP_Filesystem_Base Extends class
 */
class WP_Filesystem_ftpsockets extends WP_Filesystem_Base {
	var $ftp = false;
	var $timeout = 5;
	var $errors;
	var $options = array();

	var $permission = null;

	var $filetypes = array(
							'php' => FTP_ASCII,
							'css' => FTP_ASCII,
							'txt' => FTP_ASCII,
							'js'  => FTP_ASCII,
							'html'=> FTP_ASCII,
							'htm' => FTP_ASCII,
							'xml' => FTP_ASCII,

							'jpg' => FTP_BINARY,
							'png' => FTP_BINARY,
							'gif' => FTP_BINARY,
							'bmp' => FTP_BINARY
							);

	function WP_Filesystem_ftpsockets($opt = '') {
		$this->method = 'ftpsockets';
		$this->errors = new WP_Error();

		//Check if possible to use ftp functions.
		if( ! @include_once ABSPATH . 'wp-admin/includes/class-ftp.php' )
				return false;
		$this->ftp = new ftp();

		//Set defaults:
		if ( empty($opt['port']) )
			$this->options['port'] = 21;
		else
			$this->options['port'] = $opt['port'];

		if ( empty($opt['hostname']) )
			$this->errors->add('empty_hostname', __('FTP hostname is required'));
		else
			$this->options['hostname'] = $opt['hostname'];

		if ( isset($opt['base']) && ! empty($opt['base']) )
			$this->wp_base = $opt['base'];

		// Check if the options provided are OK.
		if ( empty ($opt['username']) )
			$this->errors->add('empty_username', __('FTP username is required'));
		else
			$this->options['username'] = $opt['username'];

		if ( empty ($opt['password']) )
			$this->errors->add('empty_password', __('FTP password is required'));
		else
			$this->options['password'] = $opt['password'];
	}

	function connect() {
		if ( ! $this->ftp )
			return false;

		//$this->ftp->Verbose = true;

		if ( ! $this->ftp->SetServer($this->options['hostname'], $this->options['port']) ) {
			$this->errors->add('connect', sprintf(__('Failed to connect to FTP Server %1$s:%2$s'), $this->options['hostname'], $this->options['port']));
			return false;
		}
		if ( ! $this->ftp->connect() ) {
			$this->errors->add('connect', sprintf(__('Failed to connect to FTP Server %1$s:%2$s'), $this->options['hostname'], $this->options['port']));
			return false;
		}

		if ( ! $this->ftp->login($this->options['username'], $this->options['password']) ) {
			$this->errors->add('auth', sprintf(__('Username/Password incorrect for %s'), $this->options['username']));
			return false;
		}

		$this->ftp->SetType(FTP_AUTOASCII);
		$this->ftp->Passive(true);
		return true;
	}

	function setDefaultPermissions($perm) {
		$this->permission = $perm;
	}

	function get_contents($file, $type = '', $resumepos = 0) {
		if( ! $this->exists($file) )
			return false;

		if( empty($type) ){
			$extension = substr(strrchr($file, '.'), 1);
			$type = isset($this->filetypes[ $extension ]) ? $this->filetypes[ $extension ] : FTP_AUTOASCII;
		}
		$this->ftp->SetType($type);
		$temp = wp_tempnam( $file );
		if ( ! $temphandle = fopen($temp, 'w+') )
			return false;
		if ( ! $this->ftp->fget($temphandle, $file) ) {
			fclose($temphandle);
			unlink($temp);
			return ''; //Blank document, File does exist, Its just blank.
		}
		fseek($temphandle, 0); //Skip back to the start of the file being written to
		$contents = '';
		while ( ! feof($temphandle) )
			$contents .= fread($temphandle, 8192);
		fclose($temphandle);
		unlink($temp);
		return $contents;
	}

	function get_contents_array($file) {
		return explode("\n", $this->get_contents($file) );
	}

	function put_contents($file, $contents, $type = '' ) {
		if( empty($type) ){
			$extension = substr(strrchr($file, '.'), 1);
			$type = isset($this->filetypes[ $extension ]) ? $this->filetypes[ $extension ] : FTP_AUTOASCII;
		}
		$this->ftp->SetType($type);

		$temp = wp_tempnam( $file );
		if ( ! $temphandle = fopen($temp, 'w+') ){
			unlink($temp);
			return false;
		}
		fwrite($temphandle, $contents);
		fseek($temphandle, 0); //Skip back to the start of the file being written to
		$ret = $this->ftp->fput($file, $temphandle);
		fclose($temphandle);
		unlink($temp);
		return $ret;
	}

	function cwd() {
		$cwd = $this->ftp->pwd();
		if( $cwd )
			$cwd = trailingslashit($cwd);
		return $cwd;
	}

	function chdir($file) {
		return $this->ftp->chdir($file);
	}

	function chgrp($file, $group, $recursive = false ) {
		return false;
	}

	function chmod($file, $mode = false, $recursive = false ) {
		if( ! $mode )
			$mode = $this->permission;
		if( ! $mode )
			return false;
		//if( ! $this->exists($file) )
		//	return false;
		if( ! $recursive || ! $this->is_dir($file) ) {
			return $this->ftp->chmod($file,$mode);
		}
		//Is a directory, and we want recursive
		$filelist = $this->dirlist($file);
		foreach($filelist as $filename){
			$this->chmod($file . '/' . $filename, $mode, $recursive);
		}
		return true;
	}

	function chown($file, $owner, $recursive = false ) {
		return false;
	}

	function owner($file) {
		$dir = $this->dirlist($file);
		return $dir[$file]['owner'];
	}

	function getchmod($file) {
		$dir = $this->dirlist($file);
		return $dir[$file]['permsn'];
	}

	function group($file) {
		$dir = $this->dirlist($file);
		return $dir[$file]['group'];
	}

	function copy($source, $destination, $overwrite = false ) {
		if( ! $overwrite && $this->exists($destination) )
			return false;

		$content = $this->get_contents($source);
		if ( false === $content )
			return false;

		return $this->put_contents($destination, $content);
	}

	function move($source, $destination, $overwrite = false ) {
		return $this->ftp->rename($source, $destination);
	}

	function delete($file, $recursive = false ) {
		if ( $this->is_file($file) )
			return $this->ftp->delete($file);
		if ( !$recursive )
			return $this->ftp->rmdir($file);

		return $this->ftp->mdel($file);
	}

	function exists($file) {
		return $this->ftp->is_exists($file);
	}

	function is_file($file) {
		return $this->is_dir($file) ? false : true;
	}

	function is_dir($path) {
		$cwd = $this->cwd();
		if ( $this->chdir($path) ) {
			$this->chdir($cwd);
			return true;
		}
		return false;
	}

	function is_readable($file) {
		//Get dir list, Check if the file is writable by the current user??
		return true;
	}

	function is_writable($file) {
		//Get dir list, Check if the file is writable by the current user??
		return true;
	}

	function atime($file) {
		return false;
	}

	function mtime($file) {
		return $this->ftp->mdtm($file);
	}

	function size($file) {
		return $this->ftp->filesize($file);
	}

	function touch($file, $time = 0, $atime = 0 ) {
		return false;
	}

	function mkdir($path, $chmod = false, $chown = false, $chgrp = false ) {
		if( ! $this->ftp->mkdir($path) )
			return false;
		if( $chmod )
			$this->chmod($path, $chmod);
		if( $chown )
			$this->chown($path, $chown);
		if( $chgrp )
			$this->chgrp($path, $chgrp);
		return true;
	}

	function rmdir($path, $recursive = false ) {
		if( ! $recursive )
			return $this->ftp->rmdir($path);

		return $this->ftp->mdel($path);
	}

	function dirlist($path = '.', $incdot = false, $recursive = false ) {
		if( $this->is_file($path) ) {
			$limitFile = basename($path);
			$path = dirname($path) . '/';
		} else {
			$limitFile = false;
		}

		$list = $this->ftp->dirlist($path);
		if( ! $list )
			return false;
		if( empty($list) )
			return array();

		$ret = array();
		foreach ( $list as $struc ) {

			if ( 'd' == $struc['type'] ) {
				$struc['files'] = array();

				if ( $incdot ){
					//We're including the doted starts
					if( '.' != $struc['name'] && '..' != $struc['name'] ){ //Ok, It isnt a special folder
						if ($recursive)
							$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $incdot, $recursive);
					}
				} else { //No dots
					if ($recursive)
						$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $incdot, $recursive);
				}
			}
			//File
			$ret[$struc['name']] = $struc;
		}
		return $ret;
	}

	function __destruct() {
		$this->ftp->quit();
	}
}

?>
