<?php
/*
Plugin Name: ScoreRender
Plugin URI: http://scorerender.abelcheung.org/
Description: Renders inline music score fragments in posts and pages.
Author: Abel Cheung
Version: 0.3.50
Author URI: http://me.abelcheung.org/
*/

/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
 */

/**
 * Database version.
 *
 * This number must be incremented every time when option has been changed, removed or added.
 */
define ('DATABASE_VERSION', 17);

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
define (SR_DEBUG, FALSE);

/*
 * How error is handled when rendering failed
 */
define ('ON_ERR_SHOW_MESSAGE' , '1');
define ('ON_ERR_SHOW_FRAGMENT', '2');
define ('ON_ERR_SHOW_NOTHING' , '3');

define ('MSG_WARNING', 1);
define ('MSG_FATAL'  , 2);

define ('TYPES_AND_VALUES', 0);
define ('TYPES_ONLY'      , 1);
define ('VALUES_ONLY'     , 2);

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
$notations = array();

/**
 * Utility functions used by ScoreRender
 */
require_once('scorerender-utils.php');

/**
 * Main ScoreRender class
 */
require_once('scorerender-class.php');

require_once('notation/abc.php');
require_once('notation/guido.php');
require_once('notation/lilypond.php');
require_once('notation/mup.php');
require_once('notation/pmw.php');

/**
 * Default options used for first-time install. Also contains the type of value,
 * so other actions can be applied depending on setting type.
 *
 * @uses is_windows() Determine default program path based on operating system
 * @uses sys_get_temp_dir()
 */
function scorerender_get_def_settings ($return_type = TYPES_AND_VALUES)
{
	$retval = array();

	$default_settings = array
	(
		'DB_VERSION'           => array ('type' => 'none', 'value' => DATABASE_VERSION),
		'TEMP_DIR'             => array ('type' => 'path', 'value' => sys_get_temp_dir()),
		'CACHE_DIR'            => array ('type' => 'path', 'value' => ''),

		'IMAGE_MAX_WIDTH'      => array ('type' =>  'int', 'value' => 360),
		'NOTE_COLOR'           => array ('type' =>  'str', 'value' => '#000000'),
		'USE_IE6_PNG_ALPHA_FIX'=> array ('type' => 'bool', 'value' => true),
		'SHOW_SOURCE'          => array ('type' => 'bool', 'value' => false),
		'COMMENT_ENABLED'      => array ('type' => 'bool', 'value' => false),
		'ERROR_HANDLING'       => array ('type' => 'enum', 'value' => ON_ERR_SHOW_MESSAGE),

		'FRAGMENT_PER_COMMENT' => array ('type' =>  'int', 'value' => 1),

		'CONVERT_BIN'          => array ('type' => 'prog', 'value' => ''),
		'MUP_REG_KEY'          => array ('type' =>  'str', 'value' => ''),
	);

	do_action_ref_array ('scorerender_define_setting_type', array(&$default_settings));

	if (TYPES_ONLY == $return_type)
	{
		foreach ($default_settings as $key => $val)
			$retval += array ($key => $val['type']);
		return $retval;
	}

	$convert = '';

	if ( is_windows() )
	{
		$convert = search_path ('convert.exe');
		if ( !$convert && function_exists ('glob') )
		{
			$convert  = glob ('C:\Program Files\ImageMagick*\convert.exe');
			$convert = empty ($convert)  ? '' : $convert[0];
		}
	}
	else
	{
		if ( function_exists ('shell_exec') )
			$convert  = shell_exec ('which convert');
		else
			$convert = search_path ('convert');
	}

	$default_settings['CONVERT_BIN']['value'] = empty ($convert) ? '' : $convert;

	do_action_ref_array ('scorerender_define_setting_value', array(&$default_settings));

	$cachefolder = wp_upload_dir ();
	$default_settings['CACHE_DIR']['value'] = $cachefolder['basedir'];

	switch ($return_type)
	{
	  case VALUES_ONLY:
		foreach ($default_settings as $key => $val)
			$retval += array ($key => $val['value']);
		return $retval;
	  case TYPES_AND_VALUES:
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
 * Retrieve all default settings and merge them into ScoreRender options
 *
 * @uses scorerender_get_def_settings()
 * @uses $sr_options
 */
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
 * @uses scorerender_populate_options()
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
	if ($sr_options['DB_VERSION'] <= 15)
		if ( $sr_options['INVERT_IMAGE'] )
			$sr_options['NOTE_COLOR'] = '#FFFFFF';

	scorerender_populate_options ();

	transform_paths ($sr_options, TRUE);
	update_option ('scorerender_options', $sr_options);
	transform_paths ($sr_options, FALSE);

	return;
}


/**
 * Fetches folder location used for storing cached images
 *
 * If users manually set cache folder, then user setting is honored; otherwise
 * WordPress default upload directory will be used.
 *
 * @return string Cache folder location
 * @since 0.3.50
 */
function scorerender_get_cache_location ()
{
	global $sr_options;

	if ( !empty ($sr_options['CACHE_DIR']) )
		return $sr_options['CACHE_DIR'];
	else
	{
		$data = wp_upload_dir ();
		return $data['basedir'];
	}
}


/**
 * Generate HTML content from error message or rendered image
 *
 * @uses ScoreRender::render()
 * @uses ScoreRender::get_notation_name() Used when showing original content upon error
 * @uses ScoreRender::get_music_fragment() Used when showing original content upon error
 * @uses ScoreRender::get_command_output() Used when showing error message upon error, and debug is on
 * @uses ScoreRender::get_error_msg() Used when showing error message upon error, and debug is off
 * @uses scorerender_get_cache_location() For getting cached image location and read its size
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
			if ( false !== ( $name = $render->get_notation_name () ) )
				return $notations[$name]['starttag'] . "\n" .
					$render->get_music_fragment() . "\n" .
					$notations[$name]['endtag'];
			else
				return __('[Unknown notation type]') . "\n" .
					$render->get_music_fragment();

		  default:
			if (SR_DEBUG)
				return $render->get_command_output ();
			else
				return $render->get_error_msg ();
		}
	}

	// No errors, so generate HTML
	// Not nice to show source in alt text or title, since music source
	// is most likely multi-line, and some are quite long

	// This idea is taken from LatexRender demo site
	if ($sr_options['SHOW_SOURCE'])
	{
		$html = sprintf ("<form target='fragmentpopup' action='%s' method='post'>\n", plugins_url ('scorerender/misc/showcode.php'));
		$html .= sprintf ("<input type='image' name='music_image' class='scorerender-image' title='%s' alt='%s' src='%s' />\n",
			__('Click on image to view source', TEXTDOMAIN),
			__('Music fragment', TEXTDOMAIN),
			plugins_url ('scorerender/misc/tint-image.php') . '?img=' . $result );

		if ( false === ( $name = $render->get_notation_name() ) )
			// Shouldn't reach here
			return __('[Unknown notation type]');

		$content = $notations[$name]['starttag'] . "\r\n" .
			preg_replace ("/(?<!\r)\n/s", "\r\n", $render->get_music_fragment()) . "\r\n" .
			$notations[$name]['endtag'];

		$html .= sprintf ("<input type='hidden' name='code' value='%s'>\n</form>\n",
			rawurlencode (htmlentities ($content, ENT_NOQUOTES, get_bloginfo ('charset'))));
	}
	else
	{
		$dir = scorerender_get_cache_location();
		list ($width, $height, $type, $attr) = getimagesize( $dir.'/'.$result );
		$html .= sprintf ("<img class='scorerender-image' $attr title='%s' alt='%s' src='%s' />\n",
			__('Music fragment', TEXTDOMAIN),
			__('Music fragment', TEXTDOMAIN),
			plugins_url ('scorerender/misc/tint-image.php') . '?img=' . $result );
	}

	return $html;
}



/**
 * Initialize PHP class for corresponding music notation
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
 * @uses ScoreRender::set_temp_dir()
 * @uses ScoreRender::set_cache_dir()
 * @uses ScoreRender::set_img_width()
 * @uses ScoreRender::set_music_fragment()
 *
 * @param array $matches Matched music fragment in posts or comments. This variable must be supplied by {@link preg_match preg_match()} or {@link preg_match_all preg_match_all()}. Alternatively invoke this function with {@link preg_replace_callback preg_replace_callback()}.
 * @return string Either HTML content containing rendered image, or HTML error message in case rendering failed.
 */
function scorerender_init_class ($matches)
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
			foreach (array_keys($notation['progs']) as $setting_name) {
				$programs[$setting_name] = $sr_options[$setting_name];
			}
			$render->set_programs ($programs);

			break;
		}

	if (empty ($render)) return $input;

	$render->set_imagemagick_path ($sr_options['CONVERT_BIN']);
	$render->set_temp_dir         ($sr_options['TEMP_DIR']);
	$render->set_img_width        ($sr_options['IMAGE_MAX_WIDTH']);
	$render->set_cache_dir        (scorerender_get_cache_location());

	do_action ('sr_set_class_variable', $sr_options);

	$input = trim (html_entity_decode ($matches[1]));
	$render->set_music_fragment ($input);

	return scorerender_process_content ($render);
}


/**
 * The hook attached to WordPress plugin system.
 *
 * Check if post/comment rendering should be enabled.
 * If yes, then apply {@link scorerender_init_class} function on $content.
 *
 * @uses scorerender_init_class() Initialize class upon regular expression match
 * @param string $content The whole content of blog post / comment
 * @param boolean $is_post TRUE if content comes from post / page, FALSE otherwise
 * @return string Converted blog post / comment content.
 */
function scorerender_conversion_hook ($content, $is_post)
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
		foreach (array_keys($notation['progs']) as $setting_name)
			if (empty ($sr_options[$setting_name])) continue 2;
		$regex_list[] = $notation['regex'];
	};

	$limit = ($is_post)                                 ? -1 :
	         ($sr_options['FRAGMENT_PER_COMMENT'] <= 0) ? -1 :
	          $sr_options['FRAGMENT_PER_COMMENT'];

	return preg_replace_callback ($regex_list, 'scorerender_init_class', $content, $limit);
}

/**
 * Adds transparent PNG support if browser is IE6
 *
 * The filter used for transparent PNG image comes from Twinhelix.
 * This fix first adds CSS class to all images rendered by ScoreRender,
 * then use IE specific filter to add fake transparency to all images
 * with such CSS class.
 */
function scorerender_add_ie6_style()
{
?>
<!--[if lte IE 6]>
<style type="text/css">
.scorerender-image { behavior: url(<?php echo plugins_url ('/scorerender/misc/iepngfix.php') ?>); }
</style>
<![endif]-->
<?php
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

if (defined ('WP_ADMIN'))
	include_once ('scorerender-admin.php');
else
{
	// IE6 PNG translucency filter
	if ($sr_options['USE_IE6_PNG_ALPHA_FIX'])
		add_action ('wp_head', 'scorerender_add_ie6_style');

	// earlier than default priority, since
	// smilies conversion and wptexturize() can mess up the content
	add_filter ('the_excerpt' ,
		create_function ('$content',
			'return scorerender_conversion_hook ($content, TRUE);'),
		2);
	add_filter ('the_content' ,
		create_function ('$content',
			'return scorerender_conversion_hook ($content, TRUE);'),
		2);
	add_filter ('comment_text',
		create_function ('$content',
			'return scorerender_conversion_hook ($content, FALSE);'),
		2);
}

?>
