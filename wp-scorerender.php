<?php
/*
Plugin Name: ScoreRender
Plugin URI: http://scorerender.abelcheung.org/
Description: Renders inline music score fragments in WordPress. Heavily based on <a href="http://chris-lamb.co.uk/code/figurerender/">FigureRender</a> from Chris Lamb.
Author: Abel Cheung
Version: 0.1.99
Author URI: http://me.abelcheung.org/
*/

/*
 ScoreRender - Renders inline music score fragments in WordPress
 Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 Copyright (C) 2007 Abel Cheung <abelcheung at gmail dot com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/*
 Mostly based on wp-figurerender.php from FigureRender
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006
*/


/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.1.99
 * @author Abel Cheung <abelcheung@gmail.com>
 * @copyright Copyright (C) 2007 Abel Cheung
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/**
 * Database version.
 *
 * This number must be incremented every time when option has been changed, removed or added.
 */
define ('DATABASE_VERSION', 8);

/**
 * Most apps hardcode DPI value to 72 dot per inch
 */
define ('DPI', 72.0);

/*
 * Error constants
 */


/**
 * Error constant used when content is known to be invalid or dangerous.
 *
 * Dangerous content means special constructs causing
 * inclusion of another file or command execution, etc.
 */
define ('ERR_INVALID_INPUT'               , -1);

/**
 * Error constant used when cache directory is not writable.
 */
define ('ERR_CACHE_DIRECTORY_NOT_WRITABLE', -2);

/**
 * Error constant used when temporary working directory is not writable.
 */
define ('ERR_TEMP_DIRECTORY_NOT_WRITABLE' , -3);

/**
 * Error constant used when temporary file is not writable.
 *
 * This error is very rare, most usually it's the directory (not file) which is not writable.
 */
define ('ERR_TEMP_FILE_NOT_WRITABLE'      , -4);

/**
 * Error constant used when conversion of rendered image to proper format failed.
 */
define ('ERR_IMAGE_CONVERT_FAILURE'       , -5);

/**
 * Error constant used when any generic error occurred during rendering.
 */
define ('ERR_RENDERING_ERROR'             , -6);

/**
 * Error constant used when length of supplied content exceeds configured limit.
 */
define ('ERR_LENGTH_EXCEEDED'             , -7);

/**
 * Error constant representing internal class error.
 *
 * Currently used when some essential method is not implemented in classes.
 */
define ('ERR_INTERNAL_CLASS'              , -8);

/*
 * Global Variables
 */


/**
 * Stores default temporary folder determined by {@link sys_get_temp_dir sys_get_temp_dir()}.
 * @global string $default_tmp_dir
 */
$default_tmp_dir = '';

/**
 * Stores all ScoreRender config options.
 * @global array $scorerender_options
 */
$scorerender_options = array ();

/**
 * Array of supported music notation syntax and relevant attributes.
 *
 * Array keys are names of the music notations, in lower case.
 * Their values are arrays themselves, containing:
 * - regular expression matching relevant notation
 * - start tag and end tag
 * - class file and class name for corresponding notation
 *
 * @global array $notations
 */
$notations = array (
	'abc'      => array (
		'regex'       => '~\[abc\](.*?)\[/abc\]~si',
		'starttag'    => '[abc]',
		'endtag'      => '[/abc]',
		'classname'   => 'abcRender',
		'includefile' => 'class.abc.inc.php'),
	'guido'    => array (
		'regex'       => '~\[guido\](.*?)\[/guido\]~si',
		'starttag'    => '[guido]',
		'endtag'      => '[/guido]',
		'classname'   => 'guidoRender',
		'includefile' => 'class.guido.inc.php'),
	'lilypond' => array (
		'regex'       => '~\[lilypond\](.*?)\[/lilypond\]~si',
		'starttag'    => '[lilypond]',
		'endtag'      => '[/lilypond]',
		'classname'   => 'lilypondRender',
		'includefile' => 'class.lilypond.inc.php'),
	'mup'      => array (
		'regex'       => '~\[mup\](.*?)\[/mup\]~si',
		'starttag'    => '[mup]',
		'endtag'      => '[/mup]',
		'classname'   => 'mupRender',
		'includefile' => 'class.mup.inc.php'),
);

require_once('class.scorerender.inc.php');

foreach (array_values ($notations) as $notation)
{
	/**
	 * @ignore
	 */
	require_once ($notation['includefile']);
}


/*
 * Backported functions -- sys_get_temp_dir and array_intersect_key
 */

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
		else if ( !empty($_ENV['TMPDIR']) )
			return realpath( $_ENV['TMPDIR'] );
		else if ( !empty($_ENV['TEMP']) )
			return realpath( $_ENV['TEMP'] );
		// Detect by creating a temporary file
		else
		{
			// Try to use system's temporary directory
			// as random name shouldn't exist
			$temp_file = tempnam( md5(uniqid(rand(), TRUE)), '' );
			if ( $temp_file )
			{
				$temp_dir = realpath( dirname($temp_file) );
				unlink( $temp_file );
				return $temp_dir;
			}
			else
				return FALSE;
		}
	}
}

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
			foreach ($result as $key => $v)
				if (!array_key_exists ($key, $array))
					unset ($result[$key]);
		return $result;
	}
}



/**
 * Retrieve ScoreRender options from database.
 *
 * If the {@link DATABASE_VERSION} constant contained inside MySQL database is
 * small than that of PHP file (most likely occur when plugin is
 * JUST upgraded), then it also merges old config with new default
 * config and update the options in database.
 */
function scorerender_get_options ()
{
	global $scorerender_options, $default_tmp_dir;

	$default_tmp_dir = sys_get_temp_dir();
	$scorerender_options = get_option ('scorerender_options');

	if (!is_array ($scorerender_options))
	{
		$scorerender_options = array();
	}
	else if (array_key_exists ('DB_VERSION', $scorerender_options) &&
		($scorerender_options['DB_VERSION'] >= DATABASE_VERSION) )
	{
		return;
	}

	// default options
	$defaults = array
	(
		'DB_VERSION'           => DATABASE_VERSION,
		'TEMP_DIR'             => $default_tmp_dir,
		'CONVERT_BIN'          => '/usr/bin/convert',
		'CACHE_DIR'            => ABSPATH . get_option('upload_path'),
		'CACHE_URL'            => get_option('siteurl') . '/' . get_option('upload_path'),

		'IMAGE_MAX_WIDTH'      => 360,
		'INVERT_IMAGE'         => false,
		'TRANSPARENT_IMAGE'    => true,
		'SHOW_SOURCE'          => false,
		'SHOW_IE_TRANSPARENCY_WARNING' => 1,

		'CONTENT_MAX_LENGTH'   => 4096,
		'FRAGMENT_PER_COMMENT' => 1,

		'LILYPOND_CONTENT_ENABLED' => true,
		'LILYPOND_COMMENT_ENABLED' => false,
		'LILYPOND_BIN'             => '/usr/bin/lilypond',

		'MUP_CONTENT_ENABLED' => true,
		'MUP_COMMENT_ENABLED' => false,
		'MUP_BIN'             => '/usr/local/bin/mup',
		'MUP_MAGIC_FILE'      => '',

		'GUIDO_CONTENT_ENABLED' => true,
		'GUIDO_COMMENT_ENABLED' => false,

		'ABC_CONTENT_ENABLED' => true,
		'ABC_COMMENT_ENABLED' => false,
		'ABCM2PS_BIN'         => '/usr/bin/abcm2ps',
	);

	// remove current settings not present in newest schema, then merge default values
	$scorerender_options = array_intersect_key ($scorerender_options, $defaults);
	$scorerender_options = array_merge ($defaults, $scorerender_options);
	$scorerender_options['DB_VERSION'] = DATABASE_VERSION;
	update_option ('scorerender_options', $scorerender_options);
	return;
}



/**
 * Returns number of cached images inside cache directory
 *
 * @since 0.2
 * @return integer number of images inside cache directory
 */
function scorerender_get_num_of_images ()
{
	global $scorerender_options;

	if (!is_dir ($scorerender_options['CACHE_DIR']) || !is_readable ($scorerender_options['CACHE_DIR']))
		return -1;

	$count = 0;
	if ($handle = opendir ($scorerender_options['CACHE_DIR']))
	{
		while (false !== ($file = readdir ($handle)))
		{
			if (preg_match ('/^[0-9A-Fa-f]{32}\.png$/', $file))
				$count++;
		}
		closedir ($handle);
	}
	return $count;
}



/**
 * Calculate number of fragments contained inside a blog post
 *
 * The returned numbers are mainly used for showing info in WordPress Dashboard
 *
 * @since 0.2
 * @param string $content the whole blog post content
 * @return array $count Array containing number of matched fragments for each kind of notation
 */
function scorerender_get_fragment_count ($content)
{
	global $notations;

	$count = array();
	foreach (array_values ($notations) as $notation)
		$count[] = preg_match_all ($notation['regex'], $content, $matches);

	return $count;
}



/**
 * Remove all cached images in cache directory
 *
 * @since 0.2
 */
function scorerender_remove_cache ()
{
	global $scorerender_options;

	// extra guard, doesn't hurt
	if (!is_dir      ($scorerender_options['CACHE_DIR']) ||
	    !is_readable ($scorerender_options['CACHE_DIR']) ||
	    !is_writable ($scorerender_options['CACHE_DIR']))
		return;

	if ($handle = opendir ($scorerender_options['CACHE_DIR']))
	{
		while (false !== ($file = readdir ($handle)))
		{
			// FIXME: how to decide if some image is generated by plugin?
			if (preg_match ('/^[0-9A-Fa-f]{32}\.png$/', $file))
				unlink ($scorerender_options['CACHE_DIR'] . DIRECTORY_SEPARATOR . $file);
		}
		closedir ($handle);
	}
	return;
}



/**
 * Generate HTML content from error message or rendered image
 * @param object $render PHP object created for rendering relevant music fragment
 * @return string $html The HTML content
 */
function scorerender_process_content ($render)
{
	global $scorerender_options, $notations;

	$result = $render->render();

	switch ($result)
	{
		case ERR_INVALID_INPUT:
			return sprintf (__('[%s: Invalid or dangerous input!]'), __('ScoreRender Error'));
		case ERR_CACHE_DIRECTORY_NOT_WRITABLE:
			return sprintf (__('[%s: Cache directory not writable!]'), __('ScoreRender Error'));
		case ERR_TEMP_DIRECTORY_NOT_WRITABLE:
			return sprintf (__('[%s: Temporary directory not writable!]'), __('ScoreRender Error'));
		case ERR_TEMP_FILE_NOT_WRITABLE:
			return sprintf (__('[%s: Temporary file not writable!]'), __('ScoreRender Error'));
		case ERR_IMAGE_CONVERT_FAILURE:
			return sprintf (__('[%s: Image convert failure!]'), __('ScoreRender Error'));
		case ERR_RENDERING_ERROR:
			return sprintf (__('[%s: The external rendering application failed!]'), __('ScoreRender Error'));
			                                        // '<br /><textarea cols=80 rows=10 READONLY>' .
			                                        // $render->getCommandOutput() . '</textarea>');
		case ERR_LENGTH_EXCEEDED:
			return sprintf (__('[%s: Content length limit exceeded!]'), __('ScoreRender Error'));
		case ERR_INTERNAL_CLASS:
			return sprintf (__('[%s: Internal class initialization error!]'), __('ScoreRender Error'));
	}

	// No errors, so generate HTML
	// Not nice to show source in alt text or title, since music source
	// is most likely multi-line, and some are quite long

	// This idea is taken from LatexRender demo site
	if ($scorerender_options['SHOW_SOURCE'])
	{
		$html = sprintf ("<form target='fragmentpopup' action='%s/%s/ScoreRender/showcode.php' method='post'>\n", get_bloginfo ('home'), PLUGINDIR);
		$html .= sprintf ("<input type='image' name='music_image' style='vertical-align: bottom' class='scorerender-image' title='%s' alt='%s' src='%s/%s' />\n",
		          __('Music fragment'), __('Music fragment'),
		          $scorerender_options['CACHE_URL'], $result);

		foreach ($notations as $notationname => $notation)
		{
			if ($render instanceof $notation['classname'])
			{
				$content = $notation['starttag'] . "\n" .
					$render->getMusicFragment() . "\n" .
					$notation['endtag'];
				break;
			}
		}

		$html .= sprintf ("<input type='hidden' name='code' value='%s'>\n</form>\n",
			rawurlencode (htmlentities ($content, ENT_NOQUOTES, get_bloginfo ('charset'))));
	}
	else
	{
		$html .= sprintf ("<img name='music_image' style='vertical-align: bottom' class='scorerender-image' title='%s' alt='%s' src='%s/%s' />\n",
		          __('Music fragment'), __('Music fragment'),
		          $scorerender_options['CACHE_URL'], $result);
	}

	if ($scorerender_options['TRANSPARENT_IMAGE'] &&
	    $scorerender_options['SHOW_IE_TRANSPARENCY_WARNING']) 
	{
		$html .= '<br /><!--[if lt IE 7]>' . __('<font color="red">Warning</font>: Internet Explorer &lt; 7 is incapable of displaying transparent PNG image, so the above image may not show properly in your browser as expected. Please either use any other browser such as <a href="http://www.getfirefox.com/" target="_blank">Firefox</a> or <a href="http://www.opera.com/" target="_blank">Opera</a>, or at least upgrade to IE 7. Alternatively, ask site admin to disable transparent image.') . "<![endif]-->\n";
	}

	return $html;
}



/**
 * Generate converted HTML fragment from music notation fragment
 *
 * Create PHP object for each kind of matched music notation, render fragment
 * inside, and output the rendered result as HTML.
 *
 * If no PHP class exists corresponding to certain notation, then 
 * unconverted content is returned.
 * @param array $matches Matched music fragment in posts or comments. This variable must be supplied by {@link preg_match preg_match()} or {@link preg_match_all preg_match_all()}. Alternatively invoke this function with {@link preg_replace_callback preg_replace_callback()}.
 * @return string Either HTML content containing rendered image, or HTML error message in case rendering failed.
 */
function scorerender_filter ($matches)
{
	global $scorerender_options, $notations;
	$input = trim (html_entity_decode ($matches[1]));

	// since preg_replace_callback only accepts single function,
	// we have to check which regex is matched here and create
	// corresponding object
	foreach ($notations as $notationname => $notation)
	{
		if (preg_match ($notation['regex'], $matches[0]))
		{
			// TODO: Should do it like what a class should behave
			$render = new $notation['classname'] ($scorerender_options);
			$render->setMusicFragment ($input);
			break;
		}
	}

	if (empty ($render)) return $input;

	return scorerender_process_content ($render);
}



/**
 * Renders music fragments contained inside posts.
 *
 * Check if post rendering should be enabled.
 * If yes, then apply {@link scorerender_filter} function on $content.
 *
 * @param string $content the whole content of blog post
 * @return string Converted blog post content.
 */
function scorerender_content ($content)
{
	global $scorerender_options, $notations;

	$regex_list = array();
	foreach ($notations as $notationname => $notation)
	{
		$config_name = strtoupper ($notationname) . '_CONTENT_ENABLED';
		if ($scorerender_options[$config_name])
			$regex_list[] = $notation['regex'];
	};

	return preg_replace_callback ($regex_list, 'scorerender_filter', $content, -1);
}



/**
 * Renders music fragments contained inside comments.
 *
 * Check if comment rendering should be enabled.
 * If yes, then apply {@link scorerender_filter} function on $content.
 *
 * @param string $content the whole content of blog comment
 * @return string Converted blog comment content.
 */
function scorerender_comment ($content)
{
	global $scorerender_options, $notations;

	$regex_list = array();
	foreach ($notations as $notationname => $notation)
	{
		$config_name = strtoupper ($notationname) . '_CONTENT_ENABLED';
		if ($scorerender_options[$config_name])
			$regex_list[] = $notation['regex'];
	};

	$limit = ($scorerender_options['FRAGMENT_PER_COMMENT'] <= 0) ? -1 : $scorerender_options['FRAGMENT_PER_COMMENT'];
	return preg_replace_callback ($regex_list, 'scorerender_filter', $content, $limit);
}



/**
 * Display info in WordPress Dashboard
 *
 * @since 0.2
 * @access private
 */
function scorerender_activity_box ()
{
	global $wpdb, $notations;
	$frag_count = 0;

	$wpdb->hide_errors();

	// get posts first
	$query_substr = array();
	foreach (array_values ($notations) as $notation)
		$query_substr[] .= "post_content LIKE '%" . $notation['endtag'] . "%'";
	$query = "SELECT post_content FROM $wpdb->posts WHERE " . implode (" OR ", $query_substr);

	$posts = $wpdb->get_col ($query);
	$post_count = count ($posts);
	$number_of_posts = sprintf (__ngettext ('%d post', '%d posts', $post_count), $post_count);

	foreach ($posts as $post)
		$frag_count += array_sum (scorerender_get_fragment_count ($post));


	// followed by comments
	$query_substr = array();
	foreach (array_values ($notations) as $notation)
		$query_substr[] .= "comment_content LIKE '%[/" . $notation['endtag'] . "]%'";
	$query = sprintf ("SELECT comment_content FROM $wpdb->comments WHERE comment_approved = '1' AND (%s)",
			  implode (" OR ", $query_substr));

	$comments = $wpdb->get_col ($query);
	$comment_count = count ($comments);
	$number_of_comments = sprintf (__ngettext ('%d comment', '%d comments', $comment_count), $comment_count);

	foreach ($comments as $comment)
		$frag_count += array_sum (scorerender_get_fragment_count ($comment));


	$number_of_fragments = sprintf (__ngettext ('%d music fragment', '%d music fragments', $frag_count), $frag_count);

	$img_count = scorerender_get_num_of_images();

	if ( 0 > $img_count )
		$number_of_images = sprintf (__('<font color="red">The cache directory is either non-existant or not readable.</font> Please <a href="%s">change the setting</a> and make sure the directory exists.'), 'options-general.php?page=' . plugin_basename (__FILE__));
	else
		$number_of_images = sprintf (__ngettext ('Currently %d image are rendered and cached.',
		                                         'Currently %d images are rendered and cached.', $img_count),
		                             $img_count);
?>
	<div>
	<h3><?php _e('ScoreRender') ?></h3>
	<p><?php printf (__('There are %s contained within %s and %s.'), $number_of_fragments, $number_of_posts, $number_of_comments); ?> <?php echo $number_of_images; ?></p>
	</div>
<?php
}



/**
 * Update ScoreRender options in database with submitted options.
 *
 * A warning banner will be shown on top of admin page for each
 * error encountered in various options. In some cases supplied
 * config values will be discarded.
 */
function scorerender_update_options ()
{
	global $defalt_tmp_dir, $scorerender_options;
	$newopt = (array) $_POST['ScoreRender'];

	$messages = array
	(
		'temp_dir_undefined'       => sprintf (__('WARNING: Temporary directory is NOT defined! Will fall back to %s.'), $defalt_tmp_dir),
		'temp_dir_not_writable'    => sprintf (__('WARNING: Temporary directory is NOT writable! Will fall back to %s.'), $defalt_tmp_dir),
		'cache_dir_undefined'      => __('ERROR: Cache directory is NOT defined! Image can not be placed inside appropriate directory.'),
		'cache_dir_not_writable'   => __('ERROR: Cache directory is NOT writable! Image can not be placed inside appropriate directory.'),
		'cache_url_undefined'      => __('ERROR: Cache URL is NOT defined!'),
		'wrong_content_length'     => __('ERROR: Content length is not a non-negative integer. Value discarded.'),
		'wrong_frag_per_comment'   => __('ERROR: Fragment per comment is not a non-negative integer. Value discarded.'),
		'wrong_image_max_width'    => __('ERROR: Image maximum width must be positive integer >= 72. Value discarded.'),
		'convert_not_found'        => __('ERROR: Location of <tt>convert</tt> utility is NOT defined!'),
		'convert_not_executable'   => __('ERROR: <tt>convert</tt> utility is NOT executable!'),
		'lilypond_binary_problem'  => sprintf (__('WARNING: %s not found or not an executable. %s support DISABLED.'), '<tt>lilypond</tt>', 'Lilypond'),
		'mup_binary_problem'       => sprintf (__('WARNING: %s not found or not an executable. %s support DISABLED.'), '<tt>mup</tt>', 'Mup'),
		'abc_binary_problem'       => sprintf (__('WARNING: %s not found or not an executable. %s support DISABLED.'), '<tt>abcm2ps</tt>', 'ABC'),
	);

	if ( function_exists ('current_user_can') && !current_user_can ('manage_options') )
		die (__('Cheatin&#8217; uh?'));

	/*
	 * general options
	 */
	if ( empty ($newopt['TEMP_DIR']) )
	{
		$msgs[] = 'temp_dir_undefined';
		$newopt['TEMP_DIR'] = $default_tmp_dir;
	}
	else if ( !is_writable ($newopt['TEMP_DIR']) )
	{
		$msgs[] = 'temp_dir_not_writable';
		$newopt['TEMP_DIR'] = $default_tmp_dir;
	}

	if ( empty ($newopt['CACHE_DIR']) )
		$msgs[] = 'cache_dir_undefined';
	else if ( !is_writable ($newopt['CACHE_DIR']) )
		$msgs[] = 'cache_dir_not_writable';

	if ( empty ($newopt['CACHE_URL']) )
		$msgs[] = 'cache_url_undefined';

	if ( empty ($newopt['CONVERT_BIN']) )
		$msgs[] = 'convert_not_found';
	else if ( ! is_executable ($newopt['CONVERT_BIN']) )
		$msgs[] = 'convert_not_executable';

	$newopt['SHOW_SOURCE'] = isset ($newopt['SHOW_SOURCE']);
	$newopt['INVERT_IMAGE'] = isset ($newopt['INVERT_IMAGE']);
	$newopt['TRANSPARENT_IMAGE'] = isset ($newopt['TRANSPARENT_IMAGE']);

	if (!ctype_digit ($newopt['CONTENT_MAX_LENGTH'])) {
		$msgs[] = 'wrong_content_length';
		unset ($newopt['CONTENT_MAX_LENGTH']);
	}

	if (!ctype_digit ($newopt['FRAGMENT_PER_COMMENT'])) {
		$msgs[] = 'wrong_frag_per_comment';
		unset ($newopt['FRAGMENT_PER_COMMENT']);
	}

	if (!ctype_digit ($newopt['IMAGE_MAX_WIDTH']) || ($newopt['IMAGE_MAX_WIDTH'] < (1 * DPI))) {
		$msgs[] = 'wrong_image_max_width';
		unset ($newopt['IMAGE_MAX_WIDTH']);
	}

	/*
	 * lilypond options
	 */
	$newopt['LILYPOND_CONTENT_ENABLED'] = isset ($newopt['LILYPOND_CONTENT_ENABLED']);
	$newopt['LILYPOND_COMMENT_ENABLED'] = isset ($newopt['LILYPOND_COMMENT_ENABLED']);

	if ( !is_executable ($newopt['LILYPOND_BIN']) )
	{
		if ($newopt['LILYPOND_CONTENT_ENABLED'] || $newopt['LILYPOND_COMMENT_ENABLED'])
		{
			$msgs[] = 'lilypond_binary_problem';
			$newopt['LILYPOND_CONTENT_ENABLED'] = false;
			$newopt['LILYPOND_COMMENT_ENABLED'] = false;
		}
	}

	/*
	 * mup options
	 */
	$newopt['MUP_CONTENT_ENABLED'] = isset ($newopt['MUP_CONTENT_ENABLED']);
	$newopt['MUP_COMMENT_ENABLED'] = isset ($newopt['MUP_COMMENT_ENABLED']);

	if ( !is_executable ($newopt['MUP_BIN']) )
	{
		if ($newopt['MUP_CONTENT_ENABLED'] || $newopt['MUP_COMMENT_ENABLED'])
		{
			$msgs[] = 'mup_binary_problem';
			$newopt['MUP_CONTENT_ENABLED'] = false;
			$newopt['MUP_COMMENT_ENABLED'] = false;
		}
	}

	/*
	 * guido options
	 */
	$newopt['GUIDO_CONTENT_ENABLED'] = isset ($newopt['GUIDO_CONTENT_ENABLED']);
	$newopt['GUIDO_COMMENT_ENABLED'] = isset ($newopt['GUIDO_COMMENT_ENABLED']);

	/*
	 * abcm2ps options
	 */
	$newopt['ABC_CONTENT_ENABLED'] = isset ($newopt['ABC_CONTENT_ENABLED']);
	$newopt['ABC_COMMENT_ENABLED'] = isset ($newopt['ABC_COMMENT_ENABLED']);

	if ( !is_executable ($newopt['ABCM2PS_BIN']) )
	{
		if ($newopt['ABC_CONTENT_ENABLED'] || $newopt['ABC_COMMENT_ENABLED'])
		{
			$msgs[] = 'abc_binary_problem';
			$newopt['ABC_CONTENT_ENABLED'] = false;
			$newopt['ABC_COMMENT_ENABLED'] = false;
		}
	}

	$scorerender_options = array_merge ($scorerender_options, $newopt);
	update_option ('scorerender_options', $scorerender_options);

	if ( !empty ($msgs) )
	{
		foreach ($msgs as $key => $m)
			echo '<div id="scorerender-error-' . $key . '" class="updated fade-ff0000"><p><strong>' . $messages[$m] . "</strong></p></div>\n";
	}
	else
	{
		echo '<div id="message" class="updated fade"><p><strong>' .
			__('Options saved.') . "</strong></p></div>\n";
	}
}



/**
 * Section of admin page about path options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_path ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('Path options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Temporary directory:') ?></th>
			<td>
				<input name="ScoreRender[TEMP_DIR]" class="code" type="text" id="temp_dir" value="<?php echo attribute_escape ($scorerender_options['TEMP_DIR']); ?>" size="60" /><br />
				<?php _e('Must be writable and ideally located outside the web-accessible area.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache directory:') ?></th>
			<td>
				<input name="ScoreRender[CACHE_DIR]" class="code" type="text" id="cache_dir" value="<?php echo attribute_escape ($scorerender_options['CACHE_DIR']); ?>" size="60" /><br />
				<?php _e('Must be writable and located inside the web-accessible area.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache URL:') ?></th>
			<td>
				<input name="ScoreRender[CACHE_URL]" class="code" type="text" id="cache_url" value="<?php echo attribute_escape ($scorerender_options['CACHE_URL']); ?>" size="60" /><br />
				<?php _e('Must correspond to the image cache directory above.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<a target="_new" href="http://www.imagemagick.net/"><code>convert</code></a>') ?></th>
			<td>
				<input name="ScoreRender[CONVERT_BIN]" class="code" type="text" id="convert_bin" value="<?php echo attribute_escape ($scorerender_options['CONVERT_BIN']); ?>" size="40" />
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Section of admin page about image options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_image ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('Image options') ?></legend>
		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Image width (pixel):') ?></th>
			<td>
				<input type="text" name="ScoreRender[IMAGE_MAX_WIDTH]" id="image_max_width" value="<?php echo attribute_escape ($scorerender_options['IMAGE_MAX_WIDTH']); ?>" size="6" />
				<label for="image_max_width"><?php _e('Default is 360 (5 inch)') ?></label><br /><?php _e('Note that image size conversion is hardcoded to 72 DPI for most music rendering applications. And this value is just an approximation, actual image rendered for certain applications can be a bit smaller.') ?></label>
			</td>
		</tr>
		<tr valign="top">
			<td colspan="2">
				<input type="checkbox" name="ScoreRender[SHOW_SOURCE]" id="show_input" value="1" <?php checked('1', $scorerender_options['SHOW_SOURCE']); ?> />
				<label for="show_input"><?php _e('Show music source in new browser window/tab when image is clicked'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<td colspan="2">
				<input type="checkbox" name="ScoreRender[INVERT_IMAGE]" id="invert_image" value="1" <?php checked('1', $scorerender_options['INVERT_IMAGE']); ?> />
				<label for="invert_image"><?php _e('Invert image colours (becomes white on black)'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<td colspan="2">
				<input type="checkbox" name="ScoreRender[TRANSPARENT_IMAGE]" id="transparent_image" value="1" <?php checked('1', $scorerender_options['TRANSPARENT_IMAGE']); ?> onclick="var box = document.getElementById('show_ie_transparency_warning'); box.disabled = !box.disabled; return true;" />
				<label for="transparent_image"><?php _e('Use transparent background') ?> <?php _e('(IE &lt;= 6 does not support transparent PNG)'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<td style="padding-left: 30px;" colspan="2">
				<input type="checkbox" name="ScoreRender[SHOW_IE_TRANSPARENCY_WARNING]" id="show_ie_transparency_warning" value="1" <?php checked('1', $scorerender_options['SHOW_IE_TRANSPARENCY_WARNING']); if (1 != $scorerender_options['TRANSPARENT_IMAGE']) { echo ' disabled="disabled"'; } ?> />
				<label for="show_ie_transparency_warning"><?php _e('Show warning message when such browser is used (Only possible if above box is checked)') ?></label>
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Section of admin page about content options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_content ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('Content options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Maximum length per fragment:') ?></th>
			<td>
				<input type="text" name="ScoreRender[CONTENT_MAX_LENGTH]" id="content_max_length" value="<?php echo attribute_escape ($scorerender_options['CONTENT_MAX_LENGTH']); ?>" size="6" />
				<label for="content_max_length"><?php _e('(0 means unlimited)') ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Maximum number of fragment per comment:') ?></th>
			<td>
				
				<input type="text" name="ScoreRender[FRAGMENT_PER_COMMENT]" id="fragment_per_comment" value="<?php echo attribute_escape ($scorerender_options['FRAGMENT_PER_COMMENT']); ?>" size="6" />
				<label for="fragment_per_comment"><?php _e('(0 means unlimited)') ?><br /><?php printf (__('If you don&#8217;t want comment rendering, turn off &#8216;<i>%s</i>&#8217; checkboxes below instead. This option does not affect posts and pages.'), __('Enable parsing for comments')); ?></label>
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Section of admin page about caching options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_caching ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('Caching') ?></legend>
<?php
	$img_count = scorerender_get_num_of_images();

	if ( -1 == $img_count )
		echo "Cache directory is not a readable directory.<br />\n";
	else
	{
		printf (__ngettext("Cache directory contain %d image.\n",
			           "Cache directory contain %d images.\n",
			           $img_count), $img_count);
	}

	if ( ($img_count >= 1) && is_writable ($scorerender_options['CACHE_DIR']) ) :
?>
		<p class="submit">
		<input type="submit" name="clear_cache" value="<?php _e('Clear Cache &raquo;') ?>" />
		</p>
<?php
	endif;
?>
	</fieldset>
<?php
}



/**
 * Section of admin page about Lilypond notation options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_lilypond ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('Lilypond options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[LILYPOND_CONTENT_ENABLED]" id="lilypond_content" value="1" <?php checked('1', $scorerender_options['LILYPOND_CONTENT_ENABLED']); ?> />
				<label for="lilypond_content"><?php _e('Enable parsing for posts and pages'); ?></label><br />
				<input type="checkbox" name="ScoreRender[LILYPOND_COMMENT_ENABLED]" id="lilypond_comment" value="1" <?php checked('1', $scorerender_options['LILYPOND_COMMENT_ENABLED']); ?> />
				<label for="lilypond_comments"><?php printf ('%s %s', __('Enable parsing for comments'), __('(<span style="font-weight: bold; color: red;">Warning:</span> possible security and overloading concern.)')); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<code>lilypond</code>'); ?></th>
			<td>
				<input name="ScoreRender[LILYPOND_BIN]" class="code" type="text" id="lilypond_bin" value="<?php echo attribute_escape ($scorerender_options['LILYPOND_BIN']); ?>" size="50" />
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Section of admin page about Mup notation options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_mup ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('Mup options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[MUP_CONTENT_ENABLED]" id="mup_content" value="1" <?php checked('1', $scorerender_options['MUP_CONTENT_ENABLED']); ?> />
				<label for="mup_content"><?php _e('Enable parsing for posts and pages'); ?></label><br />
				<input type="checkbox" name="ScoreRender[MUP_COMMENT_ENABLED]" value="1" <?php checked('1', $scorerender_options['MUP_COMMENT_ENABLED']); ?> />
				<label for="mup_comments"><?php printf ('%s %s', __('Enable parsing for comments'), __('(<span style="font-weight: bold; color: red;">Warning:</span> possible security and overloading concern.)')); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_BIN]" class="code" type="text" id="mup_bin" value="<?php echo attribute_escape ($scorerender_options['MUP_BIN']); ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s magic file:'), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_MAGIC_FILE]" class="code" type="text" id="mup_magic_file" value="<?php echo attribute_escape ($scorerender_options['MUP_MAGIC_FILE']); ?>" size="50" />
				<br />
				<?php printf (__('Leave it empty if you have not <a href="%s">registered</a> Mup. This file must be readable by the user account running web server.'), 'http://www.arkkra.com/doc/faq.html#payment'); ?>
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Section of admin page about GUIDO notation options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_guido ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('GUIDO NoteServer options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[GUIDO_CONTENT_ENABLED]" id="guido_content" value="1" <?php checked('1', $scorerender_options['GUIDO_CONTENT_ENABLED']); ?> />
				<label for="guido_content"><?php _e('Enable parsing for posts and pages'); ?></label><br />
				<input type="checkbox" name="ScoreRender[GUIDO_COMMENT_ENABLED]" id="guido_comment" value="1" <?php checked('1', $scorerender_options['GUIDO_COMMENT_ENABLED']); ?> />
				<label for="guido_comments"><?php _e('Enable parsing for comments'); ?></label>
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Section of admin page about ABC notation options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_abc ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<legend><?php _e('ABC options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[ABC_CONTENT_ENABLED]" id="abc_content" value="1" <?php checked('1', $scorerender_options['ABC_CONTENT_ENABLED']); ?> />
				<label for="abc_content"><?php _e('Enable parsing for posts and pages'); ?></label><br />
				<input type="checkbox" name="ScoreRender[ABC_COMMENT_ENABLED]" id="abc_comment" value="1" <?php checked('1', $scorerender_options['ABC_COMMENT_ENABLED']); ?> />
				<label for="abc_comments"><?php printf ('%s %s', __('Enable parsing for comments'), __('(<span style="font-weight: bold; color: red;">Warning:</span> possible security and overloading concern.)')); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<code>abcm2ps</code>'); ?></th>
			<td>
				<input name="ScoreRender[ABCM2PS_BIN]" class="code" type="text" id="abcm2ps_bin" value="<?php echo attribute_escape ($scorerender_options['ABCM2PS_BIN']); ?>" size="50" />
				<br />
				<?php printf (__('Any program with command line argument compatible with %s will do, but %s is HIGHLY recommended, because it can handle multiple voices inside single staff.'), '<code>abc2ps</code>', '<code>abcm2ps</code>'); ?>
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}



/**
 * Show WordPress admin page
 *
 * It also checks if form button is pressed, and may call
 * {@link scorerender_remove_cache scorerender_remove_cache()} or
 * {@link scorerender_update_options scorerender_update_options()} correspondingly.
 *
 * @access private
 */
function scorerender_admin_options ()
{
	global $scorerender_options;

	if ( isset($_POST['clear_cache']) && isset($_POST['ScoreRender']) )
	{
		check_admin_referer ('scorerender-update-options');
		scorerender_remove_cache();
	}

	if ( isset($_POST['Submit']) && isset($_POST['ScoreRender']) )
	{
		check_admin_referer ('scorerender-update-options');
		scorerender_update_options();
	}
?>

	<div class="wrap">
	<form method="post" action="" id="scorerender-conf">
	<?php wp_nonce_field ('scorerender-update-options') ?>
	<h2><?php _e('ScoreRender options') ?></h2>

	<p><?php _e('ScoreRender renders inline music fragments inside blog post and/or comment as images. Currently it supports the following notations:'); ?></p>
	<ul>
		<li><a target="_blank" href="http://www.lilypond.org/">Lilypond</a></li>
		<li><?php printf ('%s, used by Mup itself and %s',
			'<a target="_blank" href="http://www.arkkra.com/">Mup</a>',
			'<a target="_blank" href="http://noteedit.berlios.de/">Noteedit</a>'); ?></li>
		<li><a target="_new" href="http://www.informatik.tu-darmstadt.de/AFS/GUIDO/">GUIDO</a></li>
		<li><?php printf ('<a target="_blank" href="%s">ABC notation</a>, used by various programs like %s, %s or %s',
			'http://abcnotation.org.uk/',
			'<a target="_blank" href="http://www.ihp-ffo.de/~msm/">abc2ps</a>',
			'<a target="_blank" href="http://moinejf.free.fr/">abcm2ps</a>',
			'<a target="_blank" href="http://trillian.mit.edu/~jc/music/abc/src/">jcabc2ps</a>'); ?></li>
	</ul>

	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;') ?>" />
	</p>
<?php
	// path options
	scorerender_admin_section_path(); 

	// image options
	scorerender_admin_section_image(); 

	// content options
	scorerender_admin_section_content(); 

	// caching options
	scorerender_admin_section_caching(); 

	// lilypond options
	scorerender_admin_section_lilypond(); 

	// mup options
	scorerender_admin_section_mup(); 

	// guido options
	scorerender_admin_section_guido(); 

	// ABC options
	scorerender_admin_section_abc(); 
?>
	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;') ?>" />
	</p>

	</div>
	</form>
	<?php
}



/**
 * Append submenu item into WordPress menu
 *
 * @access private
 */
function scorerender_admin_menu ()
{
	add_options_page (__('ScoreRender options'), 'ScoreRender', 9, __FILE__, 'scorerender_admin_options');
}

/*
Remove tag balancing filter

There seems to be an bug in the balanceTags function of wp-includes/functions-formatting.php
which means that ">>" are converted to "> >", and "<<" to "< <", causing syntax errors.

(This is part of the LilyPond syntax for parallel music, and Mup syntax for
attribute change within a bar) -- Abel

Since balancing filter is also used in get_the_content(), i.e. before any plugin is
activated, removing filter is of no use. -- Abel
 */

/*
remove_filter ('content_save_pre', 'balanceTags', 50);
remove_filter ('excerpt_save_pre', 'balanceTags', 50);
remove_filter ('comment_save_pre', 'balanceTags', 50);
remove_filter ('pre_comment_content', 'balanceTags', 30);
remove_filter ('comment_text', 'force_balance_tags', 25);
 */

scorerender_get_options ();

if ( 0 != get_option('use_balanceTags') )
{
	/**
	 * @ignore
	 */
	function turn_off_balance_tags()
	{
		echo '<div id="balancetag-warning" class="updated" style="background-color: #ff6666"><p>'
			. sprintf (__('<strong>OPTION CONFLICT</strong>: The "correct invalidly nested XHTML automatically" option conflicts with ScoreRender plugin, because it will mangle certain Lilypond and Mup fragments. The option is available in <a href="%s">Writing option page</a>.'), "options-writing.php")
			. "</p></div>";
	}
	add_filter ('admin_notices', 'turn_off_balance_tags');
}

// earlier than default priority, since smilies conversion and wptexturize() can mess up the content
add_filter ('the_excerpt', 'scorerender_content', 5);
add_filter ('the_content', 'scorerender_content', 5);
add_filter ('comment_text', 'scorerender_comment', 5);
add_filter ('activity_box_end', 'scorerender_activity_box');
add_filter ('admin_menu', 'scorerender_admin_menu');

?>
