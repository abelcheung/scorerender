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
define ('DATABASE_VERSION', 19);

/**
 * Translation text domain
 */
define ('TEXTDOMAIN', 'scorerender');

/**
 * Regular expression for cached images
 */
define ('REGEX_CACHE_IMAGE', '/^sr-(\w+)-([0-9A-Fa-f]{32})\.png$/');

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

/**
 * Shortcode API replacement
 */
require_once('scorerender-shortcode.php');

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
 * @uses sys_get_temp_dir() For getting default temp directory
 * @uses search_path() For searching default programs in system PATH
 */
function scorerender_get_def_settings ($return_type = TYPES_AND_VALUES) /* {{{ */
{
	$retval = array();
	static $default_settings = array();

	if ( empty ($default_settings) )
	{
		$default_settings = array
		(
			'DB_VERSION'             => array ('type' => 'none', 'value' => DATABASE_VERSION),
			'TEMP_DIR'               => array ('type' => 'path', 'value' => ''),
			'CACHE_DIR'              => array ('type' => 'path', 'value' => ''),

			'IMAGE_MAX_WIDTH'        => array ('type' =>  'int', 'value' => 360),
			'NOTE_COLOR'             => array ('type' =>  'str', 'value' => '#000000'),
			'USE_IE6_PNG_ALPHA_FIX'  => array ('type' => 'bool', 'value' => true),

			'ENABLE_CLIPBOARD'       => array ('type' => 'bool', 'value' => false),
			'COMMENT_ENABLED'        => array ('type' => 'bool', 'value' => false),
			'ERROR_HANDLING'         => array ('type' => 'enum', 'value' => ON_ERR_SHOW_MESSAGE),
			'FRAGMENT_PER_COMMENT'   => array ('type' =>  'int', 'value' => 1),
			'PRODUCE_MIDI'           => array ('type' => 'bool', 'value' => false),

			'CONVERT_BIN'            => array ('type' => 'prog', 'value' => ''),
			'MUP_REG_KEY'            => array ('type' =>  'str', 'value' => ''),
		);

		do_action_ref_array ('scorerender_define_setting_type', array(&$default_settings));

		$convert = search_prog ('convert');
		$default_settings['CONVERT_BIN']['value'] = $convert ? $convert : '';

		do_action_ref_array ('scorerender_define_setting_value', array(&$default_settings));
	}

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

	  case TYPES_AND_VALUES:
		return $default_settings;
	}
} /* }}} */


/**
 * Initialize text domain.
 *
 * Translations are expected to be found in:
 *   - the same directory containing this plugin
 *   - default plugin translation path (root of plugin folder)
 *   - theme translation path (wp-content/languages or wp-includes/languages)
 * @since 0.2
 */
function scorerender_init_textdomain () /* {{{ */
{
	// load_textdomain() already does file existance checking
	load_plugin_textdomain (TEXTDOMAIN, PLUGINDIR.'/'.plugin_basename (dirname (__FILE__)));
	load_plugin_textdomain (TEXTDOMAIN);
	load_plugin_textdomain (TEXTDOMAIN, ABSPATH . LANGDIR);
} /* }}} */


/**
 * Retrieve all default settings and merge them into ScoreRender options
 *
 * @uses scorerender_get_def_settings()
 */
function scorerender_populate_options () /* {{{ */
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
} /* }}} */

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
function scorerender_get_options () /* {{{ */
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
	if ( $sr_options['DB_VERSION'] < 10 )
		if ( $sr_options['LILYPOND_COMMENT_ENABLED'] ||
		     $sr_options['MUP_COMMENT_ENABLED']      ||
		     $sr_options['ABC_COMMENT_ENABLED']      ||
		     $sr_options['GUIDO_COMMENT_ENABLED'] )
		{
			$sr_options['COMMENT_ENABLED'] = true;
		}
	if ( $sr_options['DB_VERSION'] < 16 )
		if ( $sr_options['INVERT_IMAGE'] ) $sr_options['NOTE_COLOR'] = '#FFFFFF';
	if ( $sr_options['DB_VERSION'] < 19 )
		$sr_options['ENABLE_CLIPBOARD'] = $sr_options['SHOW_SOURCE'];

	scorerender_populate_options ();

	transform_paths ($sr_options, TRUE);
	update_option ('scorerender_options', $sr_options);
	transform_paths ($sr_options, FALSE);
} /* }}} */


/**
 * Fetches folder location used for storing cached images
 *
 * If users manually set cache folder, then user setting is honored; otherwise
 * WordPress default upload directory will be used.
 *
 * @return string Cache folder location
 * @since 0.3.50
 */
function scorerender_get_cache_location () /* {{{ */
{
	global $sr_options;

	if ( !empty ($sr_options['CACHE_DIR']) )
		return $sr_options['CACHE_DIR'];
	else
	{
		$data = wp_upload_dir ();
		return $data['basedir'];
	}
} /* }}} */


/**
 * Generate HTML error message upon rendering failure
 *
 * @uses SrNotationBase::get_raw_input() Used when showing raw music code upon error
 * @uses SrNotationBase::get_command_output() Used when showing error message upon error, and debug is on
 *
 * @param object $render PHP object created for rendering relevant music fragment
 * @param string $lang
 * @param int $error_handling
 * @param WP_Error $wperror
 * @return string HTML content containing error message or empty string, depending on setting.
 */
function scorerender_return_fragment_error ( $render, $lang, $error_handling, $wperror ) /* {{{ */
{
	switch ( $error_handling )
	{
	  case ON_ERR_SHOW_NOTHING:
		return '';

	  case ON_ERR_SHOW_FRAGMENT:
		return "&#91;score lang='$lang'&#93;" .
			htmlentities ( $render->get_raw_input() ) .
			"&#91;/score&#93;";

	  default:
		if (SR_DEBUG)
			error_log ("ScoreRender: command error, output is:\n" . $render->get_command_output() );

		return "<div class='scorerender-error'><pre>" .
			htmlentities ( $render->format_error_msg (
				$wperror->get_error_message() ) ) . "</pre></div>";
	}
} /* }}} */


/**
 * Generate HTML content from rendered image
 *
 * @uses SrNotationBase::get_raw_input() For preparing the code content for clipboard copying
 * @uses scorerender_get_cache_location() For getting cached image path and reading its size
 *
 * @param object $render PHP object created for rendering relevant music fragment
 * @param array $attr Shortcode attributes
 * @return string HTML content containing image if successful, otherwise may display error message or empty string, depending on setting.
 */
function scorerender_return_fragment_ok ( $render, $attr, $result ) /* {{{ */
{
	global $sr_options;
	static $count = 0;
	$count++;

	extract ($attr);

	$args = array ( 'img' => $render->final_image );
	$args['color'] = preg_replace ( '/^#/', '',
	   !is_null ($color) ? $color : $sr_options['NOTE_COLOR']	);

	$imgurl = add_query_arg ( $args, plugins_url ('scorerender/misc/tint-image.php') );

	$content = "[score lang=\"{$lang}\"]\n" .
		preg_replace ( "/[\r\n]+/s", "\n", $render->get_raw_input() ) . "\n[/score]";

	$id = preg_replace ( REGEX_CACHE_IMAGE, 'sr-$2', $render->final_image);

	// Convert some more chars to avoid various problems
	//
	// linebreak conversion is for avoiding wpautop(), which continue
	// to insert breaks and <p>s everywhere (including inside HTML tag!)
	// brakcet conversion is there for avoiding re-evaluation of shortcode
	// in resulting HTML, more trustful than the purported [[shortcode]] syntax
	$repl_chars = array (
		'/\n/' => '&#10;',
		'/\[/' => '&#91;',
		'/\]/' => '&#93;',
	);

	list ( $width, $height, $type, $htmlattr ) =
		getimagesize ( scorerender_get_cache_location() .'/'. $render->final_image );

	$title = sprintf ( __('Music fragment in "%s" notation', TEXTDOMAIN), $lang );

	$turn_on_clipboard = ( !is_null ($clipboard) ) ? $clipboard : $sr_options['ENABLE_CLIPBOARD'];

	if ( $turn_on_clipboard )
	{
		// esc_js() does nothing but messing up line breaks
		$html = sprintf ("<input type='hidden' name='code' value='%s' id='%s-code'>",
				preg_replace ( array_keys ($repl_chars), array_values($repl_chars),
					htmlentities ( $content, ENT_QUOTES, get_option ('blog_charset') ) ),
				$id );

		// TODO: message is not aligned vertically, cf. http://www.jakpsatweb.cz/css/css-vertical-center-solution.html
		$html .= sprintf ("<div id='%s-div' style='position:relative; width:%spx; height:%spx; display:inline; overflow:hidden;'>",
				$id, $width, $height );

		$html .= sprintf ("<div id='%s-message' style='position:absolute; width:%spx; height:%spx; display:none; background:inherit; text-align:center;'>%s</div>",
				$id, ($width >= 300) ? $width : '300', $height,
				__('Music code copied to clipboard', TEXTDOMAIN) );

		// Note that all images with clipboard use 'scorerender-clip' class
		$html .= sprintf ("<img class='scorerender-image scorerender-clip' %s title='%s' alt='%s' src='%s' id='%s' />",
				$htmlattr, __('Click on image to copy music code to clipboard', TEXTDOMAIN),
				$title, $imgurl, $id );

		$html .= "</div>";
	}
	else
	{
		$html = sprintf ("<img class='scorerender-image' %s title='%s' alt='%s' src='%s' id='%s' />",
				$htmlattr, $title, $title, $imgurl, $id );
	}

	if ( $sr_options['PRODUCE_MIDI'] )
	{
		if ( $result )
		{
			if ( !is_null ($render->final_midi) )
			{
				$midiurl = add_query_arg ( array ('file' => $render->final_midi),
					plugins_url ('scorerender/misc/get-midi.php') );
				$mesg = "<a href='{$midiurl}'>(midi download)</a>";
			}
			else
				$mesg = "(no midi download)";
		}
		else
			$mesg = "(midi generation failed)";

		$html .= " <span class='scorerender-midi'>$mesg</span>";
	}

	return $html;
} /* }}} */


/**
 * Shortcode callback for all supported notations
 *
 * Create PHP object for each kind of supported notation, and set
 * all relevant parameters needed for rendering. Afterwards, render
 * the image and pass the result to other functions for displaying
 * error message or HTML containing image.
 *
 * @param array $attr Shortcode attributes
 * @param string $content Music source content
 * @param string $code Shortcode tag name
 *
 * @uses SrNotationBase::set_img_progs() Setting default notation rendering program
 * @uses SrNotationBase::set_midi_progs() Setting default MIDI generation program
 * @uses SrNotationBase::set_imagemagick_path() Setting ImageMagick `convert` path
 * @uses SrNotationBase::set_temp_dir() Setting default temp directory
 * @uses SrNotationBase::set_cache_dir() Setting cache directory used for storing images
 * @uses SrNotationBase::set_img_width()
 * @uses SrNotationBase::set_music_fragment()
 * @uses SrNotationBase::format_error_msg()
 * @uses SrNotationBase::render()
 * @uses scorerender_get_cache_location()
 * @uses scorerender_return_fragment_ok() Handles the case when image rendering is successful
 * @uses scorerender_return_fragment_error() Handles the case when image rendering failed
 * @uses extension_loaded() For checking existance of GD extension
 *
 * @return string Either HTML content containing rendered image, or HTML error message on failure
 */
function scorerender_shortcode_handler ($attr, $content = null, $code = "") /* {{{ */
{
	global $notations, $sr_options;

	$defaults = array (
		'color'     => null,
		'lang'      => null,
		'clipboard' => null,
	);

	// transform shortcode attributes to boolean values whenever appropriate
	if ( !empty ($attr) )
		foreach ( $attr as $key => $value )
		{
			if     ( 'false' === $attr[$key] ) $attr[$key] = false;
			elseif ( 'true'  === $attr[$key] ) $attr[$key] = true;
		}

	// prevents construct like [mup lang="abc"]
	if ( ( 'score' != $code ) && ( 'scorerender' != $code ) )
		$attr['lang'] = $code;

	$attr = shortcode_atts ( $defaults, $attr );
	extract ($attr);

	if ( ! array_key_exists ( $lang, $notations ) )
		return SrNotationBase::format_error_msg ( sprintf (
			__('unknown notation language "%s"', TEXTDOMAIN), $lang ) );

	// initialize notation class
	if ( class_exists ( $notations[$lang]['classname'] ) )
		$render = new $notations[$lang]['classname'];

	// in case something is very wrong... (notation data incorrect, instance creation failure)
	if ( empty ($render) )
		return SrNotationBase::format_error_msg ( __('class initialization failure', TEXTDOMAIN) );

	$render_progs = array();
	$midi_progs   = array();
	foreach ( $notations[$lang]['progs'] as $setting_name => $progdata )
	{
		switch ( $progdata['type'] )
		{
		  case 'prog':
			$render_progs[$setting_name] = $sr_options[$setting_name];
			break;
		  case 'midiprog':
			$midi_progs[$setting_name]   = $sr_options[$setting_name];
			break;
		}
	}
	$render->set_img_progs        ($render_progs);
	$render->set_midi_progs       ($midi_progs);
	$render->set_imagemagick_path ($sr_options['CONVERT_BIN']);
	$render->set_temp_dir         ($sr_options['TEMP_DIR']);
	$render->set_img_width        ($sr_options['IMAGE_MAX_WIDTH']);
	$render->set_cache_dir        (scorerender_get_cache_location());

	do_action ('sr_set_class_variable', $sr_options);

	$render->set_music_fragment ( trim ( html_entity_decode ($content) ) );

	$result = $render->render();

	if ( is_wp_error ($result) )
		return scorerender_return_fragment_error ( $render, $lang, $sr_options['ERROR_HANDLING'], $result );

	if ( !extension_loaded ('gd') )
		return SrNotationBase::format_error_msg ( __('PHP GD extension is not installed or enabled on this host', TEXTDOMAIN) );

	// TODO: if $result is false, have to check for midi generation problem etc
	return scorerender_return_fragment_ok ( $render, $attr, $result );
} /* }}} */


/**
 * Used as a shortcode callback when certain notation is not supported
 * on the blog, usually by unsetting corresponding program in admin page
 *
 * @uses SrNotationBase::format_error_msg()
 * @param array $attr Array of shortcode attributes, only language name is used here
 * @param string $content The content enclosed in shortcode, unused in this func
 * @param string $code Shortcode tag used
 * @return string Error message mentioning unsupported notation
 */
function scorerender_shortcode_unsupported ($attr, $content = null, $code = "") /* {{{ */
{
	if ( ( 'score' != $code ) && ( 'scorerender' != $code ) )
		$attr['lang'] = $code;

	return SrNotationBase::format_error_msg (
			sprintf (__("'%s' notation is not supported on this blog", TEXTDOMAIN),
			$attr['lang']) );
} /* }}} */


/**
 * The main hook attached to WordPress plugin system, converting all shortcodes
 * and contents enclosed into score image.
 *
 * First it determines whether rendering should be enabled through author roles
 * and blog settings. If everything is fine, register all shortcodes usable
 * and apply them on the content.
 *
 * @uses scorerender_shortcode_unsupported() Use this callback if notation
 * is not supported
 * @uses scorerender_do_shortcode() Use this version of shortcode filtering
 * instead of WP native do_shortcode() if on WP < 2.8 or applying on comment
 *
 * @param string $content The whole content of blog post / comment
 * @param string $content_type Either 'post' or 'comment'
 * @param callable $callback Callback function used for $content_type
 * @return string Converted blog post / comment content
 */
function scorerender_parse_shortcode ($content, $content_type, $callback) /* {{{ */
{
	global $sr_options, $post, $notations, $shortcode_tags, $wp_version;

	// only handles page, post and comment for now
	if ( !in_array ( $content_type, array ( 'post', 'comment' ) ) ) return $content;

	if ( ( 'comment' == $content_type ) && !$sr_options['COMMENT_ENABLED']) return $content;

	if ( 'post' == $content_type )
	{
		$author = new WP_User ($post->post_author);
		if (!$author->has_cap ('unfiltered_html')) return $content;
	}

	$orig_shortcodes = $shortcode_tags;
	remove_all_shortcodes();

	add_shortcode ('scorerender', $callback);
	add_shortcode ('score', $callback);
	foreach ( $notations as $notation_name => $notation_data )
	{
		foreach ( $notation_data['progs'] as $setting_name => $progdata )
		{
			// ignore progs not used for rendering
			if ( 'prog' !== $progdata['type'] ) continue;

			// unfilled program name = disable support, thus use stub handler
			if (empty ($sr_options[$setting_name])) {
				add_shortcode ($notation_name, 'scorerender_shortcode_unsupported');
				continue 2;
			}
		}
		add_shortcode ($notation_name, $callback);
	}

	$limit = ($sr_options['FRAGMENT_PER_COMMENT'] <= 0) ? -1 : (int)$sr_options['FRAGMENT_PER_COMMENT'];

	// shortcode API in WP < 2.8 is not good enough
	if ( 'comment' == $content_type )
		$content = scorerender_do_shortcode ($content, $limit);
	elseif ( version_compare ( $wp_version, '2.8', '<' ) )
		$content = scorerender_do_shortcode ($content);
	else
		$content = do_shortcode ($content);

	$shortcode_tags = $orig_shortcodes;

	return $content;
} /* }}} */


/**
 * Adds transparent PNG support if browser is IE6
 *
 * The filter used for transparent PNG image comes from Twinhelix.
 * This fix first adds CSS class to all images rendered by ScoreRender,
 * then use IE specific filter to add fake transparency to all images
 * with such CSS class.
 */
function scorerender_add_ie6_style() /* {{{ */
{
?>
<!-- begin scorerender style -->
<!--[if lte IE 6]>
<style type="text/css">
.scorerender-image { behavior: url(<?php echo plugins_url ('scorerender/misc/iepngfix.php') ?>); }
</style>
<![endif]-->
<!-- end scorerender style -->
<?php
} /* }}} */

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

// Register all notations and their initial data
do_action_ref_array ( 'scorerender_register_notations', array(&$notations) );

// retrieve plugin options first
scorerender_get_options ();

// initialize translation files
add_action ('init', 'scorerender_init_textdomain');

if (defined ('WP_ADMIN'))
	include_once ('scorerender-admin.php');
else
{
	global $wp_version;

	// IE6 PNG translucency filter
	if ($sr_options['USE_IE6_PNG_ALPHA_FIX'])
		add_action ('wp_head', 'scorerender_add_ie6_style');

	if ( version_compare ( $wp_version, '2.8', '>=' ) )
	{
		wp_enqueue_script ( 'zeroclipboard'  , plugins_url ( 'scorerender/misc/ZeroClipboard.js'   ),
				array(), '1.0.6', true );
		wp_enqueue_script ( 'jquery-copyable', plugins_url ( 'scorerender/misc/jquery.copyable.js' ),
				array('jquery', 'zeroclipboard'), '0.0', true );
	}
	else
	{
		wp_enqueue_script ( 'zeroclipboard'  , plugins_url ( 'scorerender/misc/ZeroClipboard.js'   ),
				array(), '1.0.6' );
		wp_enqueue_script ( 'jquery-copyable', plugins_url ( 'scorerender/misc/jquery.copyable.js' ),
				array('jquery', 'zeroclipboard'), '0.0' );
	}

	// Set ZeroClipboard path, as well as fading effect for all images
	add_action ('wp_footer', create_function ( '$a',
			'echo "<script type=\'text/javascript\'>
ZeroClipboard.setMoviePath( \'' . plugins_url ( 'scorerender/misc/ZeroClipboard.swf' ) . '\' );
jQuery(\'.scorerender-clip\').copyable(function(e, clip) {
	clip.setText(jQuery(\'#\'+this.id+\'-code\').val());
	var messageid = this.id + \'-message\';
	jQuery(this).fadeOut(\'slow\');
	jQuery(\'#\' + messageid).fadeIn(\'slow\');
	jQuery(this).fadeIn(2000);
	jQuery(\'#\' + messageid).fadeOut(2000);
});
</script>
";'
			)
	);
	// earlier than default priority, since
	// smilies conversion and wptexturize() can mess up the content
	add_filter ('the_excerpt',
		create_function ('$content',
			'return scorerender_parse_shortcode ($content, "post", "scorerender_shortcode_handler");'),
		2);
	add_filter ('the_content',
		create_function ('$content',
			'return scorerender_parse_shortcode ($content, "post", "scorerender_shortcode_handler");'),
		2);
	add_filter ('comment_text',
		create_function ('$content',
			'return scorerender_parse_shortcode ($content, "comment", "scorerender_shortcode_handler");'),
		2);
}

/* vim: set cindent foldmethod=marker : */
?>
