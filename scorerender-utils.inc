<?php

/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
 */

// Backported function: sys_get_temp_dir
// http://www.phpit.net/article/creating-zip-tar-archives-dynamically-php/2/
//
if (!function_exists ('sys_get_temp_dir')) /* {{{ */
{
	/**
	 * @ignore
	 */
	function sys_get_temp_dir ()
	{
		// Try to get from environment variable
		if ( !empty($_ENV['TMP']) )
			return realpath( $_ENV['TMP'] );
		elseif ( !empty($_ENV['TMPDIR']) )
			return realpath( $_ENV['TMPDIR'] );
		elseif ( !empty($_ENV['TEMP']) )
			return realpath( $_ENV['TEMP'] );
		else
		{
			// Detect by creating a temporary file and pick its dir name
			$temp_file = tempnam( md5(uniqid(rand(), TRUE)), '' );
			if ( !$temp_file) return false;

			unlink( $temp_file );
			return realpath( dirname($temp_file) );
		}
	}
} /* }}} */

// Backported function: array_intersect_key
// http://www.php.net/manual/en/function.array-intersect-key.php#68179
//
if (!function_exists ('array_intersect_key')) /* {{{ */
{
	/**
	 * @ignore
	 */
	function array_intersect_key ()
	{
		$arrs = func_get_args ();
		$result = array_shift ($arrs);
		foreach ($arrs as $array)
			foreach (array_keys ($result) as $key)
				if (!array_key_exists ($key, $array))
					unset ($result[$key]);
		return $result;
	}
} /* }}} */


/**
 * Convenience function: Check if OS is Windows
 *
 * @since 0.3
 * return boolean True if OS is Windows, false otherwise.
 */
function is_windows ()
{
	return (substr(PHP_OS, 0, 3) == 'WIN');
}


/**
 * Transform path string to Windows or Unix presentation
 *
 * @since 0.3
 * @param string $path The path to be transformed
 * @param boolean $is_internal Whether to always transform into Unix format, which is used for storing values into database. FALSE means using OS native representation.
 * @uses is_windows()
 * @return string $path The resulting path, with appropriate slashes or backslashes
 */
function get_path_presentation ($path, $is_internal) /* {{{ */
{
	if (is_windows () && ! $is_internal)
		return preg_replace ('#/+#', '\\', $path);

	// TODO: Check how CJK chars are handled in paths
	return preg_replace ('#\\\\+#', '/', $path);
} /* }}} */


/**
 * Create temporary directory
 *
 * Inspired from PHP tempnam documentation comment
 *
 * @uses sys_get_temp_dir()
 * @param string $dir Base directory on which temp folder is created
 * @param string $prefix Prefix of temp directory
 * @param integer $mode Access mode of temp directory
 * @return string Full path of created temp directory, or FALSE on failure
 */
function create_temp_dir ($dir = '', $prefix = '', $mode = 0700) /* {{{ */
{
	if ( !is_dir ($dir) || !is_writable ($dir) )
		$dir = sys_get_temp_dir ();

	if (!empty ($dir)) $dir = trailingslashit ($dir);

	// Not secure indeed. But PHP doesn't provide facility to create temp folder anyway.
	$chars = str_split ("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
	$i = 0;

	do {
		$path = $dir . $prefix . sprintf ("%s%s%s%s%s%s",
			$chars[mt_rand(0,51)], $chars[mt_rand(0,51)], $chars[mt_rand(0,51)],
			$chars[mt_rand(0,51)], $chars[mt_rand(0,51)], $chars[mt_rand(0,51)]);
	}
	while (!@mkdir ($path, $mode) && (++$i < 100));

	return ($i < 100) ? $path : FALSE;
} /* }}} */


/**
 * Search for executables in system and return full path of its location
 *
 * It will try to search in PATH envioronment variable first; after failing that,
 * use glob() to search for all paths supplied by user, and finally some more
 * guess work among common apps locations.
 *
 * @since 0.3.50
 * @uses is_windows()
 *
 * @param string $prog The program name to be searched
 * @param array|string $extra_win_paths Array containing paths to be searched,
 * also accepts a %PATH%-like string
 * @param array|string $extra_unix_paths Similar to previous argument, but
 * conforms to unix $PATH style if specified as string
 *
 * @return string|boolean Full path of program if it is found, FALSE otherwise
 */
function search_prog ($binary_name, $extra_win_paths = array(), $extra_unix_paths = array()) /* {{{ */
{
	if ( is_windows() ) $binary_name .= '.exe';

	foreach ( explode( PATH_SEPARATOR, getenv('PATH')) as $dir )
	{
		$fullpath = realpath ($dir . DIRECTORY_SEPARATOR . $binary_name);
		if ( is_executable ( $fullpath ) ) return $fullpath;
	}

	// game over if glob() is disabled
	if ( !function_exists ('glob') ) return $fullpath;

	if ( is_windows() )
	{
		if ( is_string ($extra_win_paths) )
			$extra_paths = explode ( PATH_SEPARATOR, $extra_win_paths );
		elseif ( !is_array ($extra_win_paths) )
			$extra_paths = array();

		$extra_paths[] = "C:\\Program Files";
		$extra_paths[] = "C:\\Program Files\\*";
		$extra_paths[] = "C:\\cygwin*\\bin";
	}
	else
	{
		if ( is_string ($extra_unix_paths) )
			$extra_paths = explode ( PATH_SEPARATOR, $extra_unix_paths );
		elseif ( !is_array ($extra_unix_paths) )
			$extra_paths = array();

		$extra_paths[] = "/usr/local/bin";
		$extra_paths[] = "/opt/bin";
		$extra_paths[] = "/opt/*/bin";
	}

	foreach ( array_values ($extra_paths) as $dir )
	{
		$fullpath = glob ($dir . DIRECTORY_SEPARATOR . $binary_name);
		if ( !empty ($fullpath) )
		{
			foreach ($fullpath as $prog)
			{
				$prog = realpath ($prog);
				if ( is_executable ($prog) ) return $prog;
			}
		}
	}
	return false;
} /* }}} */


/**
 * Transform all path related options in ScoreRender settings
 *
 * @since 0.3
 * @uses get_path_presentation()
 * @uses scorerender_get_def_settings()
 * @param array $setting The settings to be transformed, either from
 * existing setting or from newly submitted setting
 * @param boolean $is_internal Whether to always transform into Unix format,
 * which is used for storing values into database.
 * FALSE means using OS native representation.
 */
function transform_paths (&$setting, $is_internal) /* {{{ */
{
	if (!is_array ($setting)) return;

	$default_settings = scorerender_get_def_settings(SrNotationBase::TYPES_ONLY);

	// Transform path and program settings to unix presentation
	foreach ($default_settings as $key => $type)
		if ( in_array ( $type, array ('path', 'prog', 'midiprog') ) && isset( $setting[$key] ) )
			$setting[$key] = get_path_presentation ($setting[$key], $is_internal);

} /* }}} */


/**
 * Convert linebreak to appropriate one depending on OS
 *
 * @since 0.3.50
 * @uses is_windows()
 * @param string $content Original content to be converted
 * @return string Content with appropriate linebreak
 */
function normalize_linebreak ($content) /* {{{ */
{
	if ( is_windows() )
	{
		$content = preg_replace ("/(?<!\r)\n/", "\r\n", $content);
		$content = preg_replace ("/\r(?!\n)/", "\r\n", $content);
	}
	else
	{
		$content = preg_replace ("/\r\n/", "\n", $content);
		$content = preg_replace ("/\r/", "\n", $content);
	}

	return $content;
} /* }}} */

/* vim: set cindent foldmethod=marker : */
?>
