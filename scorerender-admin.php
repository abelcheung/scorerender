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


/**
 * This class is used for encapsulating various admin related functions
 *
 * @since 0.3.50
 * @package ScoreRender
*/
class ScoreRenderAdmin
{

/**
 * Returns number of cached images inside cache directory
 *
 * @since 0.2
 * @return integer number of images inside cache directory, or -1 if cache dir can't be read
 * @access private
 */
private function get_num_of_images ()
{
	global $sr_options;

	if (false === ($handle = opendir ($sr_options['CACHE_DIR']))) return -1;

	$count = 0;
	while (false !== ($file = readdir ($handle)))
		if (preg_match (REGEX_CACHE_IMAGE, $file)) $count++;

	closedir ($handle);
	return $count;
}


/**
 * Remove all cached images in cache directory
 *
 * @since 0.2
 * @access private
 */
private function remove_cache ()
{
	global $sr_options;

	if (false === ($handle = opendir ($sr_options['CACHE_DIR']))) return;

	while (false !== ($file = readdir ($handle)))
	{
		if (preg_match (REGEX_CACHE_IMAGE, $file))
			@unlink ($sr_options['CACHE_DIR'] . DIRECTORY_SEPARATOR . $file);
	}
	closedir ($handle);

	return;
}


/**
 * Check if cache folder and cache URL settings match or not.
 *
 * This is done by creating a temp file with random file name, and
 * see if it is accessible via URL.
 *
 * Note that no error checking is done for path and URL, so make sure
 * their validity are confirmed before using.
 *
 * @since 0.3
 * @param string $path Cache full path
 * @param string $url Cache URL
 * @return boolean Whether both point to the same location.
 */
private function cache_location_match ($path, $url)
{
	$retval = true;
	/*
	 * Just a very crude check. Non-existance of URL before file creation
	 * is not verified; neither does non-existance of URL after file removal
	 */
	$tmpfile = tempnam ($path, (string) mt_rand());

	if (false === $tmpfile)
		$retval = false;

	else
	{
		if ( false === strpos ($tmpfile, $path) )
			$retval = false;
		elseif ( false === ( $fh = @fopen (
				trailingslashit($url).basename($tmpfile), 'r') ) )
			$retval = false;
		else
			fclose ($fh);

		unlink ($tmpfile);
	}
	return $retval;
}


/**
 * Update ScoreRender options in database with submitted options.
 *
 * A warning banner will be shown on top of admin page for each
 * error encountered in various options. In some cases supplied
 * config values will be discarded.
 *
 * @uses ScoreRenderAdmin::cache_location_match()
 * @uses transform_paths()
 * @uses ScoreRender::is_web_hosting()
 * @uses ScoreRender::is_prog_usable()
 * @uses scorerender_get_def_settings()
 */
private function update_options ()
{
	if ( !current_user_can ('manage_options') )
		wp_die (__('Cheatin&#8217; uh?', TEXTDOMAIN));

	global $sr_options;

	$newopt = (array) $_POST['ScoreRender'];
	transform_paths ($newopt, TRUE);
	$errmsgs = array ();

	$sr_adm_msgs = array
	(
		'temp_dir_not_writable'  => array (
			'level'   => MSG_WARNING,
			'content' => __('Temporary directory is NOT writable! Will fall back to system default setting.', TEXTDOMAIN)),
		'cache_dir_undefined'    => array (
			'level'   => MSG_FATAL  ,
			'content' => __('Cache directory is NOT defined! Image can not be placed inside appropriate directory. The plugin will stop working.', TEXTDOMAIN)),
		'cache_dir_not_writable' => array (
			'level'   => MSG_FATAL  ,
			'content' => __('Cache directory is NOT writable! Image can not be placed inside appropriate directory. The plugin will stop working.', TEXTDOMAIN)),
		'cache_url_undefined'    => array (
			'level'   => MSG_FATAL  ,
			'content' => __('Cache URL is NOT defined! The plugin will stop working.', TEXTDOMAIN)),
		'cache_dir_url_unmatch'  => array (
			'level'   => MSG_WARNING,
			'content' => __('Cache directory and URL probably do not correspond to the same location.', TEXTDOMAIN)),
		'wrong_frag_per_comment' => array (
			'level'   => MSG_WARNING,
			'content' => __('Fragment per comment is not a non-negative integer. Value discarded.', TEXTDOMAIN)),
		'wrong_image_max_width'  => array (
			'level'   => MSG_WARNING,
			'content' => __('Image maximum width must be positive integer >= 72. Value discarded.', TEXTDOMAIN)),
		'convert_bin_problem'    => array (
			'level'   => MSG_FATAL  ,
			'content' => __('<tt>convert</tt> program is NOT defined or NOT executable! The plugin will stop working.', TEXTDOMAIN)),
		'prog_check_disabled'    => array (
			'level'   => MSG_WARNING,
			'content' => sprintf (__('Some PHP functions are disabled due to security reasons. Program validation will not be done.', TEXTDOMAIN))),
	);

	// error message definition for each notation
	do_action_ref_array ('scorerender_define_adm_msgs', array(&$sr_adm_msgs));

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

	if ( ! in_array ('cache_dir_undefined'   , $errmsgs) &&
	     ! in_array ('cache_dir_not_writable', $errmsgs) &&
	     ! in_array ('cache_url_undefined'   , $errmsgs) )
	{
		if ( ! $this->cache_location_match ($newopt['CACHE_DIR'], $newopt['CACHE_URL']) )
			$errmsgs[] = 'cache_dir_url_unmatch';
	}

	if ( ScoreRender::is_web_hosting() )
		$errmsgs[] = 'prog_check_disabled';

	if ( ! ScoreRender::is_prog_usable ('ImageMagick', $newopt['CONVERT_BIN'], '-version') )
		$errmsgs[] = 'convert_bin_problem';

	// Any boolean values set to false would not appear in $_POST
	$var_types = scorerender_get_def_settings (TYPES_ONLY);
	foreach ($var_types as $key => $type)
		if ($type == 'bool')
			$newopt[$key] = isset ($newopt[$key]);

	if ( isset ($newopt['FRAGMENT_PER_COMMENT']) &&
		!ctype_digit ($newopt['FRAGMENT_PER_COMMENT']) )
	{
		$errmsgs[] = 'wrong_frag_per_comment';
		unset ($newopt['FRAGMENT_PER_COMMENT']);
	}

	if ( !ctype_digit ($newopt['IMAGE_MAX_WIDTH']) || ($newopt['IMAGE_MAX_WIDTH'] < (1 * DPI)) )
	{
		$errmsgs[] = 'wrong_image_max_width';
		unset ($newopt['IMAGE_MAX_WIDTH']);
	}

	// program checking for each notation
	do_action_ref_array ('scorerender_check_notation_progs', array(&$errmsgs, &$newopt));

	$sr_options = array_merge ($sr_options, $newopt);
	transform_paths ($sr_options, TRUE);
	update_option ('scorerender_options', $sr_options);
	transform_paths ($sr_options, FALSE);

	if ( !empty ($errmsgs) )
	{
		foreach (array_values ($errmsgs) as $m)
		{
			if ($sr_adm_msgs[$m]['level'] == MSG_WARNING)
			{
				$class = 'scorerender-warning';
				$mesg = __('WARNING: %s', TEXTDOMAIN);
			}
			elseif ($sr_adm_msgs[$m]['level'] == MSG_FATAL)
			{
				$class = 'scorerender-error';
				$mesg = __('ERROR: %s', TEXTDOMAIN);
			}

			printf ("<div id='%s' class='error %s'><p><strong>%s</strong></p></div>\n",
					'sr-err-' . $sr_adm_msgs[$m],
					$class,
					sprintf ($mesg, $sr_adm_msgs[$m]['content'])
			       );
		}
	}
	else
		echo '<div id="message" class="updated fade"><p><strong>' .
			__('Options saved.', TEXTDOMAIN) . "</strong></p></div>\n";
}


/**
 * WP hook added to admin page header
 *
 * @since 0.3
 * @access private
 */
public function admin_head()
{
?>
<style type="text/css">
	.sr-help-icon {vertical-align:middle;border:none;}
	#note_color_picker {width:36px; height:36px;}
	#note_color_picker div {
		position:relative;
		top:4px; left:4px;
		width:28px; height:28px;
		background:url(<?php echo plugins_url ('scorerender/images/select2.png'); ?>) center;
	}
</style>
<link rel="stylesheet" media="screen" type="text/css" href="<?php echo plugins_url ('scorerender/misc/colorpicker.css'); ?>" />
<?php
}


/**
 * WP hook added to admin page footer
 *
 * @since 0.3.50
 * @access private
 */
public function admin_footer()
{
	global $sr_options;
?>
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function($){
	$("#comment_enabled").click(function(){
		if ( $("#comment_enabled").is(":checked") )
			$("#fragment_per_comment").removeAttr("disabled");
		else
			$("#fragment_per_comment").attr('disabled', true);
	});
	$('#note_color_picker').ColorPicker({
		eventName: 'mouseover',
		color: '<?php echo $sr_options['NOTE_COLOR']; ?>',
		onShow: function (foo) {
			$(foo).fadeIn (500);
			return false;
		},
		onHide: function (foo) {
			$(foo).fadeOut (500);
			return false;
		},
		onChange: function (hsb, hex, rgb) {
			$('#note_color_picker div').css('background-color', '#' + hex);
			$('#note_color').val('#' + hex);
		}
	});
});
//]]>
</script>
<script type="text/javascript" src="<?php echo plugins_url ('scorerender/misc/colorpicker.js'); ?>"></script>
<?
}

/**
 * Section of admin page about path options
 *
 * @since 0.2
 * @access private
 */
private function admin_section_path ()
{
	global $sr_options;
?>

<h3><?php _e('Path options', TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><label for="temp_dir"><?php _e('Temporary directory:', TEXTDOMAIN) ?></label></th>
<td>
<input name="ScoreRender[TEMP_DIR]" type="text" id="temp_dir" value="<?php echo $sr_options['TEMP_DIR']; ?>" class="regular-text code" />
<div class="setting-description"><?php _e('Must be writable and ideally <strong>NOT</strong> accessible from web. System default will be used if left blank.', TEXTDOMAIN) ?></div>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="cache_dir"><?php _e('Image cache directory:', TEXTDOMAIN) ?></label></th>
<td>
<input name="ScoreRender[CACHE_DIR]" type="text" id="cache_dir" value="<?php echo $sr_options['CACHE_DIR']; ?>" class="regular-text code" />
<div class="setting-description"><?php _e('Must be writable and accessible from web.', TEXTDOMAIN) ?></div>
</td>
</tr>

<tr valign="top">
<th scope="row"><label for="cache_url"><?php _e('Image cache URL:', TEXTDOMAIN) ?></label></th>
<td>
<input name="ScoreRender[CACHE_URL]" type="text" id="cache_url" value="<?php echo $sr_options['CACHE_URL']; ?>" class="regular-text code" /><br />
<div class="setting-description"><?php _e('Must correspond to the image cache directory above.', TEXTDOMAIN) ?></div>
</td>
</tr>

</table>
<?php
}


/**
 * Section of admin page about program and file locations
 *
 * @since 0.2
 * @access private
 */
private function admin_section_prog ()
{
	global $sr_options;
?>

<h3><?php _e('Program and file locations', TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><label for="convert_bin"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>convert</code>') ?></label></th>
<td><input name="ScoreRender[CONVERT_BIN]" type="text" id="convert_bin" value="<?php echo $sr_options['CONVERT_BIN']; ?>" class="regular-text code" /></td>
</tr>

<?php
	$output = apply_filters ('scorerender_prog_and_file_loc', $output);
	echo $output;
?>

</table>
<?php
}

/**
 * Section of admin page about image options
 *
 * @since 0.2
 * @access private
 */
private function admin_section_image ()
{
	global $sr_options;
?>
<h3><?php _e('Image options', TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><?php _e('Max image width:', TEXTDOMAIN) ?></th>
<td><?php printf(__('%s pixels', TEXTDOMAIN), '<input type="text" name="ScoreRender[IMAGE_MAX_WIDTH]" id="image_max_width" value="' . $sr_options['IMAGE_MAX_WIDTH'] . '" class="small-text" />'); ?>
<div class="setting-description"><?php _e('Note that this value is just an approximation, please allow for &#x00B1;10% difference. Some programs like lilypond would not use the full image width if passage is not long enough.', TEXTDOMAIN) ?></div>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Show source:', TEXTDOMAIN) ?></th>
<td><label for="show_input"><input type="checkbox" name="ScoreRender[SHOW_SOURCE]" id="show_input" value="1" <?php checked('1', $sr_options['SHOW_SOURCE']); ?> />
<?php _e('Show music source in new browser window/tab when image is clicked', TEXTDOMAIN); ?></label>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Note color:', TEXTDOMAIN) ?></th>
<td>
<div id="note_color_picker"><div style="background-color: <?php echo $sr_options['NOTE_COLOR'] ?>"></div></div>
<div class="setting-description"><?php _e('Move mouse pointer to the colored square above to pick desired color.', TEXTDOMAIN) ?></div>
<input type="hidden" id="note_color" name="ScoreRender[NOTE_COLOR]" value="<?php echo $sr_options['NOTE_COLOR'] ?>" />
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('IE Hack:', TEXTDOMAIN) ?></th>
<td>
<label for="use_ie6_png_alpha_fix"><input type="checkbox" name="ScoreRender[USE_IE6_PNG_ALPHA_FIX]" id="use_ie6_png_alpha_fix" value="1" <?php checked('1', $sr_options['USE_IE6_PNG_ALPHA_FIX']); ?> />
<?php _e('Enable fake translucent image in IE6', TEXTDOMAIN) ?></label>
<div class="setting-description"><?php printf ('Turning on this option enables <a href="%s">emulation of translucency in PNG images</a> in IE 5.5/6, which is not supported by IE below version 7. This option only affects images rendered by ScoreRender. <strong>Make sure you have NOT installed any plugin with the same functionality before turning on this option, which may conflict with each other.</strong>', 'http://www.twinhelix.com/css/iepngfix/' ); ?></div>
</td>
</tr>

</table>
<?php
}



/**
 * Section of admin page about content options
 *
 * @since 0.2
 * @access private
 */
private function admin_section_content ()
{
	global $sr_options;
?>
<h3><?php _e('Content options', TEXTDOMAIN) ?></h3>
<table class="form-table">

<tr valign="top">
<th scope="row"><?php _e('When rendering failed:', TEXTDOMAIN); ?></th>
<td>
<fieldset><legend class="hidden screen-reader-text"><?php _e('When rendering failed:', TEXTDOMAIN); ?></legend>
<label for="on_err_show_message"><input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_message" value="1" <?php checked(ON_ERR_SHOW_MESSAGE, $sr_options['ERROR_HANDLING']); ?> />
<?php _e('Show error message', TEXTDOMAIN) ?></label><br />
<label for="on_err_show_fragment"><input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_fragment" value="2" <?php checked(ON_ERR_SHOW_FRAGMENT, $sr_options['ERROR_HANDLING']); ?> />
<?php _e('Show original, unmodified music fragment', TEXTDOMAIN) ?></label><br />
<label for="on_err_show_nothing"><input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_nothing" value="3" <?php checked(ON_ERR_SHOW_NOTHING, $sr_options['ERROR_HANDLING']); ?> />
<?php _e('Show nothing', TEXTDOMAIN) ?></label>
</fieldset>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Comment rendering:', TEXTDOMAIN) ?></th>
<td>
<label for="comment_enabled"><input type="checkbox" name="ScoreRender[COMMENT_ENABLED]" id="comment_enabled" value="1" <?php checked('1', $sr_options['COMMENT_ENABLED']); ?> />
<?php _e('Enable rendering for comments', TEXTDOMAIN) ?></label>
<div class="setting-description" style="color: red;"><?php _e('Only turn on if commenters are trusted', TEXTDOMAIN) ?></div>
</td>
</tr>

<tr valign="top">
<th scope="row"><?php _e('Maximum number of fragment per comment:', TEXTDOMAIN) ?></th>
<td>
<label for="fragment_per_comment"><input type="text" name="ScoreRender[FRAGMENT_PER_COMMENT]" id="fragment_per_comment" value="<?php echo $sr_options['FRAGMENT_PER_COMMENT']; ?>" <?php if (1 != $sr_options['COMMENT_ENABLED']) { echo ' disabled="disabled"'; } ?> class="small-text" /></label>
<span class="setting-description"><?php _e('(0 means unlimited)', TEXTDOMAIN) ?><br /><?php printf (__('If you don&#8217;t want comment rendering, turn off &#8216;<i>%s</i>&#8217; checkbox above instead. This option does not affect posts and pages.', TEXTDOMAIN), __('Enable rendering for comments', TEXTDOMAIN)); ?></span>
</td>
</tr>

</table>
<?php
}



/**
 * Section of admin page about caching options
 *
 * @uses ScoreRenderAdmin::get_num_of_images()
 * @since 0.2
 * @access private
 */
private function admin_section_caching ()
{
	global $sr_options;
?>
	<h3><?php _e('Caching', TEXTDOMAIN) ?></h3>
<?php
$img_count = $this->get_num_of_images();

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
<?php if ( is_writable ($sr_options['CACHE_DIR']) ) : ?>
	<input type="submit" name="clear_cache" class="button-secondary" value="<?php _e('Clear Cache &raquo;', TEXTDOMAIN) ?>" />
<?php else : ?>
	<input type="submit" name="clear_cache" class="button-secondary" disabled="disabled" value="<?php _e('Clear Cache &raquo;', TEXTDOMAIN) ?>" />
	<br /><font color="red"><?php _e('Cache can&#8217;t be cleared because directory is not writable.', TEXTDOMAIN) ?><br /><?php _e('Please change &#8216;Image cache directory&#8217; setting, or fix its permission.', TEXTDOMAIN) ?></font>
<?php endif; ?>
<?php
}


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
 *
 * @access private
 */
public function admin_page ()
{
	global $sr_options, $notations;

	if ( isset($_POST['clear_cache']) && isset($_POST['ScoreRender']) )
	{
		check_admin_referer ('scorerender-update-options');
		$this->remove_cache();
	}

	if ( isset($_POST['Submit']) && isset($_POST['ScoreRender']) )
	{
		check_admin_referer ('scorerender-update-options');
		$this->update_options();
	}
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div>
	<h2><?php _e('ScoreRender options', TEXTDOMAIN) ?> <a href="javascript:" title="<?php _e('Click to show help') ?>" onclick="jQuery('#sr-help-1').slideToggle('fast');"><img src="<?php echo plugins_url ('scorerender/images/info-icon.png'); ?>" width="24" height="24" class="sr-help-icon" /></a></h2>

	<form method="post" action="" id="scorerender-conf">
	<?php wp_nonce_field ('scorerender-update-options') ?>

	<div id="sr-help-1" class="hidden">
		<p><?php _e('ImageMagick &ge; 6.3.6-2 must be present and working (specifically, the <code>convert</code> program). For each kind of notation, leaving corresponding program location empty means disabling that notation support automatically, except GUIDO which does not use any program.', TEXTDOMAIN); ?></p>

		<p><?php _e('The following notations are supported by ScoreRender, along with starting and ending shortcode after each notation name. Each music fragment must be enclosed by corresponding pair of shortcodes. Click on the links to read more about each notation.', TEXTDOMAIN); ?></p>
		<ul>
<?php	foreach ($notations as $tag => $notation_data) : ?>
		<li><a target="_blank" href="<?php echo $notation_data['url']; ?>"><?php echo $notation_data['name']; ?></a>
		(<code><?php echo $notation_data['starttag']; ?></code>, <code><?php echo $notation_data['endtag']; ?></code>)</li>
<?php	endforeach; ?>
		</ul>
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
	<input type="submit" name="Submit" class="button-primary" value="<?php _e('Update Options &raquo;', TEXTDOMAIN) ?>" />
	</p>

	</form>
</div>
	<?php
}

/**
 * Add 'Settings' link to entry in global plugin admin page, alongside the
 * Activate/Deactivate links. This is used as a WP hook.
 *
 * @param array $links Reference to array of links shown on plugin admin page
 * @param string $file Part of plugin file name under plugin dir
 * @since 0.3.50
 */
public function settings_link ($links, $file)
{
	if ( $file == 'scorerender/wp-scorerender.php' )
		if ( function_exists ('admin_url') )
			$links[] = sprintf ('<a href="%s">%s</a>',
					admin_url ('options-general.php?page=scorerender'),
					__('Settings')	// use global WP translation
				   );
	return $links;
}

/**
 * Append submenu item into WordPress menu
 *
 * @uses ScoreRenderAdmin::admin_head()
 * @uses ScoreRenderAdmin::admin_footer()
 * @uses ScoreRenderAdmin::admin_page()
 */
public function register_admin_page ()
{
	$plugin_page = add_options_page (__('ScoreRender options', TEXTDOMAIN), 'ScoreRender',
			'manage_options', 'scorerender', array (&$this, 'admin_page'));
	add_action('admin_head-' . $plugin_page, array (&$this, 'admin_head'));
	add_action('admin_footer-' . $plugin_page, array (&$this, 'admin_footer'));
}

/**
 * Output warning to tell users to turn off 'correct invalidly nested
 * XHTML automatically' option.
 */
public function turn_off_balance_tags()
{
	echo '<div id="balancetag-warning" class="error"><p>' .
		sprintf (__('<strong>OPTION CONFLICT</strong>: The &#8216;correct invalidly nested XHTML automatically&#8217; option conflicts with ScoreRender plugin, because it will mangle certain Lilypond and Mup fragments. The option is available in <a href="%s">Writing option page</a>.', TEXTDOMAIN), "options-writing.php") .
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
public function __construct ()
{
	add_action ('admin_menu',            array (&$this, 'register_admin_page'));
	add_filter ('plugin_action_links',   array (&$this, 'settings_link'), 10, 2);
	if ( 0 != get_option('use_balanceTags') )
		add_action ('admin_notices', array (&$this, 'turn_off_balance_tags'));

}

} // end class

global $sr_admin;
$sr_admin = new ScoreRenderAdmin();

?>
