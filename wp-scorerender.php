<?php
/*
Plugin Name: ScoreRender
Plugin URI: http://scorerender.abelcheung.org/
Description: Renders inline music score fragments in WordPress. Heavily based on FigureRender from Chris Lamb.
Author: Abel Cheung
Version: 0.2.50
Author URI: http://me.abelcheung.org/
*/

/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.2.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 * @copyright Copyright (C) 2007, 08 Abel Cheung
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/**
 * Database version.
 *
 * This number must be incremented every time when option has been changed, removed or added.
 */
define ('DATABASE_VERSION', 12);

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

define ('TYPES_ONLY', 1);
define ('VALUES_ONLY', 2);

/*
 * Global Variables
 */

/**
 * Stores all current ScoreRender settings.
 * @global array $sr_options
 */
$sr_options = array ();

/**
 * Array of supported music notation syntax and relevant attributes.
 *
 * Array keys are names of the music notations, in lower case.
 * Their values are arrays themselves, containing:
 * - regular expression matching relevant notation
 * - start tag and end tag
 * - class file and class name for corresponding notation
 * - programs necessary for that notation to work
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
 * Default options used for first-time install. Also contains the type of value,
 * so other actions can be applied depending on setting type.
 * @global array $default_settings
 */
function scorerender_get_def_settings ($return_type = 0)
{
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

	$cachefolder = scorerender_get_upload_dir ();

	$default_settings = array
	(
		'DB_VERSION'           => array ('type' =>   'none', 'value' => DATABASE_VERSION),
		'TEMP_DIR'             => array ('type' =>   'path', 'value' => sys_get_temp_dir()),
		'CACHE_DIR'            => array ('type' =>   'path', 'value' => $cachefolder['path']),
		'CACHE_URL'            => array ('type' =>    'url', 'value' => $cachefolder['url']),

		'IMAGE_MAX_WIDTH'      => array ('type' =>    'int', 'value' => 360),
		'INVERT_IMAGE'         => array ('type' =>   'bool', 'value' => false),
		'TRANSPARENT_IMAGE'    => array ('type' =>   'bool', 'value' => true),
		'USE_IE6_PNG_ALPHA_FIX'=> array ('type' =>   'bool', 'value' => true),
		'SHOW_SOURCE'          => array ('type' =>   'bool', 'value' => false),
		'COMMENT_ENABLED'      => array ('type' =>   'bool', 'value' => false),
		'ERROR_HANDLING'       => array ('type' =>   'enum', 'value' => ON_ERR_SHOW_MESSAGE),

		'CONTENT_MAX_LENGTH'   => array ('type' =>    'int', 'value' => 4096),
		'FRAGMENT_PER_COMMENT' => array ('type' =>    'int', 'value' => 1),

		'CONVERT_BIN'          => array ('type' =>   'prog', 'value' => $defprog['convert']),
		'LILYPOND_BIN'         => array ('type' =>   'prog', 'value' => $defprog['lilypond']),
		'MUP_BIN'              => array ('type' =>   'prog', 'value' => $defprog['mup']),
		'ABCM2PS_BIN'          => array ('type' =>   'prog', 'value' => $defprog['abc2ps']),
		'PMW_BIN'              => array ('type' =>   'prog', 'value' => $defprog['pmw']),
		'MUP_MAGIC_FILE'       => array ('type' =>   'path', 'value' => ''),
	);

	$retval = array();
	switch ($return_type)
	{
	  case TYPES_ONLY:
		foreach ($default_settings as $key => $val)
			$retval += array ($key => $val['type']);
		return $retval;
	  case VALUES_ONLY:
		foreach ($default_settings as $key => $val)
			$retval += array ($key => $val['value']);
		return $retval;
	  default:
		return $default_settings;
	}
};


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
	load_plugin_textdomain (TEXTDOMAIN, PLUGINDIR.'/'.plugin_basename (dirname (__FILE__)));
	load_plugin_textdomain (TEXTDOMAIN);
	load_plugin_textdomain (TEXTDOMAIN, ABSPATH . LANGDIR);
}


/**
 * Transform all path related options in ScoreRender settings
 *
 * @since 0.2.50
 * @uses get_path_presentation()
 * @param array $setting The settings to be transformed, either from existing setting or from newly submitted setting
 * @param boolean $is_internal Whether to always transform into Unix format, which is used for storing values into database. FALSE means using OS native representation.
 */
function transform_paths (&$setting, $is_internal)
{
	if (!is_array ($setting)) return;
	
	$default_settings = scorerender_get_def_settings(TYPES_ONLY);
	
	// Transform path and program settings to unix presentation
	foreach ($default_settings as $key => $type)
		if ( ($type == 'path') || ($type == 'prog') )
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
 * @uses get_path_presentation()
 * @uses is_absolute_path()
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
		$path = 'wp-content/uploads';

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

function scorerender_populate_options ()
{
	global $sr_options;

	$defaults = scorerender_get_def_settings(VALUES_ONLY);

	// safe guard
	if (empty ($defaults)) return;

	if (empty ($sr_options))
		$sr_options = $defaults;
	else
	{
		// remove current settings not present in newest schema, then merge default values
		$sr_options = array_intersect_key ($sr_options, $defaults);
		$sr_options = array_merge ($defaults, $sr_options);
		$sr_options['DB_VERSION'] = DATABASE_VERSION;
	}
}

/**
 * Retrieve ScoreRender options from database.
 *
 * If the {@link DATABASE_VERSION} constant contained inside MySQL database is
 * small than that of PHP file (most likely occur when plugin is
 * JUST upgraded), then it also merges old config with new default
 * config and update the options in database.
 *
 * @uses scorerender_get_upload_dir()
 * @uses transform_paths()
 */
function scorerender_get_options ()
{
	global $sr_options;

	$sr_options = get_option ('scorerender_options');

	if (!is_array ($sr_options))
		$sr_options = array();
	elseif (array_key_exists ('DB_VERSION', $sr_options) &&
		($sr_options['DB_VERSION'] >= DATABASE_VERSION) )
	{
		transform_paths ($sr_options, FALSE);
		return;
	}

	// Special handling for certain versions
	if ($sr_options['DB_VERSION'] <= 9)
		if ( $sr_options['LILYPOND_COMMENT_ENABLED'] ||
		     $sr_options['MUP_COMMENT_ENABLED']      ||
		     $sr_options['ABC_COMMENT_ENABLED']      ||
		     $sr_options['GUIDO_COMMENT_ENABLED'] )
		{
			$sr_options['COMMENT_ENABLED'] = true;
		}

	scorerender_populate_options ();

	transform_paths ($sr_options, TRUE);
	update_option ('scorerender_options', $sr_options);
	transform_paths ($sr_options, FALSE);
	
	return;
}


/**
 * Generate HTML content from error message or rendered image
 *
 * @uses ScoreRender::render()
 * @uses ScoreRender::get_notation_name()
 * @uses ScoreRender::get_music_fragment()
 * @uses ScoreRender::get_error_msg()
 *
 * @param object $render PHP object created for rendering relevant music fragment
 * @return string HTML content containing image if successful, otherwise may display error message or empty string, depending on setting.
 */
function scorerender_process_content ($render)
{
	global $sr_options, $notations;

	$result = $render->render();

	if (false === $result)
	{
		switch ($sr_options['ERROR_HANDLING'])
		{
		  case ON_ERR_SHOW_NOTHING:
			return '';

		  case ON_ERR_SHOW_FRAGMENT:
			try {
				$name = $render->get_notation_name ();
			} catch (Exception $e) {
				return $e->getMessage();
			}

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
	if ($sr_options['SHOW_SOURCE'])
	{
		$html = sprintf ("<form target='fragmentpopup' action='%s/%s/%s/showcode.php' method='post'>\n", get_bloginfo ('home'), PLUGINDIR, dirname (plugin_basename (__FILE__)));
		$html .= sprintf ("<input type='image' name='music_image' class='scorerender-image' title='%s' alt='%s' src='%s/%s' />\n",
			__('Click on image to view source', TEXTDOMAIN),
			__('Music fragment', TEXTDOMAIN),
			$sr_options['CACHE_URL'], $result);

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
			$sr_options['CACHE_URL'], $result);
	}

	return $html;
}



/**
 * Generate converted HTML fragment from music notation fragment
 *
 * Create PHP object for each kind of matched music notation, and set
 * all relevant parameters needed for rendering. Afterwards, pass
 * everything to {@link scorerender_process_content()} for rendering.
 *
 * If no PHP class exists corresponding to certain notation, then 
 * unconverted content is returned immediately.
 *
 * @uses scorerender_process_content()
 * @uses ScoreRender::set_programs()
 * @uses ScoreRender::set_imagemagick_path()
 * @uses ScoreRender::set_inverted()
 * @uses ScoreRender::set_transparency()
 * @uses ScoreRender::set_temp_dir()
 * @uses ScoreRender::set_cache_dir()
 * @uses ScoreRender::set_max_length()
 * @uses ScoreRender::set_img_width()
 * @uses ScoreRender::set_music_fragment()
 * @uses mupRender::set_magic_file()
 *
 * @param array $matches Matched music fragment in posts or comments. This variable must be supplied by {@link preg_match preg_match()} or {@link preg_match_all preg_match_all()}. Alternatively invoke this function with {@link preg_replace_callback preg_replace_callback()}.
 * @return string Either HTML content containing rendered image, or HTML error message in case rendering failed.
 */
function scorerender_filter ($matches)
{
	global $sr_options, $notations;

	// since preg_replace_callback only accepts single function,
	// we have to check which regex is matched here and create
	// corresponding object
	foreach (array_values ($notations) as $notation)
		if (preg_match ($notation['regex'], $matches[0]))
		{
			$render = new $notation['classname'];

			$progs = array();
			foreach ($notation['progs'] as $progname) {
				$progs["$progname"] = $sr_options[$progname];
			}
			$render->set_programs ($progs);

			break;
		}

	if (empty ($render)) return $input;

	$render->set_imagemagick_path ($sr_options['CONVERT_BIN']);
	$render->set_inverted ($sr_options['INVERT_IMAGE']);
	$render->set_transparency ($sr_options['TRANSPARENT_IMAGE']);
	$render->set_temp_dir ($sr_options['TEMP_DIR']);
	$render->set_cache_dir ($sr_options['CACHE_DIR']);
	$render->set_max_length ($sr_options['CONTENT_MAX_LENGTH']);
	$render->set_img_width ($sr_options['IMAGE_MAX_WIDTH']);

	do_action ('sr_set_class_variable', $sr_options);

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
 * @uses scorerender_filter() Apply filter to content upon regular expression match
 * @param string $content The whole content of blog post / comment
 * @param boolean $is_post Whether content is from post or comment
 * @return string Converted blog post / comment content.
 */
function scorerender_do_conversion ($content, $is_post)
{
	global $sr_options, $notations, $post;

	if (!$is_post && !$sr_options['COMMENT_ENABLED']) return $content;

	if ($is_post)
	{
		$author = new WP_User ($post->post_author);
		if (!$author->has_cap ('unfiltered_html')) return $content;
	}

	$regex_list = array();
	foreach (array_values ($notations) as $notation)
	{
		// unfilled program name = disable support
		foreach ($notation['progs'] as $prog)
			if (empty ($sr_options[$prog])) continue;
		$regex_list[] = $notation['regex'];
	};

	$limit = ($is_post)                                 ? -1 :
	         ($sr_options['FRAGMENT_PER_COMMENT'] <= 0) ? -1 :
	          $sr_options['FRAGMENT_PER_COMMENT'];

	return preg_replace_callback ($regex_list, 'scorerender_filter', $content, $limit);
}

function scorerender_add_ie6_style()
{
	// FIXME: hardcoded path is not nice
	$uri = get_bloginfo('url').'/'.PLUGINDIR.'/scorerender';
	$path = parse_url ($uri, PHP_URL_PATH);
?>
<!--[if lte IE 6]>
<style type="text/css">
.scorerender-image { behavior: url(<?php echo $path; ?>/iepngfix.php); }
</style>
<![endif]-->
<?php
}

if (defined ('WP_ADMIN'))
	include_once ('scorerender-admin.php');

/*
Remove tag balancing filter

There seems to be an bug in the balanceTags function of
wp-includes/functions-formatting.php which means that ">>" are
converted to "> >", and "<<" to "< <", causing syntax errors.  This is
part of the LilyPond syntax for parallel music, and Mup syntax for
attribute change within a bar.  Since balancing filter is also used
in get_the_content() before any plugin is activated, removing
filter is of no use.

remove_filter ('content_save_pre', 'balanceTags', 50);
remove_filter ('excerpt_save_pre', 'balanceTags', 50);
remove_filter ('comment_save_pre', 'balanceTags', 50);
remove_filter ('pre_comment_content', 'balanceTags', 30);
remove_filter ('comment_text', 'force_balance_tags', 25);
 */

// retrieve plugin options first
scorerender_get_options ();

// initialize translation files
add_action ('init', 'scorerender_init_textdomain');

// IE6 PNG translucency filter
if ($sr_options['TRANSPARENT_IMAGE'] &&
    $sr_options['USE_IE6_PNG_ALPHA_FIX'])
	add_action ('wp_head', 'scorerender_add_ie6_style');

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
