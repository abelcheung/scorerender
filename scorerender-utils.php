<?php

//
// Backported function: sys_get_temp_dir
// http://www.phpit.net/article/creating-zip-tar-archives-dynamically-php/2/
//
if (!function_exists ('sys_get_temp_dir'))
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
}

//
// Backported function: array_intersect_key
// http://www.php.net/manual/en/function.array-intersect-key.php#68179
//
if (!function_exists ('array_intersect_key'))
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
}


/**
 * Convenience function: Check if OS is Windows
 *
 * @since 0.2.50
 * return boolean True if OS is Windows, false otherwise.
 */
function is_windows ()
{
	return (substr(PHP_OS, 0, 3) == 'WIN');
}


/**
 * Transform path string to Windows or Unix presentation
 *
 * @since 0.2.50
 * @param string $path The path to be transformed
 * @param boolean $is_internal Whether to always transform into Unix format, which is used for storing values into database. FALSE means using OS native representation.
 * @uses is_windows
 * @return string $path The resulting path, with appropriate slashes or backslashes
 */
function get_path_presentation ($path, $is_internal)
{
	if (is_windows () && ! $is_internal)
		return preg_replace ('#/+#', '\\', $path);

	// FIXME: Japanese and Chinese users have to avoid non-UTF8 charsets
	return preg_replace ('#\\\\+#', '/', $path);
}

/**
 * Convenience function: Check if a path is aboslute path
 *
 * @since 0.2.50
 * @return boolean True if path is absolute, false otherwise.
 */
function is_absolute_path ($path)
{
	// FIXME: How about network shares on Windows?
	return ( (!is_windows() && (substr ($path, 0, 1) == '/')) ||
	         ( is_windows() && preg_match ('/^[A-Za-z]:/', $path) ) );
}

?>
