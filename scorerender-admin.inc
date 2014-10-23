<?php

/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung
 * @copyright Copyright (C) 2006 Chris Lamb
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
 */


/**
 * This class is used for encapsulating various admin related functions
 *
 * @since 0.3.50
 * @package ScoreRender
 * @access private
*/
class ScoreRenderAdmin
{

const MSG_WARNING = 1;
const MSG_FATAL   = 2;

/**
 * Returns number of cached files inside cache directory
 *
 * @since 0.2
 * @uses scorerender_get_cache_location() For reading cache folder and counting images
 * @return WP_Error|array Associative array containing number of images/midi
 * inside cache folder, or WP_Error if directory is unreadable
 * @access private
 */
private function get_cache_stat () /* {{{ */
{
	if ( false === ( $handle = @opendir (scorerender_get_cache_location()) ) )
		return new WP_Error ( 'sr_opendir_fail', __('Fail to open cache directory', SR_TEXTDOMAIN) );

	$imgcount = $midicount = 0;
	while ( false !== ( $file = @readdir ($handle) ) )
	{
		if     ( preg_match (SrNotationBase::REGEX_CACHE_IMAGE, $file) ) $imgcount++;
		elseif ( preg_match (SrNotationBase::REGEX_CACHE_MIDI,  $file) ) $midicount++;
	}

	closedir ($handle);
	return array ('img' => $imgcount, 'midi' => $midicount);
} /* }}} */


/**
 * Remove all cached images in cache directory
 *
 * @since 0.2
 * @uses scorerender_get_cache_location() For searching cached image folder and deleting images
 * @return WP_Error|bool WP_Error on failure, true on success
 * @access private
 */
private function remove_cache () /* {{{ */
{
	$dir = scorerender_get_cache_location();
	if ( false === ( $handle = @opendir ( $dir ) ) )
		return new WP_Error ( 'sr_opendir_fail', __('Fail to open cache directory', SR_TEXTDOMAIN) );

	$cwd = getcwd();
	if ( false === @chdir ($dir) )
		return new WP_Error ( 'sr_chdir_fail', __('Fail to change into cache directory', SR_TEXTDOMAIN) );

	while (false !== ($file = @readdir ($handle)))
	{
		if ( preg_match (SrNotationBase::REGEX_CACHE_IMAGE, $file) ||
		     preg_match (SrNotationBase::REGEX_CACHE_MIDI , $file) )
			@unlink ($file);
	}
	closedir ($handle);
	chdir ($cwd);

	return true;
} /* }}} */


/**
 * Update ScoreRender options in database with submitted options.
 *
 * A warning banner will be shown on top of admin page for each
 * error encountered in various options. In some cases supplied
 * config values will be discarded.
 *
 * @uses transform_paths()
 * @uses SrNotationBase::is_web_hosting()
 * @uses SrNotationBase::is_prog_usable() Check if ImageMagick is usable
 * @uses scorerender_get_def_settings()
 * @uses scorerender_get_cache_location() Also checks if cached image folder is writable
 * @access private
 */
private function update_options () /* {{{ */
{
	if ( !current_user_can ('manage_options') )
		wp_die (__('Cheatin&#8217; uh?', SR_TEXTDOMAIN));

	$newopt = (array) $_POST['ScoreRender'];
	transform_paths ($newopt, TRUE);
	$errmsgs = array ();

	$sr_adm_msgs = array
	(
		'temp_dir_not_writable'  => array (
			'level'   => self::MSG_WARNING,
			'content' => __('Temporary directory is NOT writable! Will fall back to system default setting.', SR_TEXTDOMAIN)),
		'cache_dir_not_writable' => array (
			'level'   => self::MSG_FATAL  ,
			'content' => sprintf (__('Cache directory is NOT writable! If default value is used, please go to <a href="%s">WordPress file upload setting</a> and check default upload directory; otherwise please make sure the cache directory you specified can be accessed by web server. The plugin will stop working.', SR_TEXTDOMAIN), admin_url('options-misc.php'))),
		'wrong_frag_per_comment' => array (
			'level'   => self::MSG_WARNING,
			'content' => __('Fragment per comment is not a non-negative integer. Value discarded.', SR_TEXTDOMAIN)),
		'wrong_image_max_width'  => array (
			'level'   => self::MSG_WARNING,
			'content' => __('Image maximum width must be integer and at least 100. Value discarded.', SR_TEXTDOMAIN)),
		'convert_bin_problem'    => array (
			'level'   => self::MSG_FATAL  ,
			'content' => __('Failed to detect usable ImageMagick <tt>convert</tt> program! The plugin will stop working.', SR_TEXTDOMAIN)),
		'prog_check_disabled'    => array (
			'level'   => self::MSG_WARNING,
			'content' => __('Some PHP functions are disabled due to security reasons. Program validation will not be done.', SR_TEXTDOMAIN)),
	);

	// error message definition for each notation
	do_action_ref_array ('scorerender_define_adm_msgs', array(&$sr_adm_msgs));

	/*
	 * general options
	 */
	if ( !empty ($newopt['TEMP_DIR']) &&
		!is_writable ($newopt['TEMP_DIR']) )
	{
		$errmsgs[] = 'temp_dir_not_writable';
		$newopt['TEMP_DIR'] = '';
	}

	if ( !empty ($newopt['CACHE_DIR']) )
	{
		if ( !is_writable ($newopt['CACHE_DIR']) )
			$errmsgs[] = 'cache_dir_not_writable';
	}
	else
	{
		$newopt['CACHE_DIR'] = '';
		if ( !is_writable ( scorerender_get_cache_location() ) )
			$errmsgs[] = 'cache_dir_not_writable';
	}

	if ( SrNotationBase::is_web_hosting() )
		$errmsgs[] = 'prog_check_disabled';

	$result = SrNotationBase::is_prog_usable (
		SrNotationBase::$imagick_check['test_output'],
		$newopt['CONVERT_BIN'],
		SrNotationBase::$imagick_check['test_arg'],
		SrNotationBase::$imagick_check['min_version']);

	if ( is_wp_error ($result) ) {
		$errmsgs[] = 'convert_bin_problem';
	}

	// Any boolean values set to false would not appear in $_POST
	$var_types = scorerender_get_def_settings (SrNotationBase::TYPES_ONLY);
	foreach ($var_types as $key => $type)
		if ($type == 'bool')
			$newopt[$key] = isset ($newopt[$key]);

	if ( isset ($newopt['FRAGMENT_PER_COMMENT']) &&
		!ctype_digit ($newopt['FRAGMENT_PER_COMMENT']) )
	{
		$errmsgs[] = 'wrong_frag_per_comment';
		unset ($newopt['FRAGMENT_PER_COMMENT']);
	}

	if ( !ctype_digit ($newopt['IMAGE_MAX_WIDTH']) || ($newopt['IMAGE_MAX_WIDTH'] < 100) )
	{
		$errmsgs[] = 'wrong_image_max_width';
		unset ($newopt['IMAGE_MAX_WIDTH']);
	}

	// program checking for each notation
	do_action_ref_array ('scorerender_check_notation_progs', array(&$errmsgs, &$newopt));

	SrNotationBase::$sr_opt = array_merge (SrNotationBase::$sr_opt, $newopt);
	transform_paths (SrNotationBase::$sr_opt, TRUE);
	update_option ('scorerender_options', SrNotationBase::$sr_opt);
	transform_paths (SrNotationBase::$sr_opt, FALSE);

	if ( !empty ($errmsgs) )
	{
		foreach (array_values ($errmsgs) as $m)
		{
			if ($sr_adm_msgs[$m]['level'] == self::MSG_WARNING)
			{
				$class = 'scorerender-warning';
				$mesg = __('WARNING: %s', SR_TEXTDOMAIN);
			}
			elseif ($sr_adm_msgs[$m]['level'] == self::MSG_FATAL)
			{
				$class = 'scorerender-error';
				$mesg = __('ERROR: %s', SR_TEXTDOMAIN);
			}

			if ( is_wp_error ($result) )
			{
				printf ("<div id='%s' class='error %s'><p><strong>%s</strong></p><p>%s</p></div>\n",
					'sr-err-' . $sr_adm_msgs[$m],
					$class,
					sprintf ($mesg, $sr_adm_msgs[$m]['content']),
					$result->get_error_message()
				);
			}
			else
				printf ("<div id='%s' class='error %s'><p><strong>%s</strong></p></div>\n",
					'sr-err-' . $sr_adm_msgs[$m],
					$class,
					sprintf ($mesg, $sr_adm_msgs[$m]['content'])
				);
		}
	}
	else
		echo '<div id="message" class="updated fade"><p><strong>' .
			__('Options saved.', SR_TEXTDOMAIN) . "</strong></p></div>\n";
} /* }}} */


/**
 * WP hook added to admin page header
 *
 * @since 0.3
 */
public function admin_head()
{
?>
<style type="text/css">
	.sr-help-icon {vertical-align:middle;border:none;}
	span.stressed {font-weight:bold;}
</style>
<?php
}


/**
 * Handle different ways of printing setting description in
 * various WP versions.
 *
 * @param string $mesg Original message
 * @param array $extraclass Array of CSS style class names to be added
 * @return string Message enclosed in span tags with appropriate CSS style
 * @since 0.3.50
 */
public static function print_description ($mesg, $extraclass = null)
{
	global $wp_version;

	foreach ( (array)$extraclass as $key => $value )
		if ( is_string ($value) ) $class[] = $value;

	if ( version_compare ( $wp_version, '2.7', '>=' ) )
		$class[] = version_compare ( $wp_version, '2.8', '<' ) ? 'setting-description' : 'description';

	if ( empty ($class) )
		return $mesg;
	else
		return '<span class="' . implode (' ', $class) . '">' . $mesg . "</span>\n";
}


/**
 * WP hook added to admin page footer
 *
 * Mainly include javascripts involved in admin form
 *
 * @since 0.3.50
 */
public function admin_footer() /* {{{ */
{
?>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function($){
	$("#comment_enabled").click(function() {
		if ( $(this).is(":checked") )
		{
			$("#fragment_per_comment").removeAttr("disabled");
			$("#fragment_per_comment").removeClass("disabled");
		}
		else
		{
			$("#fragment_per_comment").attr('disabled', true);
			$("#fragment_per_comment").addClass("disabled");
		}
	});
});
//]]>
</script>
<script type="text/javascript" src="<?php echo plugins_url ('scorerender/misc/jscolor/jscolor.js') ?>"></script>
<?php
} /* }}} */

/**
 * Section of admin page about path options
 *
 * @since 0.2
 * @access private
 */
private function admin_section_path () /* {{{ */
{
?>

<h3><?php _e('Path options', SR_TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><label for="temp_dir"><?php _e('Temporary folder:', SR_TEXTDOMAIN) ?></label></th>
<td>
<input name="ScoreRender[TEMP_DIR]" type="text" id="temp_dir" value="<?php echo SrNotationBase::$sr_opt['TEMP_DIR']; ?>" class="regular-text code" />
<?php echo self::print_description ( __('All rendering will be performed inside this folder before copying to cache folder. System default will be used if left blank.', SR_TEXTDOMAIN) ) ?>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="cache_dir"><?php _e('Cache folder:', SR_TEXTDOMAIN) ?></label></th>
<td>
<input name="ScoreRender[CACHE_DIR]" type="text" id="cache_dir" value="<?php echo SrNotationBase::$sr_opt['CACHE_DIR']; ?>" class="regular-text code" />
<?php echo self::print_description ( __('Stores rendered image and MIDI for faster access. WordPress default upload directory will be used if left blank. Need NOT be web accessible.', SR_TEXTDOMAIN) ) ?>
</td>
</tr>

</table>
<?php
} /* }}} */


/**
 * Section of admin page about program and file locations
 *
 * @since 0.2
 * @access private
 */
private function admin_section_prog () /* {{{ */
{
?>

<h3><?php _e('Program and file locations', SR_TEXTDOMAIN) ?></h3>

<p><?php printf ( __("The only <strong>MANDATORY</strong> requirements are GhostScript and ImageMagick &ge; %s (specifically, the <code>convert</code> program). For each kind of notation, leaving corresponding program location empty means disabling that notation support automatically, except GUIDO which does not use any program (therefore can't be disabled).", SR_TEXTDOMAIN), SrNotationBase::$imagick_check['min_version'] ) ?></p>

<table class="form-table">

<tr valign="top">
<th scope="row"><label for="convert_bin"><?php printf (__('Location of %s binary:', SR_TEXTDOMAIN), '<code>convert</code>') ?></label></th>
<td><input name="ScoreRender[CONVERT_BIN]" type="text" id="convert_bin" value="<?php echo SrNotationBase::$sr_opt['CONVERT_BIN']; ?>" class="regular-text code" /></td>
</tr>

<?php
	$output = apply_filters ('scorerender_prog_and_file_loc', isset($output)? $output : '');
	echo $output;
?>

</table>
<?php
} /* }}} */

/**
 * Section of admin page about image options
 *
 * @since 0.2
 * @access private
 */
private function admin_section_image () /* {{{ */
{
?>
<h3><?php _e('Image options', SR_TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><?php _e('Max image width:', SR_TEXTDOMAIN) ?></th>
<td><?php printf(__('%s pixels', SR_TEXTDOMAIN), '<input type="text" name="ScoreRender[IMAGE_MAX_WIDTH]" id="image_max_width" value="' . SrNotationBase::$sr_opt['IMAGE_MAX_WIDTH'] . '" class="small-text" />'); ?><br />
<?php echo self::print_description ( __('Note that this value is just an approximation, please allow for &#x00B1;10% difference. Some programs like lilypond would not use the full image width if passage is not long enough.', SR_TEXTDOMAIN) ) ?>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Note color:', SR_TEXTDOMAIN) ?></th>
<td>
<label for="note_color">
<input type="text" id="note_color" name="ScoreRender[NOTE_COLOR]" value="<?php echo SrNotationBase::$sr_opt['NOTE_COLOR'] ?>" class="small-text color {hash:true}" style="width:6em" />
<em><?php _e('(Click to select color)', SR_TEXTDOMAIN) ?></em>
</label><br />
<?php echo self::print_description ( __('This feature can be toggled in each music fragment. Please refer to help for detail.') ) ?>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('IE Hack:', SR_TEXTDOMAIN) ?></th>
<td>
<label for="use_ie6_png_alpha_fix"><input type="checkbox" name="ScoreRender[USE_IE6_PNG_ALPHA_FIX]" id="use_ie6_png_alpha_fix" value="1" <?php checked('1', SrNotationBase::$sr_opt['USE_IE6_PNG_ALPHA_FIX']); ?> />
<?php _e('Enable fake translucent image in IE6', SR_TEXTDOMAIN) ?></label><br />
<?php echo self::print_description ( sprintf ( __('Enables <a href="%s">emulation of translucency in PNG images</a> in IE 5.5/6.x . This option only affects images rendered by ScoreRender, and only when user is using IE5/6 browser.', SR_TEXTDOMAIN), 'http://www.twinhelix.com/css/iepngfix/' ) ) ?>
<?php echo self::print_description ( __(' If any WordPress plugin with such functionality is used on the same site, please turn this option OFF.', SR_TEXTDOMAIN), array('stressed') ) ?>
</td>
</tr>

</table>
<?php
} /* }}} */



/**
 * Section of admin page about content options
 *
 * @since 0.2
 * @access private
 */
private function admin_section_content () /* {{{ */
{
?>
<h3><?php _e('Content options', SR_TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><?php _e('Enable clipboard:', SR_TEXTDOMAIN) ?></th>
<td><label for="show_input"><input type="checkbox" name="ScoreRender[ENABLE_CLIPBOARD]" id="show_input" value="1" <?php checked('1', SrNotationBase::$sr_opt['ENABLE_CLIPBOARD']); ?> />
<?php _e('Copy music source content to clipboard when image is clicked', SR_TEXTDOMAIN); ?></label><br />
<?php echo self::print_description ( __('This feature can be toggled in each music fragment. Please refer to help for detail.', SR_TEXTDOMAIN) ) ?>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('MIDI generation:', SR_TEXTDOMAIN) ?></th>
<td><label for="produce_midi"><input type="checkbox" name="ScoreRender[PRODUCE_MIDI]" id="produce_midi" value="1" <?php checked('1', SrNotationBase::$sr_opt['PRODUCE_MIDI']); ?> />
<?php _e('Also attempt to generate MIDI and provide download link when generating image', SR_TEXTDOMAIN); ?></label><br />
<?php echo self::print_description ( __('Note that different notations have different ways to generate MIDI. For example, ABC notation requires <code>abc2midi</code> program, while Lilypond, PMW and Mup have built-in support yet triggered in different ways.') ) ?>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('When rendering failed:', SR_TEXTDOMAIN); ?></th>
<td>
<fieldset><legend class="hidden screen-reader-text"><?php _e('When rendering failed:', SR_TEXTDOMAIN); ?></legend>
<label for="on_err_show_message"><input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_message" value="1" <?php checked(SrNotationBase::ON_ERR_SHOW_MESSAGE, SrNotationBase::$sr_opt['ERROR_HANDLING']); ?> />
<?php _e('Show error message', SR_TEXTDOMAIN) ?></label><br />
<label for="on_err_show_fragment"><input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_fragment" value="2" <?php checked(SrNotationBase::ON_ERR_SHOW_FRAGMENT, SrNotationBase::$sr_opt['ERROR_HANDLING']); ?> />
<?php _e('Show original, unmodified music fragment', SR_TEXTDOMAIN) ?></label><br />
<label for="on_err_show_nothing"><input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_nothing" value="3" <?php checked(SrNotationBase::ON_ERR_SHOW_NOTHING, SrNotationBase::$sr_opt['ERROR_HANDLING']); ?> />
<?php _e('Show nothing', SR_TEXTDOMAIN) ?></label>
</fieldset>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Comment rendering:', SR_TEXTDOMAIN) ?></th>
<td>
<label for="comment_enabled"><input type="checkbox" name="ScoreRender[COMMENT_ENABLED]" id="comment_enabled" value="1" <?php checked('1', SrNotationBase::$sr_opt['COMMENT_ENABLED']); ?> />
<?php _e('Enable rendering for comments', SR_TEXTDOMAIN) ?></label><br />
<?php echo self::print_description ( __('Only turn on if commenters are trusted', SR_TEXTDOMAIN), array('stressed') ) ?>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Maximum number of fragment per comment:', SR_TEXTDOMAIN) ?></th>
<td>
<label for="fragment_per_comment"><input type="text" name="ScoreRender[FRAGMENT_PER_COMMENT]" id="fragment_per_comment" value="<?php echo SrNotationBase::$sr_opt['FRAGMENT_PER_COMMENT']; ?>" <?php echo (1 != SrNotationBase::$sr_opt['COMMENT_ENABLED']) ? 'class="small-text disabled" disabled="disabled"' : 'class="small-text"'; ?> /></label>
<?php _e('(0 means unlimited)', SR_TEXTDOMAIN) ?><br /><?php echo self::print_description ( sprintf (__('If you don&#8217;t want comment rendering, turn off &#8216;<i>%s</i>&#8217; checkbox above instead. This option does not affect posts and pages.', SR_TEXTDOMAIN), __('Enable rendering for comments', SR_TEXTDOMAIN) ) ) ?>
</td>
</tr>

</table>
<?php
} /* }}} */



/**
 * Section of admin page about caching options
 *
 * @since 0.2
 * @uses ScoreRenderAdmin::get_cache_stat() Get and show cached image count
 * @uses scorerender_get_cache_location() Check if the cached image folder is read-writable
 * @access private
 */
private function admin_section_caching () /* {{{ */
{
?>
	<h3><?php _e('Caching', SR_TEXTDOMAIN) ?></h3>
<?php
	$result = $this->get_cache_stat();

	if ( is_wp_error ( $result ) )
		echo "<font color='red'>" . $result->get_error_message() . "</font><br />";
	else
	{
		global $wp_version;
		if( version_compare( $wp_version, '2.8', '>=' ) ) {
			$imgpart  = sprintf ( _n ( __("%d image" , SR_TEXTDOMAIN),
			                           __("%d images", SR_TEXTDOMAIN), $result['img'] ),
			                      $result['img'] );
			$midipart = sprintf ( _n ( __("%d midi file" , SR_TEXTDOMAIN),
			                           __("%d midi files", SR_TEXTDOMAIN), $result['midi'] ),
			                      $result['midi'] );
		} else {
			$imgpart  = sprintf ( __ngettext ( __("%d image" , SR_TEXTDOMAIN),
			                                   __("%d images", SR_TEXTDOMAIN), $result['img'] ),
			                      $result['img'] );
			$midipart = sprintf ( __ngettext ( __("%d midi file" , SR_TEXTDOMAIN),
			                                   __("%d midi files", SR_TEXTDOMAIN), $result['midi'] ),
			                                   $result['midi'] );
		}

		if     ( ( 0 === $result['midi'] ) && ( 0 === $result['img'] ) )
			_e("Cache directory is empty.\n", SR_TEXTDOMAIN);
		elseif     ( 0 === $result['midi'] )
			printf ( __("Cache directory contains %s.\n", SR_TEXTDOMAIN), $imgpart );
		elseif ( 0 === $result['img'] )
			printf ( __("Cache directory contains %s.\n", SR_TEXTDOMAIN), $midipart );
		else
			printf ( __("Cache directory contains %s and %s.\n", SR_TEXTDOMAIN), $imgpart, $midipart );
	}

	$dir = scorerender_get_cache_location();
	if ( is_writable ($dir) && is_readable ($dir) ) :
?>
	<input type="submit" name="clear_cache" class="button-secondary" value="<?php _e('Clear Cache &raquo;', SR_TEXTDOMAIN) ?>" />
<?php else : ?>
	<input type="submit" name="clear_cache" class="button-secondary" disabled="disabled" value="<?php _e('Clear Cache &raquo;', SR_TEXTDOMAIN) ?>" />
	<br /><font color="red"><?php printf (__('Cache folder %s can&#8217;t be cleared because folder permission is incorrect. It must be both readable and writable by web server.', SR_TEXTDOMAIN), $dir) ?></font>
<?php endif;
} /* }}} */


/**
 * Show WordPress admin page
 *
 * It also checks if form button is pressed, and may call
 * {@link ScoreRenderAdmin::remove_cache()} or
 * {@link ScoreRenderAdmin::update_options()} correspondingly.
 *
 * @uses ScoreRenderAdmin::remove_cache() Activated when 'Remove Cache' button is clicked
 * @uses ScoreRenderAdmin::update_options() Activate when 'Update Options' button is clicked
 * @uses ScoreRenderAdmin::admin_section_path() Admin page -- path options
 * @uses ScoreRenderAdmin::admin_section_prog() Admin page -- program and file locations
 * @uses ScoreRenderAdmin::admin_section_image() Admin page -- image options
 * @uses ScoreRenderAdmin::admin_section_content() Admin page -- content options
 * @uses ScoreRenderAdmin::admin_section_caching() Admin page -- caching administration
 */
public function admin_page () /* {{{ */
{
	if ( SR_DEBUG && !empty ($_POST) )
	{
		echo '<div id="sr-admin-form-data" class="updated"><p><pre>$_POST = ' .
			var_export ($_POST, true) .
			"</pre></p></div>\n";
	}

	if ( isset($_POST['clear_cache']) && isset($_POST['ScoreRender']) )
	{
		check_admin_referer ('scorerender-update-options');
		$retval = $this->remove_cache();
		if ( is_wp_error ( $retval ) )
		{
			printf ( "<div id='%s' class='error scorerender-error'><p><strong>" .
				__('ERROR: Cache removal failed, error message is: &#8216;%s&#8217;', SR_TEXTDOMAIN) .
				"</strong></p></div>\n",
				$retval->get_error_code(), $retval->get_error_message() );
		}
	}

	if ( isset($_POST['Submit']) && isset($_POST['ScoreRender']) )
	{
		check_admin_referer ('scorerender-update-options');
		$this->update_options();
	}
?>
<div class="wrap">
	<?php if ( function_exists ('screen_icon') ) screen_icon(); ?>
	<h2><?php _e('ScoreRender options', SR_TEXTDOMAIN) ?> <a href="javascript:" title="<?php _e('Click to show help') ?>" onclick="jQuery('#sr-help-1').slideToggle('fast');"><img src="<?php echo plugins_url ('scorerender/misc/info-icon.png'); ?>" width="32" height="32" class="sr-help-icon" /></a></h2>

	<form method="post" action="" id="scorerender-conf">
	<?php wp_nonce_field ('scorerender-update-options') ?>

	<div id="sr-help-1" class="hidden">
		<p><?php _e('Each music fragment must be enclosed by a pair of shortcodes, in the following format:', SR_TEXTDOMAIN) ?></p>
		<blockquote><code>[score lang="<em>xxxx</em>"]&hellip;&hellip;[/score]</code></blockquote>
		<p><?php
		_e('<em>xxxx</em> represents the tag name used for certain notation. The following notations are supported by ScoreRender (along with their tag name): ', SR_TEXTDOMAIN);
		foreach (SrNotationBase::$notations as $tag => $notation_data)
		{
			$lines[] = sprintf ("<a target='_blank' href='%s'>%s</a> (<code>%s</code>)",
				$notation_data['url'], $notation_data['name'], $tag);
		}
		print join ( __(', '), $lines );
		?></p>
		<p><?php printf (__('For example, to add a score fragment in ABC notation, use %s. For compatibility the shorthand %s is also supported but might be discontinued later. Click on the links to read more about each notation, including examples and documentation about the notation.', SR_TEXTDOMAIN),
				'<code style="white-space: nowrap">[score lang="abc"]&hellip;&hellip;[/score]</code>',
				'<code style="white-space: nowrap">[<em>xxxx</em>]&hellip;&hellip;[/<em>xxxx</em>]</code>'); ?></p>
		<p><?php _e('There are a few other attributes beside <code>lang=</code>, controlling how music image content is presented:', SR_TEXTDOMAIN) ?></p>
		<table class="widefat" cellspacing="0">
		<thead>
		<tr class="thead"><?php
		$a[] = __('Attribute name'    , SR_TEXTDOMAIN);
		$a[] = __('Accepted parameter', SR_TEXTDOMAIN);
		$a[] = __('Example'           , SR_TEXTDOMAIN);
		foreach ($a as $b) echo '<th>' . $b . '</th>';
		?></tr>
		</thead>
		<tbody>
<?php
		$rows = array (
			array ('<code>color</code>', __('Hex color code', SR_TEXTDOMAIN), '<code>color="#A4B5FF"</code>'),
			array ('<code>clipboard</code>', __('Whether flash clipboard shall be overlayed onto image', SR_TEXTDOMAIN), '<code>clipboard="no"</code>'),
			array ('<code>midi</code>', __('Whether midi download link shall be provided', SR_TEXTDOMAIN), '<code>midi="yes"</code>'),
			array ('<code>tempo</code>', __('Indicate tempo for ABC notation (in quaver per minute, only used for <code>abc2midi</code>)', SR_TEXTDOMAIN), '<code>tempo="84"</code>'),
		);
		$out = '';
		foreach ($rows as $row) {
			$out .= '<tr>';
			foreach ($row as $cell)
				$out .= '<td>' . $cell . '</td>';
			$out .= "</tr>\n";
		}
		print $out;
?>
		</tbody>
		</table>
	</div>

<?php
	// path options
	$this->admin_section_path();

	// program location options
	$this->admin_section_prog();

	// image options
	$this->admin_section_image();

	// content options
	$this->admin_section_content();

	// caching options
	$this->admin_section_caching();
?>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php _e('Update Options &raquo;', SR_TEXTDOMAIN) ?>" />
	</p>

	</form>
</div>
	<?php
} /* }}} */

/**
 * Add 'Settings' link to entry in global plugin admin page, alongside the
 * Activate/Deactivate links. This is used as a WP hook.
 *
 * @param array $links Reference to array of links shown on plugin admin page
 * @param string $file Part of plugin file name under plugin dir
 * @since 0.3.50
 */
public function settings_link ($links, $file) /* {{{ */
{
	if ( $file == 'scorerender/wp-scorerender.php' )
		$links[] = sprintf ('<a href="%s">%s</a>',
				admin_url ('options-general.php?page=scorerender'),
				__('Settings')	// use global WP translation
			   );
	return $links;
} /* }}} */

/**
 * Append submenu item into WordPress menu
 *
 * @uses ScoreRenderAdmin::admin_head()
 * @uses ScoreRenderAdmin::admin_footer()
 * @uses ScoreRenderAdmin::admin_page()
 */
public function register_admin_page () /* {{{ */
{
	$plugin_page = add_options_page (__('ScoreRender options', SR_TEXTDOMAIN), 'ScoreRender',
			'manage_options', 'scorerender', array (&$this, 'admin_page'));
	add_action('admin_head-' . $plugin_page, array (&$this, 'admin_head'));
	// not using print_scripts hooks, not sanitized until WP 2.8
	add_action('admin_footer-' . $plugin_page, array (&$this, 'admin_footer'));
} /* }}} */

/**
 * Output warning to tell users to turn off 'correct invalidly nested
 * XHTML automatically' option.
 */
public function turn_off_balance_tags()
{
	echo '<div id="balancetag-warning" class="error"><p>' .
		sprintf (__('<strong>OPTION CONFLICT</strong>: The &#8216;correct invalidly nested XHTML automatically&#8217; option conflicts with ScoreRender plugin, because it will mangle certain Lilypond and Mup fragments. The option is available in <a href="%s">Writing option page</a>.', SR_TEXTDOMAIN), "options-writing.php") .
		"</p></div>";
}

/**
 * Class constructor, for adding wordpress admin related hooks
 *
 * @uses ScoreRenderAdmin::register_admin_page()
 * @uses ScoreRenderAdmin::settings_link()
 * @uses ScoreRenderAdmin::turn_off_balance_tags()
 * @access private
 */
public function __construct () /* {{{ */
{
	add_action ('admin_menu',            array (&$this, 'register_admin_page'));
	add_filter ('plugin_action_links',   array (&$this, 'settings_link'), 10, 2);
	if ( 0 != get_option('use_balanceTags') )
		add_action ('admin_notices', array (&$this, 'turn_off_balance_tags'));

} /* }}} */

} // end class

global $sr_admin;
$sr_admin = new ScoreRenderAdmin();

/* vim: set cindent foldmethod=marker : */
?>
