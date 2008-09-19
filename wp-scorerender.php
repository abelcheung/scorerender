<?php
/*
Plugin Name: ScoreRender
Plugin URI: http://scorerender.abelcheung.org/
Description: Renders inline music score fragments in WordPress. Heavily based on FigureRender from Chris Lamb.
Author: Abel Cheung
Version: 0.2.50
Author URI: http://me.abelcheung.org/
*/

/*
 ScoreRender - Renders inline music score fragments in WordPress
 Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 Copyright (C) 2007, 08 Abel Cheung <abelcheung at gmail dot com>

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

/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.2.50
 * @author Abel Cheung <abelcheung@gmail.com>
 * @copyright Copyright (C) 2007, 08 Abel Cheung
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/**
 * Database version.
 *
 * This number must be incremented every time when option has been changed, removed or added.
 */
define ('DATABASE_VERSION', 11);

/**
 * Most apps hardcode DPI value to 72 dot per inch
 */
define ('DPI', 72.0);

/**
 * Translation text domain
 */
define ('TEXTDOMAIN', 'scorerender');

/**
 * Regular expression for cached images
 */
define ('REGEX_CACHE_IMAGE', '/^sr-\w+-[0-9A-Fa-f]{32}\.png$/');

/**
 * Debugging purpose
 */
define (DEBUG, FALSE);

/*
 * How error is handled when rendering failed
 */
define ('ON_ERR_SHOW_MESSAGE' , '1');

define ('ON_ERR_SHOW_FRAGMENT', '2');

define ('ON_ERR_SHOW_NOTHING' , '3');

define ('MSG_WARNING', 1);
define ('MSG_FATAL', 2);

/*
 * Global Variables
 */

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
		'includefile' => 'notation/abc.php',
		'progs'       => array ('ABCM2PS_BIN')),
	'guido'    => array (
		'regex'       => '~\[guido\](.*?)\[/guido\]~si',
		'starttag'    => '[guido]',
		'endtag'      => '[/guido]',
		'classname'   => 'guidoRender',
		'includefile' => 'notation/guido.php',
		'progs'       => array ()),
	'lilypond' => array (
		'regex'       => '~\[lilypond\](.*?)\[/lilypond\]~si',
		'starttag'    => '[lilypond]',
		'endtag'      => '[/lilypond]',
		'classname'   => 'lilypondRender',
		'includefile' => 'notation/lilypond.php',
		'progs'       => array ('LILYPOND_BIN')),
	'mup'      => array (
		'regex'       => '~\[mup\](.*?)\[/mup\]~si',
		'starttag'    => '[mup]',
		'endtag'      => '[/mup]',
		'classname'   => 'mupRender',
		'includefile' => 'notation/mup.php',
		'progs'       => array ('MUP_BIN')),
	'pmw'      => array (
		'regex'       => '~\[pmw\](.*?)\[/pmw\]~si',
		'starttag'    => '[pmw]',
		'endtag'      => '[/pmw]',
		'classname'   => 'pmwRender',
		'includefile' => 'notation/pmw.php',
		'progs'       => array ('PMW_BIN')),
);

/**
 * Utility functions used by ScoreRender
 */
require_once('scorerender-utils.php');

/**
 * Main ScoreRender class
 */
require_once('scorerender-class.php');

foreach (array_values ($notations) as $notation)
{
	/**
	 * @ignore
	 */
	require_once ($notation['includefile']);
}

/**
 * Initialize text domain.
 *
 * Translations are expected to be found in:
 *   - the same directory containing this plugin
 *   - default plugin translation path (root of plugin folder)
 *   - theme translation path (wp-content/languages or wp-includes/languages)
 * @since 0.2
 */
function scorerender_init_textdomain ()
{
	// load_textdomain() already does file existance checking
	load_plugin_textdomain (TEXTDOMAIN, PLUGINDIR . DIRECTORY_SEPARATOR . plugin_basename (dirname (__FILE__)));
	load_plugin_textdomain (TEXTDOMAIN);
	load_plugin_textdomain (TEXTDOMAIN, ABSPATH . LANGDIR);
}


/**
 * Transform all path related options in ScoreRender settings
 *
 * @since 0.2.50
 * @uses get_path_presentation
 * @pararm array $array The settings to be transformed (as an array)
 * @param boolean $is_internal Whether to always transform into Unix format, which is used for storing values into database. FALSE means using OS native representation.
 */
function transform_paths (&$setting, $is_internal)
{
	if (!is_array ($setting)) return;
	
	// Transform path related settings to unix presentation
	$keys = array ('TEMP_DIR', 'CACHE_DIR',
		'CONVERT_BIN', 'LILYPOND_BIN', 'MUP_BIN',
		'MUP_MAGIC_FILE', 'ABCM2PS_BIN', 'PMW_BIN');
	
	foreach ($keys as $key)
		if (isset ($setting[$key]))
			$setting[$key] = get_path_presentation ($setting[$key], $is_internal);

}

/**
 * Guess default upload directory setting from WordPress.
 * 
 * WordPress is inconsistent with upload directory setting across multiple
 * versions. Try to guess to most sensible setting and take that as default
 * value.
 *
 * @since 0.2
 * @uses get_path_presentation
 * @uses is_absolute_path
 * @return array Returns array containing both full path ('path' key) and corresponding URL ('url' key)
 */
function scorerender_get_upload_dir ()
{
	$uploads = wp_upload_dir();
	
	/* Path setting read order:
	 * 1. wp_upload_dir()
	 * 2. upload_path option
	 * 3. ABSPATH/wp-content/uploads
	 */
	if (isset ($uploads['basedir']))
		$path = $uploads['basedir'];
	else
		$path = trim(get_option('upload_path'));
	
	if (empty ($path))
		$path = 'wp-content'.DIRECTORY_SEPARATOR.'uploads';

	if (!is_absolute_path ($path))
		$path = ABSPATH . $path;

	/* URL setting read order:
	 * 1. wp_upload_dir()
	 * 2. upload_url_path option
	 * 3. $siteurl/wp-content/uploads
	 */
	if (isset ($uploads['baseurl']))
		$url = $uploads['baseurl'];
	else
		$url = trim(get_option('upload_url_path'));
	
	if (empty ($url))
		$url = get_option('siteurl') . '/' .
			str_replace (ABSPATH, '', $path);

	$path = get_path_presentation ($path, FALSE);

	return (array ('path' => $path, 'url' => $url));
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
	global $scorerender_options;

	$scorerender_options = get_option ('scorerender_options');

	if (!is_array ($scorerender_options))
	{
		$scorerender_options = array();
	}
	elseif (array_key_exists ('DB_VERSION', $scorerender_options) &&
		($scorerender_options['DB_VERSION'] >= DATABASE_VERSION) )
	{
		transform_paths ($scorerender_options, FALSE);
		return;
	}

	$cachefolder = scorerender_get_upload_dir ();

	$defprog = array();
	// ImageMagick use versioned folders, abcm2ps don't have Win32 installer
	// So just make up some close enough paths for them
	// PMW doesn't even have public available Win32 binary, perhaps
	// somebody might be able to compile it with MinGW?
	if (is_windows ())
		$defprog = array (
			'abc2ps' => 'C:\Program Files\abcm2ps\abcm2ps.exe',
			'convert' => 'C:\Program Files\ImageMagick\convert.exe',
			'lilypond' => 'C:\Program Files\Lilypond\usr\bin\lilypond.exe',
			'mup' => 'C:\Program Files\mupmate\mup.exe',
			'pmw' => '',
		);
	else
		$defprog = array (
			'abc2ps' => '/usr/bin/abcm2ps',
			'convert' => '/usr/bin/convert',
			'lilypond' => '/usr/bin/lilypond',
			'mup' => '/usr/local/bin/mup',
			'pmw' => '/usr/local/bin/pmw',
		);

	// default options
	$defaults = array
	(
		'DB_VERSION'           => DATABASE_VERSION,
		'TEMP_DIR'             => sys_get_temp_dir(),
		'CONVERT_BIN'          => $defprog['convert'],
		'CACHE_DIR'            => $cachefolder['path'],
		'CACHE_URL'            => $cachefolder['url'],

		'IMAGE_MAX_WIDTH'      => 360,
		'INVERT_IMAGE'         => false,
		'TRANSPARENT_IMAGE'    => true,
		'SHOW_SOURCE'          => false,
		'SHOW_IE_TRANSPARENCY_WARNING' => 0,
		'ERROR_HANDLING'       => ON_ERR_SHOW_MESSAGE,
		'COMMENT_ENABLED'      => false,

		'CONTENT_MAX_LENGTH'   => 4096,
		'FRAGMENT_PER_COMMENT' => 1,

		'LILYPOND_BIN'         => $defprog['lilypond'],

		'MUP_BIN'              => $defprog['mup'],
		'MUP_MAGIC_FILE'       => '',

		'ABCM2PS_BIN'          => $defprog['abc2ps'],
		'PMW_BIN'              => $defprog['pmw'],
	);

	// Special handling for certain versions
	if ($scorerender_options['DB_VERSION'] <= 9)
	{
		if ( $scorerender_options['LILYPOND_COMMENT_ENABLED'] ||
		     $scorerender_options['MUP_COMMENT_ENABLED']      ||
		     $scorerender_options['ABC_COMMENT_ENABLED']      ||
		     $scorerender_options['GUIDO_COMMENT_ENABLED'] )
		{
			$scorerender_options['COMMENT_ENABLED'] = true;
		}
	}

	// remove current settings not present in newest schema, then merge default values
	$scorerender_options = array_intersect_key ($scorerender_options, $defaults);
	$scorerender_options = array_merge ($defaults, $scorerender_options);
	$scorerender_options['DB_VERSION'] = DATABASE_VERSION;

	transform_paths ($scorerender_options, TRUE);
	update_option ('scorerender_options', $scorerender_options);
	transform_paths ($scorerender_options, FALSE);
	
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
			if (preg_match (REGEX_CACHE_IMAGE, $file))
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
 *
 * @todo maybe store all info into separate table
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
			if (preg_match (REGEX_CACHE_IMAGE, $file))
				unlink ($scorerender_options['CACHE_DIR'] . DIRECTORY_SEPARATOR . $file);
		}
		closedir ($handle);
	}
	return;
}


/**
 * Generate HTML content from error message or rendered image
 *
 * @uses scorerender_generate_html_error
 * @param object $render PHP object created for rendering relevant music fragment
 * @return string $html The HTML content
 */
function scorerender_process_content ($render)
{
	global $scorerender_options, $notations;

	$result = $render->render();

	if (false === $result)
	{
		switch ($scorerender_options['ERROR_HANDLING'])
		{
		  case ON_ERR_SHOW_NOTHING:
			return '';

		  case ON_ERR_SHOW_FRAGMENT:
			$name = $render->get_notation_name ();

			// Shouldn't reach here
			if (false === $name)
				return sprintf (__('[%s: Unknown notation type!]', TEXTDOMAIN),
					__('ScoreRender Error', TEXTDOMAIN));

			return $notations[$name]['starttag'] . "\n" .
				$render->get_music_fragment() . "\n" .
				$notations[$name]['endtag'];

		  default:
			if (DEBUG)
				return $render->get_command_output ();
			else
				return $render->get_error_msg ();
		}
	}

	// No errors, so generate HTML
	// Not nice to show source in alt text or title, since music source
	// is most likely multi-line, and some are quite long

	// This idea is taken from LatexRender demo site
	// FIXME: completely gone berserk if folder containing this plugin is a symlink, plugin_basename() sucks
	if ($scorerender_options['SHOW_SOURCE'])
	{
		$html = sprintf ("<form target='fragmentpopup' action='%s/%s/%s/showcode.php' method='post'>\n", get_bloginfo ('home'), PLUGINDIR, dirname (plugin_basename (__FILE__)));
		$html .= sprintf ("<input type='image' name='music_image' class='scorerender-image' title='%s' alt='%s' src='%s/%s' />\n",
			__('Click on image to view source', TEXTDOMAIN),
			__('Music fragment', TEXTDOMAIN),
			$scorerender_options['CACHE_URL'], $result);

		$name = $render->get_notation_name ();

		// Shouldn't reach here
		if (false === $name)
			return '[ScoreRender Error: Unknown notation type!]';

		$content = $notations[$name]['starttag'] . "\n" .
			$render->get_music_fragment() . "\n" .
			$notations[$name]['endtag'];

		$html .= sprintf ("<input type='hidden' name='code' value='%s'>\n</form>\n",
			rawurlencode (htmlentities ($content, ENT_NOQUOTES, get_bloginfo ('charset'))));
	}
	else
	{
		$html .= sprintf ("<img class='scorerender-image' title='%s' alt='%s' src='%s/%s' />\n",
			__('Music fragment', TEXTDOMAIN),
			__('Music fragment', TEXTDOMAIN),
			$scorerender_options['CACHE_URL'], $result);
	}

	if ($scorerender_options['TRANSPARENT_IMAGE'] &&
	    $scorerender_options['SHOW_IE_TRANSPARENCY_WARNING']) 
	{
		$html .= '<br /><!--[if lt IE 7]><span class="ie6warning" style="font-size: smaller;">' . __('(<font color="red">Warning</font>: Internet Explorer &lt; 7 is incapable of displaying transparent PNG image, so the above image may not show properly in your browser as expected. Please either use any other browser such as <a href="http://www.getfirefox.com/" target="_blank">Firefox</a> or <a href="http://www.opera.com/" target="_blank">Opera</a>, or at least upgrade to IE 7. Alternatively, ask site admin to disable transparent image.)', TEXTDOMAIN) . "</span><![endif]-->\n";
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
 *
 * @param array $matches Matched music fragment in posts or comments. This variable must be supplied by {@link preg_match preg_match()} or {@link preg_match_all preg_match_all()}. Alternatively invoke this function with {@link preg_replace_callback preg_replace_callback()}.
 * @return string Either HTML content containing rendered image, or HTML error message in case rendering failed.
 */
function scorerender_filter ($matches)
{
	global $scorerender_options, $notations;

	// since preg_replace_callback only accepts single function,
	// we have to check which regex is matched here and create
	// corresponding object
	foreach (array_values ($notations) as $notation)
		if (preg_match ($notation['regex'], $matches[0]))
		{
			$render = new $notation['classname'];
			$progs = array();
			foreach ($notation['progs'] as $progname) {
				$progs["$progname"] = $scorerender_options[$progname];
			}
			$render->set_programs ($progs);

			if ($notation['classname'] == 'mupRender')
				$render->set_magic_file ($scorerender_options['MUP_MAGIC_FILE']);

			break;
		}

	if (empty ($render)) return $input;

	$render->set_imagemagick_path ($scorerender_options['CONVERT_BIN']);
	$render->set_inverted ($scorerender_options['INVERT_IMAGE']);
	$render->set_transparency ($scorerender_options['TRANSPARENT_IMAGE']);
	$render->set_temp_dir ($scorerender_options['TEMP_DIR']);
	$render->set_cache_dir ($scorerender_options['CACHE_DIR']);
	$render->set_max_length ($scorerender_options['CONTENT_MAX_LENGTH']);
	$render->set_img_width ($scorerender_options['IMAGE_MAX_WIDTH']);

	$input = trim (html_entity_decode ($matches[1]));
	$render->set_music_fragment ($input);

	return scorerender_process_content ($render);
}


/**
 * Renders music fragments contained inside posts / comments.
 *
 * Check if post/comment rendering should be enabled.
 * If yes, then apply {@link scorerender_filter} function on $content.
 *
 * @uses scorerender_filter Apply filter to content upon regular expression match
 * @param string $content The whole content of blog post / comment
 * @param boolean $is_post Whether content is from post or comment
 * @return string Converted blog post / comment content.
 */
function scorerender_do_conversion ($content, $is_post)
{
	global $scorerender_options, $notations;

	if (!$is_post && !$scorerender_options['COMMENT_ENABLED']) return $content;

	$regex_list = array();
	foreach (array_values ($notations) as $notation)
	{
		// unfilled program name = disable support
		foreach ($notation['progs'] as $prog)
			if (empty ($scorerender_options[$prog])) continue;
		$regex_list[] = $notation['regex'];
	};

	$limit = ($is_post) ? -1 :
		($scorerender_options['FRAGMENT_PER_COMMENT'] <= 0) ? -1 :
		$scorerender_options['FRAGMENT_PER_COMMENT'];

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

	if (0 < $post_count)
		foreach ($posts as $post)
			$frag_count += array_sum (scorerender_get_fragment_count ($post));


	// followed by comments
	$query_substr = array();
	foreach (array_values ($notations) as $notation)
		$query_substr[] .= "comment_content LIKE '%" . $notation['endtag'] . "%'";
	$query = sprintf ("SELECT comment_content FROM $wpdb->comments WHERE comment_approved = '1' AND (%s)",
			  implode (" OR ", $query_substr));

	$comments = $wpdb->get_col ($query);
	$comment_count = count ($comments);

	if (0 < $comment_count)
		foreach ($comments as $comment)
			$frag_count += array_sum (scorerender_get_fragment_count ($comment));

	$num_of_posts_str = sprintf (__ngettext ('%d post', '%d posts', $post_count, TEXTDOMAIN), $post_count);
	$num_of_comments_str = sprintf (__ngettext ('%d comment', '%d comments', $comment_count, TEXTDOMAIN), $comment_count);


	if ((0 === $post_count) && (0 === $comment_count))
		$first_sentence = __('This blog is currently empty.', TEXTDOMAIN);

	elseif (0 === $frag_count)
		$first_sentence = __('There is no music fragment in your blog.', TEXTDOMAIN);

	elseif (0 === $comment_count)
		$first_sentence = sprintf (
			__ngettext ('There is %d music fragment contained in %s.',
			            'There are %d music fragments contained in %s.',
			            $frag_count, TEXTDOMAIN),
			$frag_count, $num_of_posts_str);

	elseif (0 === $post_count)
		$first_sentence = sprintf (
			__ngettext ('There is %d music fragment contained in %s.',
			            'There are %d music fragments contained in %s.',
			            $frag_count, TEXTDOMAIN),
			$frag_count, $num_of_comments_str);

	else
		$first_sentence = sprintf (
			__ngettext ('There is %d music fragment contained in %s and %s.',
			            'There are %d music fragments contained in %s and %s.',
			            $frag_count, TEXTDOMAIN),
			$frag_count, $num_of_posts_str, $num_of_comments_str);


	$img_count = scorerender_get_num_of_images();

	if ( 0 > $img_count )
		$second_sentence = sprintf (__('<font color="red">The cache directory is either non-existant or not readable.</font> Please <a href="%s">change the setting</a> and make sure the directory exists.', TEXTDOMAIN), 'options-general.php?page=' . plugin_basename (__FILE__));
	else
		$second_sentence = sprintf (
			__ngettext ('Currently %d image are rendered and cached.',
			            'Currently %d images are rendered and cached.',
			            $img_count, TEXTDOMAIN),
		        $img_count);
?>
	<div>
	<h3><?php _e('ScoreRender', TEXTDOMAIN) ?></h3>
	<p><?php echo $first_sentence . '  ' . $second_sentence; ?></p>
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
	if ( function_exists ('current_user_can') && !current_user_can ('manage_options') )
		wp_die (__('Cheatin&#8217; uh?', TEXTDOMAIN));

	global $defalt_tmp_dir, $scorerender_options;

	$newopt = (array) $_POST['ScoreRender'];
	transform_paths ($newopt, TRUE);

	$messages = array
	(
		'temp_dir_not_writable'    => array ('level' => MSG_WARNING, 'content' => __('Temporary directory is NOT writable! Will fall back to system default setting.')),
		'cache_dir_undefined'      => array ('level' => MSG_FATAL  , 'content' => __('Cache directory is NOT defined! Image can not be placed inside appropriate directory. The plugin will stop working.', TEXTDOMAIN)),
		'cache_dir_not_writable'   => array ('level' => MSG_FATAL  , 'content' => __('Cache directory is NOT writable! Image can not be placed inside appropriate directory. The plugin will stop working.', TEXTDOMAIN)),
		'cache_url_undefined'      => array ('level' => MSG_FATAL  , 'content' => __('Cache URL is NOT defined! The plugin will stop working.', TEXTDOMAIN)),
		'wrong_content_length'     => array ('level' => MSG_WARNING, 'content' => __('Content length is not a non-negative integer. Value discarded.', TEXTDOMAIN)),
		'wrong_frag_per_comment'   => array ('level' => MSG_WARNING, 'content' => __('Fragment per comment is not a non-negative integer. Value discarded.', TEXTDOMAIN)),
		'wrong_image_max_width'    => array ('level' => MSG_WARNING, 'content' => __('Image maximum width must be positive integer >= 72. Value discarded.', TEXTDOMAIN)),
		'convert_bin_problem'      => array ('level' => MSG_FATAL  , 'content' => __('<tt>convert</tt> program is NOT defined or NOT executable! The plugin will stop working.', TEXTDOMAIN)),
		'abcm2ps_bin_problem'      => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>abcm2ps</tt>', 'ABC')),
		'lilypond_bin_problem'     => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>lilypond</tt>', 'LilyPond')),
		'mup_bin_problem'          => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>mup</tt>', 'Mup')),
		'pmw_bin_problem'          => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>pmw</tt>', 'Philip\'s Music Writer')),
	);

	/*
	 * general options
	 */
	if ( ! empty ($newopt['TEMP_DIR']) &&
		!is_writable ($newopt['TEMP_DIR']) )
	{
		$errmsgs[] = 'temp_dir_not_writable';
		$newopt['TEMP_DIR'] = '';
	}

	if ( empty ($newopt['CACHE_DIR']) )
		$errmsgs[] = 'cache_dir_undefined';
	elseif ( !is_writable ($newopt['CACHE_DIR']) )
		$errmsgs[] = 'cache_dir_not_writable';

	if ( empty ($newopt['CACHE_URL']) )
		$errmsgs[] = 'cache_url_undefined';

	if ( ! ScoreRender::is_prog_usable ('ImageMagick', $newopt['CONVERT_BIN'], '-version') )
		$errmsgs[] = 'convert_bin_problem';

	$newopt['SHOW_SOURCE']       = isset ($newopt['SHOW_SOURCE']);
	$newopt['INVERT_IMAGE']      = isset ($newopt['INVERT_IMAGE']);
	$newopt['TRANSPARENT_IMAGE'] = isset ($newopt['TRANSPARENT_IMAGE']);
	$newopt['SHOW_IE_TRANSPARENCY_WARNING'] = isset ($newopt['SHOW_IE_TRANSPARENCY_WARNING']);
	$newopt['COMMENT_ENABLED']   = isset ($newopt['COMMENT_ENABLED']);

	if (!ctype_digit ($newopt['CONTENT_MAX_LENGTH']))
	{
		$errmsgs[] = 'wrong_content_length';
		unset ($newopt['CONTENT_MAX_LENGTH']);
	}

	if (isset ($newopt['FRAGMENT_PER_COMMENT']) &&
		!ctype_digit ($newopt['FRAGMENT_PER_COMMENT']))
	{
		$errmsgs[] = 'wrong_frag_per_comment';
		unset ($newopt['FRAGMENT_PER_COMMENT']);
	}

	if (!ctype_digit ($newopt['IMAGE_MAX_WIDTH']) || ($newopt['IMAGE_MAX_WIDTH'] < (1 * DPI)))
	{
		$errmsgs[] = 'wrong_image_max_width';
		unset ($newopt['IMAGE_MAX_WIDTH']);
	}

	if (! empty ($newopt['LILYPOND_BIN']) && ! lilypondRender::is_notation_usable ('prog=' . $newopt['LILYPOND_BIN']))
	{
		$errmsgs[] = 'lilypond_bin_problem';
	}

	if (! empty ($newopt['MUP_BIN']) && ! mupRender::is_notation_usable ('prog=' . $newopt['MUP_BIN']))
	{
		$errmsgs[] = 'mup_bin_problem';
	}

	if (! empty ($newopt['ABCM2PS_BIN']) && ! abcRender::is_notation_usable ('prog=' . $newopt['ABCM2PS_BIN']))
	{
		$errmsgs[] = 'abcm2ps_bin_problem';
	}

	if (! empty ($newopt['PMW_BIN']) && ! pmwRender::is_notation_usable ('prog=' . $newopt['PMW_BIN']))
	{
		$errmsgs[] = 'pmw_bin_problem';
	}

	$scorerender_options = array_merge ($scorerender_options, $newopt);
	transform_paths ($scorerender_options, TRUE);
	update_option ('scorerender_options', $scorerender_options);
	transform_paths ($scorerender_options, FALSE);

	if ( !empty ($errmsgs) )
	{
		foreach (array_values ($errmsgs) as $m)
		{
			if ($messages[$m]['level'] == MSG_WARNING)
				echo '<div id="scorerender-error-' . $messages[$m] . '" class="updated fade-800000"><p><strong>' . sprintf (__('WARNING: %s', TEXTDOMAIN), $messages[$m]['content']) . "</strong></p></div>\n";
			elseif ($messages[$m]['level'] == MSG_FATAL)
				echo '<div id="scorerender-error-' . $messages[$m] . '" class="updated" style="background-color: #800000; color: white;"><p><strong>' . sprintf (__('ERROR: %s', TEXTDOMAIN), $messages[$m]['content']) . "</strong></p></div>\n";
		}
	}
	else
	{
		echo '<div id="message" class="updated fade"><p><strong>' .
			__('Options saved.', TEXTDOMAIN) . "</strong></p></div>\n";
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
		<h3><?php _e('Path options', TEXTDOMAIN) ?></h3>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<tr valign="top">
			<th scope="row"><?php _e('Temporary directory:', TEXTDOMAIN) ?></th>
			<td>
				<input name="ScoreRender[TEMP_DIR]" class="code" type="text" id="temp_dir" value="<?php echo $scorerender_options['TEMP_DIR']; ?>" size="60" /><br />
				<?php _e('Must be writable and ideally located out of web-accessible area. System default will be used if left blank.', TEXTDOMAIN) ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache directory:', TEXTDOMAIN) ?></th>
			<td>
				<input name="ScoreRender[CACHE_DIR]" class="code" type="text" id="cache_dir" value="<?php echo $scorerender_options['CACHE_DIR']; ?>" size="60" /><br />
				<?php _e('Must be writable and located inside the web-accessible area.', TEXTDOMAIN) ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache URL:', TEXTDOMAIN) ?></th>
			<td>
				<input name="ScoreRender[CACHE_URL]" class="code" type="text" id="cache_url" value="<?php echo $scorerender_options['CACHE_URL']; ?>" size="60" /><br />
				<?php _e('Must correspond to the image cache directory above.', TEXTDOMAIN) ?>
			</td>
		</tr>
		</table>
	</fieldset>
<?php
}


/**
 * Section of admin page about program and file locations
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_prog ()
{
	global $scorerender_options;
?>
	<fieldset class="options">
		<h3><?php _e('Program and file locations', TEXTDOMAIN) ?></h3>
		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<caption><?php _e('ImageMagick 6.x <code>convert</code> must be present and working. For each kind of notation, leaving corresponding program location empty means disabling that notation support automatically, except GUIDO which does not use any program.', TEXTDOMAIN); ?></caption>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>convert</code>') ?></th>
			<td>
				<input name="ScoreRender[CONVERT_BIN]" class="code" type="text" id="convert_bin" value="<?php echo $scorerender_options['CONVERT_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>lilypond</code>'); ?></th>
			<td>
				<input name="ScoreRender[LILYPOND_BIN]" class="code" type="text" id="lilypond_bin" value="<?php echo $scorerender_options['LILYPOND_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_BIN]" class="code" type="text" id="mup_bin" value="<?php echo $scorerender_options['MUP_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s magic file:', TEXTDOMAIN), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_MAGIC_FILE]" class="code" type="text" id="mup_magic_file" value="<?php echo $scorerender_options['MUP_MAGIC_FILE']; ?>" size="50" />
				<br />
				<?php printf (__('Leave it empty if you have not <a href="%s">registered</a> Mup. This file must be readable by the user account running web server.', TEXTDOMAIN), 'http://www.arkkra.com/doc/faq.html#payment'); ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>abcm2ps</code>'); ?></th>
			<td>
				<input name="ScoreRender[ABCM2PS_BIN]" class="code" type="text" id="abcm2ps_bin" value="<?php echo $scorerender_options['ABCM2PS_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>pmw</code>'); ?></th>
			<td>
				<input name="ScoreRender[PMW_BIN]" class="code" type="text" id="pmw_bin" value="<?php echo $scorerender_options['PMW_BIN']; ?>" size="50" />
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
		<h3><?php _e('Image options', TEXTDOMAIN) ?></h3>
		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<tr valign="top">
			<th scope="row"><?php _e('Max image width (pixel):', TEXTDOMAIN) ?></th>
			<td>
				<input type="text" name="ScoreRender[IMAGE_MAX_WIDTH]" id="image_max_width" value="<?php echo $scorerender_options['IMAGE_MAX_WIDTH']; ?>" size="6" />
				<label for="image_max_width"><?php _e('(Default is 360)', TEXTDOMAIN) ?></label><br /><?php _e('Note that this value is just an approximation, please allow for &#x00B1;10% difference. Some programs like lilypond would not use the full image width if passage is not long enough.', TEXTDOMAIN) ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th score="row"><?php _e('Clickable image:', TEXTDOMAIN) ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[SHOW_SOURCE]" id="show_input" value="1" <?php checked('1', $scorerender_options['SHOW_SOURCE']); ?> />
				<label for="show_input"><?php _e('Show music source in new browser window/tab when image is clicked', TEXTDOMAIN); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th score="row"><?php _e('Image post-processing', TEXTDOMAIN) ?></th>
			<td>
				<p><input type="checkbox" name="ScoreRender[INVERT_IMAGE]" id="invert_image" value="1" <?php checked('1', $scorerender_options['INVERT_IMAGE']); ?> />
				<label for="invert_image"><?php _e('White colored notes (default is black)', TEXTDOMAIN); ?></label></p>
				<p><input type="checkbox" name="ScoreRender[TRANSPARENT_IMAGE]" id="transparent_image" value="1" <?php checked('1', $scorerender_options['TRANSPARENT_IMAGE']); ?> onclick="var box = document.getElementById('show_ie_transparency_warning'); box.disabled = !box.disabled; return true;" />
				<label for="transparent_image"><?php _e('Use transparent background (IE &lt;= 6 does not support transparent PNG)', TEXTDOMAIN); ?></label></p>
				<p style="padding-left: 30px;"><input type="checkbox" name="ScoreRender[SHOW_IE_TRANSPARENCY_WARNING]" id="show_ie_transparency_warning" value="1" <?php checked('1', $scorerender_options['SHOW_IE_TRANSPARENCY_WARNING']); if (1 != $scorerender_options['TRANSPARENT_IMAGE']) { echo ' disabled="disabled"'; } ?> />
				<label for="show_ie_transparency_warning"><?php _e('Show warning message when such browser is used', TEXTDOMAIN) ?></label></p>
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
		<h3><?php _e('Content options', TEXTDOMAIN) ?></h3>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<tr valign="top">
			<th scope="row"><?php _e('Maximum length per fragment:', TEXTDOMAIN) ?></th>
			<td>
				<input type="text" name="ScoreRender[CONTENT_MAX_LENGTH]" id="content_max_length" value="<?php echo $scorerender_options['CONTENT_MAX_LENGTH']; ?>" size="6" />
				<label for="content_max_length"><?php _e('(0 means unlimited)', TEXTDOMAIN) ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('When rendering failed:', TEXTDOMAIN); ?></th>
			<td>
				<input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_message" value="1" <?php checked(ON_ERR_SHOW_MESSAGE, $scorerender_options['ERROR_HANDLING']); ?> />
				<label for="on_err_show_message"><?php _e('Show error message', TEXTDOMAIN) ?></label><br />
				<input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_fragment" value="2" <?php checked(ON_ERR_SHOW_FRAGMENT, $scorerender_options['ERROR_HANDLING']); ?> />
				<label for="on_err_show_fragment"><?php _e('Show original, unmodified music fragment', TEXTDOMAIN) ?></label><br />
				<input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_nothing" value="3" <?php checked(ON_ERR_SHOW_NOTHING, $scorerender_options['ERROR_HANDLING']); ?> />
				<label for="on_err_show_nothing"><?php _e('Show nothing', TEXTDOMAIN) ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Comment rendering:', TEXTDOMAIN) ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[COMMENT_ENABLED]" id="comment_enabled" value="1" <?php checked('1', $scorerender_options['COMMENT_ENABLED']); ?> onclick="var box = document.getElementById('fragment_per_comment'); box.disabled = !box.disabled; return true;" />
				<label for="comment_enabled"><?php printf ('%s %s', __('Enable rendering for comments', TEXTDOMAIN), '<span style="font-weight: bold; color: red;">' . __('(Only turn on if commenters are trusted)', TEXTDOMAIN) . '</span>'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Maximum number of fragment per comment:', TEXTDOMAIN) ?></th>
			<td>
				<input type="text" name="ScoreRender[FRAGMENT_PER_COMMENT]" id="fragment_per_comment" value="<?php echo $scorerender_options['FRAGMENT_PER_COMMENT']; ?>" size="6" <?php if (1 != $scorerender_options['COMMENT_ENABLED']) { echo ' disabled="disabled"'; } ?> />
				<label for="fragment_per_comment"><?php _e('(0 means unlimited)', TEXTDOMAIN) ?><br /><?php printf (__('If you don&#8217;t want comment rendering, turn off &#8216;<i>%s</i>&#8217; checkbox above instead. This option does not affect posts and pages.', TEXTDOMAIN), __('Enable rendering for comments', TEXTDOMAIN)); ?></label>
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
		<h3><?php _e('Caching', TEXTDOMAIN) ?></h3>
<?php
	$img_count = scorerender_get_num_of_images();

	if ( 0 > $img_count )
	{
		echo "<font color='red'>" . __('Cache directory is not readable, thus no image count is shown.', TEXTDOMAIN) . "<br />";
		echo __('Please change &#8216;Image cache directory&#8217; setting, or fix its permission.', TEXTDOMAIN) . "</font>\n";
	}
	else
	{
		printf (__ngettext("Cache directory contains %d image.\n",
			           "Cache directory contains %d images.\n",
			           $img_count, TEXTDOMAIN), $img_count);
	}

?>
<?php if ( is_writable ($scorerender_options['CACHE_DIR']) ) : ?>
		<input type="submit" name="clear_cache" value="<?php _e('Clear Cache &raquo;', TEXTDOMAIN) ?>" />
<?php else : ?>
		<input type="submit" name="clear_cache" disabled="disabled" value="<?php _e('Clear Cache &raquo;', TEXTDOMAIN) ?>" />
		<br /><font color="red"><?php _e('Cache can&#8217;t be cleared because directory is not writable.', TEXTDOMAIN) ?><br /><?php _e('Please change &#8216;Image cache directory&#8217; setting, or fix its permission.', TEXTDOMAIN) ?></font>
<?php endif; ?>
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
 * @uses scorerender_remove_cache Activated when 'Remove Cache' button is clicked
 * @uses scorerender_update_options Activate when 'Update Options' button is clicked
 * @uses scorerender_admin_section_path Admin page -- path options
 * @uses scorerender_admin_section_prog Admin page -- program and file locations
 * @uses scorerender_admin_section_image Admin page -- image options
 * @uses scorerender_admin_section_content Admin page -- content options
 * @uses scorerender_admin_section_caching Admin page -- caching administration
 *
 * @access private
 */
function scorerender_admin_options ()
{
	global $scorerender_options, $notations;

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
	<h2><?php _e('ScoreRender options', TEXTDOMAIN) ?></h2>

	<p><?php _e('ScoreRender renders inline music fragments inside blog post and/or comment as images. Currently it supports the following notations (each notation name is followed by its starting and ending tag):', TEXTDOMAIN); ?></p>
	<ul>
		<li><a target="_blank" href="http://www.lilypond.org/">Lilypond</a> (<?php printf ('<code>%s</code>, <code>%s</code>', $notations['lilypond']['starttag'], $notations['lilypond']['endtag']); ?>)</li>
		<li><dl><dt><a target="_blank" href="http://www.arkkra.com/">Mup</a> (<?php printf ('<code>%s</code>, <code>%s</code>', $notations['mup']['starttag'], $notations['mup']['endtag']); ?>)</dt><dd><?php printf ('Used by Mup itself and %s', '<a target="_blank" href="http://noteedit.berlios.de/">Noteedit</a>'); ?></dd></dl></li>
		<li><a target="_new" href="http://www.informatik.tu-darmstadt.de/AFS/GUIDO/">GUIDO</a> (<?php printf ('<code>%s</code>, <code>%s</code>', $notations['guido']['starttag'], $notations['guido']['endtag']); ?>)</li>
		<li><dl><dt><a target="_blank" href="http://abcnotation.org.uk/">ABC</a> (<?php printf ('<code>%s</code>, <code>%s</code>', $notations['abc']['starttag'], $notations['abc']['endtag']); ?>)</dt><dd><?php printf ('Used by various programs like %s, %s or %s',
			'<a target="_blank" href="http://www.ihp-ffo.de/~msm/">abc2ps</a>',
			'<a target="_blank" href="http://moinejf.free.fr/">abcm2ps</a>',
			'<a target="_blank" href="http://trillian.mit.edu/~jc/music/abc/src/">jcabc2ps</a>'); ?></dd></dl></li>
		<li><a target="_blank" href="http://www.quercite.com/pmw.html">Philip's Music Writer</a> (<?php printf ('<code>%s</code>, <code>%s</code>', $notations['pmw']['starttag'], $notations['pmw']['endtag']); ?>)</li>
	</ul>

<?php
	// path options
	scorerender_admin_section_path();

	// program location options
	scorerender_admin_section_prog();

	// image options
	scorerender_admin_section_image();

	// content options
	scorerender_admin_section_content();

	// caching options
	scorerender_admin_section_caching();
?>
	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;', TEXTDOMAIN) ?>" />
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
	add_options_page (__('ScoreRender options', TEXTDOMAIN), 'ScoreRender', 9, __FILE__, 'scorerender_admin_options');
}

/*
Remove tag balancing filter

There seems to be an bug in the balanceTags function of
wp-includes/functions-formatting.php which means that ">>" are
converted to "> >", and "<<" to "< <", causing syntax errors.  This is
part of the LilyPond syntax for parallel music, and Mup syntax for
attribute change within a bar.  Since balancing filter is also used
in get_the_content() before any plugin is activated, removing
filter is of no use.
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
			. sprintf (__('<strong>OPTION CONFLICT</strong>: The &#8216;correct invalidly nested XHTML automatically&#8217; option conflicts with ScoreRender plugin, because it will mangle certain Lilypond and Mup fragments. The option is available in <a href="%s">Writing option page</a>.', TEXTDOMAIN), "options-writing.php")
			. "</p></div>";
	}
	add_filter ('admin_notices', 'turn_off_balance_tags');
}

add_action ('init', 'scorerender_init_textdomain');
add_filter ('activity_box_end', 'scorerender_activity_box');
add_filter ('admin_menu', 'scorerender_admin_menu');

// earlier than default priority, since
// smilies conversion and wptexturize() can mess up the content
add_filter ('the_excerpt' ,
	create_function ('$content',
		'return scorerender_do_conversion ($content, TRUE);'),
	2);
add_filter ('the_content' ,
	create_function ('$content',
		'return scorerender_do_conversion ($content, TRUE);'),
	2);
add_filter ('comment_text',
	create_function ('$content',
		'return scorerender_do_conversion ($content, FALSE);'),
	2);

?>
