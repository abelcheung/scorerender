<?php
/*
Plugin Name: ScoreRender
Plugin URI: http://me.abelcheung.org/devel/scorerender/
Description: Renders inline music score fragments in WordPress. Heavily based on <a href="http://chris-lamb.co.uk/code/figurerender/">FigureRender</a> from Chris Lamb.
Author: Abel Cheung
Version: 0.1
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


// Increment this number if database has new or changed config options
define ('DATABASE_VERSION', 3);

// Error constants
define('ERR_INVALID_INPUT', -1);
define('ERR_CACHE_DIRECTORY_NOT_WRITABLE', -2);
define('ERR_TEMP_DIRECTORY_NOT_WRITABLE', -3);
define('ERR_TEMP_FILE_NOT_WRITABLE', -4);
define('ERR_IMAGE_CONVERT_FAILURE', -5);
define('ERR_RENDERING_ERROR', -6);

require_once('class.scorerender.inc.php');
require_once('class.abc.inc.php');
require_once('class.guido.inc.php');
require_once('class.lilypond.inc.php');
require_once('class.mup.inc.php');

$default_tmp_dir = '/tmp';
$scorerender_options = array ();

function scorerender_get_options ()
{
	global $scorerender_options, $default_tmp_dir;

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
		'DB_VERSION'        => DATABASE_VERSION,
		'TEMP_DIR'          => $default_tmp_dir,
		'CONVERT_BIN'       => '/usr/bin/convert',
		'CACHE_DIR'         => ABSPATH . get_option('upload_path'),
		'CACHE_URL'         => get_option('siteurl') . '/' . get_option('upload_path'),
		'INVERT_IMAGE'      => false,
		'TRANSPARENT_IMAGE' => true,
		'SHOW_SOURCE'       => false,

		'LILYPOND_MARKUP_START'    => '[lilypond]',
		'LILYPOND_MARKUP_END'      => '[/lilypond]',
		'LILYPOND_CONTENT_ENABLED' => true,
		'LILYPOND_COMMENT_ENABLED' => false,
		'LILYPOND_BIN'             => '/usr/bin/lilypond',

		'MUP_MARKUP_START'    => '[mup]',
		'MUP_MARKUP_END'      => '[/mup]',
		'MUP_CONTENT_ENABLED' => true,
		'MUP_COMMENT_ENABLED' => false,
		'MUP_BIN'             => '/usr/local/bin/mup',
		'MUP_MAGIC_FILE'      => '',

		'GUIDO_MARKUP_START'    => '[guido]',
		'GUIDO_MARKUP_END'      => '[/guido]',
		'GUIDO_CONTENT_ENABLED' => true,
		'GUIDO_COMMENT_ENABLED' => false,

		'ABC_MARKUP_START'    => '[abc]',
		'ABC_MARKUP_END'      => '[/abc]',
		'ABC_CONTENT_ENABLED' => true,
		'ABC_COMMENT_ENABLED' => false,
		'ABCM2PS_BIN'         => '/usr/bin/abcm2ps',
	);

	$scorerender_options = array_merge ($defaults, $scorerender_options);
	$scorerender_options['DB_VERSION'] = DATABASE_VERSION;
	update_option ('scorerender_options', $scorerender_options);
	return;
}

function parse_input($input)
{
	return trim (html_entity_decode ($input));
}

function scorerender_generate_html_error ($msg)
{
	return '<p><b>ScoreRender Error:</b> <i>' . $msg . '</i></p>';
}

function scorerender_process_result ($result, $input, $render)
{
	global $scorerender_options;

	switch ($result)
	{
		case ERR_INVALID_INPUT:
			return scorerender_generate_html_error (__('Invalid input')) . '<br/><pre>' . $input . '</pre>';
		case ERR_CACHE_DIRECTORY_NOT_WRITABLE:
			return scorerender_generate_html_error (__('Cache directory not writable!'));
		case ERR_TEMP_DIRECTORY_NOT_WRITABLE:
			return scorerender_generate_html_error (__('Temporary directory not writable!'));
		case ERR_TEMP_FILE_NOT_WRITABLE:
			return scorerender_generate_html_error (__('Temporary file not writable!'));
		case ERR_IMAGE_CONVERT_FAILURE:
			return scorerender_generate_html_error (__('Image convert failure!'));
		case ERR_RENDERING_ERROR:
			return scorerender_generate_html_error (__('The external rendering application did not complete successfully.') . '<br /><textarea cols=80 rows=10 READONLY>' . $render->getCommandOutput() . '</textarea>');
	}

	// No errors, so generate HTML
	$html = '<img style="vertical-align: bottom" ';

	if ($scorerender_options['SHOW_SOURCE'])
		$html .= sprintf ('alt="%s" ', htmlentities($input, ENT_COMPAT, get_bloginfo('charset')));
	else
		$html .= sprintf ('alt="%s" ',  __('Music fragment'));

	$html .= sprintf ('src="%s/%s" />', $scorerender_options['CACHE_URL'], $result);

	return $html;
}

function lilypond_filter ($matches)
{
	global $scorerender_options;

	$input = parse_input ($matches[1]);
	$render = new LilypondRender ($input, $scorerender_options);
	$result = $render->render();

	return scorerender_process_result ($result, $input, $render);
}

function mup_filter ($matches)
{
	global $scorerender_options;

	$input = parse_input ($matches[1]);
	$render = new MupRender ($input, $scorerender_options);
	$result = $render->render();

	return scorerender_process_result ($result, $input, $render);
}

function guido_filter ($matches)
{
	global $scorerender_options;

	$input = parse_input ($matches[1]);
	$render = new GuidoRender ($input, $scorerender_options);
	$result = $render->render();

	return scorerender_process_result ($result, $input, $render);
}

function abc_filter ($matches)
{
	global $scorerender_options;

	$input = parse_input ($matches[1]);
	$render = new ABCRender ($input, $scorerender_options);
	$result = $render->render();

	return scorerender_process_result ($result, $input, $render);
}

function replace_content (&$content, $filter_function_name, $option_name, $start_tag_name, $end_tag_name)
{
	global $scorerender_options;

	if ($scorerender_options[$option_name])
	{
		$search = sprintf ('~\Q%s\E(.*?)\Q%s\E~s',
			$scorerender_options[$start_tag_name],
			$scorerender_options[$end_tag_name]);
		$content = preg_replace_callback ($search, $filter_function_name, $content);
	}
}

function scorerender_content ($content)
{
	replace_content ($content, 'lilypond_filter', 'LILYPOND_CONTENT_ENABLED',
			 'LILYPOND_MARKUP_START', 'LILYPOND_MARKUP_END');
	replace_content ($content, 'mup_filter', 'MUP_CONTENT_ENABLED',
			 'MUP_MARKUP_START', 'MUP_MARKUP_END');
	replace_content ($content, 'guido_filter', 'GUIDO_CONTENT_ENABLED',
			 'GUIDO_MARKUP_START', 'GUIDO_MARKUP_END');
	replace_content ($content, 'abc_filter', 'ABC_CONTENT_ENABLED',
			 'ABC_MARKUP_START', 'ABC_MARKUP_END');

	return $content;
}

function scorerender_comment ($content)
{
	replace_content ($content, 'lilypond_filter', 'LILYPOND_COMMENT_ENABLED',
			 'LILYPOND_MARKUP_START', 'LILYPOND_MARKUP_END');
	replace_content ($content, 'mup_filter', 'MUP_COMMENT_ENABLED',
			 'MUP_MARKUP_START', 'MUP_MARKUP_END');
	replace_content ($content, 'guido_filter', 'GUIDO_COMMENT_ENABLED',
			 'GUIDO_MARKUP_START', 'GUIDO_MARKUP_END');
	replace_content ($content, 'abc_filter', 'ABC_COMMENT_ENABLED',
			 'ABC_MARKUP_START', 'ABC_MARKUP_END');

	return $content;
}

function scorerender_update_options ()
{
	global $defalt_tmp_dir, $scorerender_options;
	$newopt = (array) $_POST['ScoreRender'];

	$messages = array
	(
		'temp_dir_undefined'         => __('WARNING: Temporary directory is NOT defined! Will fall back to /tmp.'),
		'temp_dir_not_writable'      => __('WARNING: Temporary directory is NOT writable! Will fall back to /tmp.'),
		'cache_dir_undefined'        => __('ERROR: Cache directory is NOT defined! Image can not be placed inside appropriate directory.'),
		'cache_dir_not_writable'     => __('ERROR: Cache directory is NOT writable! Image can not be placed inside appropriate directory.'),
		'cache_url_undefined'        => __('ERROR: Cache URL is NOT defined!'),
		'convert_not_found'          => __('ERROR: Location of <tt>convert</tt> utility is NOT defined!'),
		'convert_not_executable'     => __('ERROR: <tt>convert</tt> utility is NOT executable!'),
		'lilypond_tag_problem'       => __('WARNING: Start and end tag must be both present and different. Lilypond support DISABLED.'),
		'lilypond_binary_problem'    => __('WARNING: <tt>lilypond</tt> not found or not an executable. Lilypond support DISABLED.'),
		'mup_tag_problem'            => __('WARNING: Start and end tag must be both present and different. Mup support DISABLED.'),
		'mup_binary_problem'         => __('WARNING: <tt>mup</tt> not found or not an executable. Mup support DISABLED.'),
		'guido_tag_problem'          => __('WARNING: Start and end tag must be both present and different. GUIDO noteserver support DISABLED.'),
		'abc_tag_problem'            => __('WARNING: Start and end tag must be both present and different. ABC support DISABLED.'),
		'abc_binary_problem'         => __('WARNING: <tt>abcm2ps</tt> not found or not an executable. ABC support DISABLED.'),
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

	$newopt['SHOW_SOURCE'] = isset ($newopt['SHOW_SOURCE'])? true : false;
	$newopt['INVERT_IMAGE'] = isset ($newopt['INVERT_IMAGE'])? true : false;
	$newopt['TRANSPARENT_IMAGE'] = isset ($newopt['TRANSPARENT_IMAGE'])? true : false;

	/*
	 * lilypond options
	 */
	$newopt['LILYPOND_CONTENT_ENABLED'] = isset ($newopt['LILYPOND_CONTENT_ENABLED'])? true : false;
	$newopt['LILYPOND_COMMENT_ENABLED'] = isset ($newopt['LILYPOND_COMMENT_ENABLED'])? true : false;

	if ( empty ($newopt['LILYPOND_MARKUP_START']) ||
	     empty ($newopt['LILYPOND_MARKUP_END']) ||
	     ( !strcmp ($newopt['LILYPOND_MARKUP_START'], $newopt['LILYPOND_MARKUP_END']) ) )
	{
		if ($newopt['LILYPOND_CONTENT_ENABLED'] || $newopt['LILYPOND_COMMENT_ENABLED'])
		{
			$msgs[] = 'lilypond_tag_problem';
			$newopt['LILYPOND_CONTENT_ENABLED'] = false;
			$newopt['LILYPOND_COMMENT_ENABLED'] = false;
		}
	}

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
	$newopt['MUP_CONTENT_ENABLED'] = isset ($newopt['MUP_CONTENT_ENABLED'])? true : false;
	$newopt['MUP_COMMENT_ENABLED'] = isset ($newopt['MUP_COMMENT_ENABLED'])? true : false;

	if ( empty ($newopt['MUP_MARKUP_START']) ||
	     empty ($newopt['MUP_MARKUP_END']) ||
	     ( !strcmp ($newopt['MUP_MARKUP_START'], $newopt['MUP_MARKUP_END']) ) )
	{
		if ($newopt['MUP_CONTENT_ENABLED'] || $newopt['MUP_COMMENT_ENABLED'])
		{
			$msgs[] = 'mup_tag_problem';
			$newopt['MUP_CONTENT_ENABLED'] = false;
			$newopt['MUP_COMMENT_ENABLED'] = false;
		}
	}

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
	$newopt['GUIDO_CONTENT_ENABLED'] = isset ($newopt['GUIDO_CONTENT_ENABLED'])? true : false;
	$newopt['GUIDO_COMMENT_ENABLED'] = isset ($newopt['GUIDO_COMMENT_ENABLED'])? true : false;

	if ( empty ($newopt['GUIDO_MARKUP_START']) ||
	     empty ($newopt['GUIDO_MARKUP_END']) ||
	     ( !strcmp ($newopt['GUIDO_MARKUP_START'], $newopt['GUIDO_MARKUP_END']) ) )
	{
		if ($newopt['GUIDO_CONTENT_ENABLED'] || $newopt['GUIDO_COMMENT_ENABLED'])
		{
			$msgs[] = 'guido_tag_problem';
			$newopt['GUIDO_CONTENT_ENABLED'] = false;
			$newopt['GUIDO_COMMENT_ENABLED'] = false;
		}
	}

	/*
	 * abcm2ps options
	 */
	$newopt['ABC_CONTENT_ENABLED'] = isset ($newopt['ABC_CONTENT_ENABLED'])? true : false;
	$newopt['ABC_COMMENT_ENABLED'] = isset ($newopt['ABC_COMMENT_ENABLED'])? true : false;

	if ( empty ($newopt['ABC_MARKUP_START']) ||
	     empty ($newopt['ABC_MARKUP_END']) ||
	     ( !strcmp ($newopt['ABC_MARKUP_START'], $newopt['ABC_MARKUP_END']) ) )
	{
		if ($newopt['ABC_CONTENT_ENABLED'] || $newopt['ABC_COMMENT_ENABLED'])
		{
			$msgs[] = 'abc_tag_problem';
			$newopt['ABC_CONTENT_ENABLED'] = false;
			$newopt['ABC_COMMENT_ENABLED'] = false;
		}
	}

	if ( !is_executable ($newopt['ABCM2PS_BIN']) )
	{
		if ($newopt['ABC_CONTENT_ENABLED'] || $newopt['ABC_COMMENT_ENABLED'])
		{
			$msgs[] = 'abc_binary_problem';
			$newopt['ABC_CONTENT_ENABLED'] = false;
			$newopt['ABC_COMMENT_ENABLED'] = false;
		}
	}

	/* FIXME: Didn't handle the case when various tags coincide with each other */

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

function scorerender_admin_options() {

	global $scorerender_options;

	if ( isset($_POST['Submit']) && isset($_POST['ScoreRender']) )
	{
		scorerender_update_options();
	}
?>

	<div class="wrap">
	<form method="post" action="" id="scorerender-conf">
	<h2><?php _e('ScoreRender options') ?></h2>

	<p><?php _e('ScoreRender renders inline music fragments inside blog post and/or comment as images. Currently it supports the following formats:'); ?></p>
	<ul>
		<li><a target="_blank" href="http://www.lilypond.org/">Lilypond</a></li>
		<li><?php printf ('%s, used by Mup itself and %s',
			'<a target="_blank" href="http://www.arkkra.com/">Mup</a>',
			'<a target="_blank" href="http://noteedit.berlios.de/">Noteedit</a>'); ?></li>
		<li><a target="_new" href="http://www.informatik.tu-darmstadt.de/AFS/GUIDO/">GUIDO</a></li>
		<li><?php printf ('%s, used by various programs like %s or %s',
			'<a target="_blank" href="http://abcnotation.org.uk/">ABC notation</a>',
			'<a target="_blank" href="http://moinejf.free.fr/">abcm2ps</a>',
			'<a target="_blank" href="http://www.ihp-ffo.de/~msm/">abc2ps</a>'); ?></li>
	</ul>

	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Update Options') ?> &raquo;" />
	</p>

	<!-- path options -->
	<fieldset class="options">
		<legend><?php _e('Path options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Temporary directory:') ?></th>
			<td>
				<input name="ScoreRender[TEMP_DIR]" class="code" type="text" id="temp_dir" value="<?php echo attribute_escape($scorerender_options['TEMP_DIR']); ?>" size="60" /><br />
				<?php _e('Must be writable and ideally located outside the web-accessible area.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache directory:') ?></th>
			<td>
				<input name="ScoreRender[CACHE_DIR]" class="code" type="text" id="cache_dir" value="<?php echo attribute_escape($scorerender_options['CACHE_DIR']); ?>" size="60" /><br />
				<?php _e('Must be writable and located inside the web-accessible area.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache URL:') ?></th>
			<td>
				<input name="ScoreRender[CACHE_URL]" class="code" type="text" id="cache_url" value="<?php echo attribute_escape($scorerender_options['CACHE_URL']); ?>" size="60" /><br />
				<?php _e('Must correspond to the image cache directory above.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s %s binary:'), '<a target="_new" href="http://www.imagemagick.net/">ImageMagick</a>', '<code>convert</code>') ?></th>
			<td>
				<input name="ScoreRender[CONVERT_BIN]" class="code" type="text" id="convert_bin" value="<?php echo attribute_escape($scorerender_options['CONVERT_BIN']); ?>" size="40" />
			</td>
		</tr>
		</table>
	</fieldset>

	<!-- image options -->
	<fieldset class="options">
		<legend><?php _e('Image options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<td>
				<label for="show_input">
				<input type="checkbox" name="ScoreRender[SHOW_SOURCE]" id="show_input" value="1" <?php checked('1', $scorerender_options['SHOW_SOURCE']); ?> /> <?php _e('Show source in image ALT attribute'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<td>
				<label for="invert_image">
				<input type="checkbox" name="ScoreRender[INVERT_IMAGE]" id="invert_image" value="1" <?php checked('1', $scorerender_options['INVERT_IMAGE']); ?> /> <?php _e('Invert image colours'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<td>
				<label for="transparent_image">
				<input type="checkbox" name="ScoreRender[TRANSPARENT_IMAGE]" id="transparent_image" value="1" <?php checked('1', $scorerender_options['TRANSPARENT_IMAGE']); ?> /> <?php _e('Use transparent background') ?> <?php _e('(IE6 does not support transparent PNG)'); ?></label>
			</td>
		</tr>
		</table>
	</fieldset>

	<!-- lilypond options -->
	<fieldset class="options">
		<legend><?php _e('Lilypond options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<label for="lilypond_content">
				<input type="checkbox" name="ScoreRender[LILYPOND_CONTENT_ENABLED]" id="lilypond_content" value="1" <?php checked('1', $scorerender_options['LILYPOND_CONTENT_ENABLED']); ?> /> <?php _e('Enable parsing for posts and pages'); ?></label><br />
				<label for="lilypond_comments">
				<input type="checkbox" name="ScoreRender[LILYPOND_COMMENT_ENABLED]" id="lilypond_comment" value="1" <?php checked('1', $scorerender_options['LILYPOND_COMMENT_ENABLED']); ?> /> <?php printf ('%s %s', __('Enable parsing for comments'), __('(<span style="font-weight: bold; color: red;">Warning:</span> security concern for exploiting weakness in binaries.)')); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>
				<?php _e('Start:') ?> <input name="ScoreRender[LILYPOND_MARKUP_START]" class="code" type="text" id="lilypond_markup_start" value="<?php echo attribute_escape($scorerender_options['LILYPOND_MARKUP_START']); ?>" size="14" />
				<?php _e('End:') ?> <input name="ScoreRender[LILYPOND_MARKUP_END]" class="code" type="text" id="lilypond_markup_end" value="<?php echo attribute_escape($scorerender_options['LILYPOND_MARKUP_END']); ?>" size="14" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<code>lilypond</code>'); ?></th>
			<td>
				<input name="ScoreRender[LILYPOND_BIN]" class="code" type="text" id="lilypond_bin" value="<?php echo attribute_escape($scorerender_options['LILYPOND_BIN']); ?>" size="50" />
			</td>
		</tr>
		</table>
	</fieldset>

	<!-- mup options -->
	<fieldset class="options">
		<legend><?php _e('Mup options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<label for="mup_content">
				<input type="checkbox" name="ScoreRender[MUP_CONTENT_ENABLED]" id="mup_content" value="1" <?php checked('1', $scorerender_options['MUP_CONTENT_ENABLED']); ?> /> <?php _e('Enable parsing for posts and pages'); ?></label><br />
				<label for="mup_comments">
				<input type="checkbox" name="ScoreRender[MUP_COMMENT_ENABLED]" value="1" <?php checked('1', $scorerender_options['MUP_COMMENT_ENABLED']); ?> /> <?php printf ('%s %s', __('Enable parsing for comments'), __('(<span style="font-weight: bold; color: red;">Warning:</span> security concern for exploiting weakness in binaries.)')); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>

				<?php _e('Start:') ?> <input name="ScoreRender[MUP_MARKUP_START]" class="code" type="text" id="mup_markup_start" value="<?php echo attribute_escape($scorerender_options['MUP_MARKUP_START']); ?>" size="14" />
				<?php _e('End:') ?> <input name="ScoreRender[MUP_MARKUP_END]" class="code" type="text" id="mup_markup_end" value="<?php echo attribute_escape($scorerender_options['MUP_MARKUP_END']); ?>" size="14" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_BIN]" class="code" type="text" id="mup_bin" value="<?php echo attribute_escape($scorerender_options['MUP_BIN']); ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s magic file:'), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_MAGIC_FILE]" class="code" type="text" id="mup_magic_file" value="<?php echo attribute_escape($scorerender_options['MUP_MAGIC_FILE']); ?>" size="50" />
				<br />
				<?php printf (__('Leave it empty if you have not <a href="%s">registered</a> Mup. This file must be readable by the user account running web server.'), 'http://www.arkkra.com/doc/faq.html#payment'); ?>
			</td>
		</tr>
		</table>
	</fieldset>

	<!-- guido options -->
	<fieldset class="options">
		<legend><?php _e('GUIDO NoteServer options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<label for="guido_content">
				<input type="checkbox" name="ScoreRender[GUIDO_CONTENT_ENABLED]" id="guido_content" value="1" <?php checked('1', $scorerender_options['GUIDO_CONTENT_ENABLED']); ?> /> <?php _e('Enable parsing for posts and pages'); ?></label><br />
				<label for="guido_comments">
				<input type="checkbox" name="ScoreRender[GUIDO_COMMENT_ENABLED]" id="guido_comment" value="1" <?php checked('1', $scorerender_options['GUIDO_COMMENT_ENABLED']); ?> /> <?php _e('Enable parsing for comments'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>
				<?php _e('Start:') ?> <input name="ScoreRender[GUIDO_MARKUP_START]" class="code" type="text" id="guido_markup_start" value="<?php echo attribute_escape($scorerender_options['GUIDO_MARKUP_START']); ?>" size="14" />
				<?php _e('End:') ?> <input name="ScoreRender[GUIDO_MARKUP_END]" class="code" type="text" id="guido_markup_end" value="<?php echo attribute_escape($scorerender_options['GUIDO_MARKUP_END']); ?>" size="14" />
			</td>
		</tr>
		</table>
	</fieldset>

	<!-- ABC options -->
	<fieldset class="options">
		<legend><?php _e('ABC options') ?></legend>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform">
		<tr valign="top">
			<th scope="row"><?php _e('Content conversion:') ?></th>
			<td>
				<label for="abc_content">
				<input type="checkbox" name="ScoreRender[ABC_CONTENT_ENABLED]" id="abc_content" value="1" <?php checked('1', $scorerender_options['ABC_CONTENT_ENABLED']); ?> /> <?php _e('Enable parsing for posts and pages'); ?></label><br />
				<label for="abc_comments">
				<input type="checkbox" name="ScoreRender[ABC_COMMENT_ENABLED]" id="abc_comment" value="1" <?php checked('1', $scorerender_options['ABC_COMMENT_ENABLED']); ?> /> <?php printf ('%s %s', __('Enable parsing for comments'), __('(<span style="font-weight: bold; color: red;">Warning:</span> security concern for exploiting weakness in binaries.)')); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>
				<?php _e('Start:') ?> <input name="ScoreRender[ABC_MARKUP_START]" class="code" type="text" id="abc_markup_start" value="<?php echo attribute_escape($scorerender_options['ABC_MARKUP_START']); ?>" size="14" />
				<?php _e('End:') ?> <input name="ScoreRender[ABC_MARKUP_END]" class="code" type="text" id="abc_markup_end" value="<?php echo attribute_escape($scorerender_options['ABC_MARKUP_END']); ?>" size="14" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:'), '<code>abcm2ps</code>'); ?></th>
			<td>
				<input name="ScoreRender[ABCM2PS_BIN]" class="code" type="text" id="abcm2ps_bin" value="<?php echo attribute_escape($scorerender_options['ABCM2PS_BIN']); ?>" size="50" />
				<br />
				<?php printf (__('%s is HIGHLY recommended. %s works for simple melodies, but not for multiple voices inside single staff.'), '<code>abcm2ps</code>', '<code>abc2ps</code>'); ?>
			</td>
		</tr>
		</table>
	</fieldset>

	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Update Options') ?> &raquo;" />
	</p>

	</div>
	</form>
	<?php
}

function scorerender_admin()
{
	add_options_page ('ScoreRender options',
	                  'ScoreRender', 9, __FILE__,
	                  'scorerender_admin_options');
}

scorerender_get_options ();

// Remove tag balancing filter
// There seems to be an bug in the balanceTags function of wp-includes/functions-formatting.php
// which means that strings containing ">>" (part of the LilyPond syntax for parallel music)
// are converted to "> >"  causing syntax errors.
remove_filter ('content_save_pre', 'balanceTags', 50);
remove_filter ('excerpt_save_pre', 'balanceTags', 50);
remove_filter ('comment_save_pre', 'balanceTags', 50);
remove_filter ('pre_comment_content', 'balanceTags', 30);

// earlier than default priority, since smilies conversion
// and wptexturize() can mess up the content
add_filter ('the_title', 'scorerender_content', 5);
add_filter ('the_excerpt', 'scorerender_content', 5);
add_filter ('the_content', 'scorerender_content', 5);
add_filter ('comment_text', 'scorerender_comment', 5);
add_action ('admin_menu', 'scorerender_admin');

?>
