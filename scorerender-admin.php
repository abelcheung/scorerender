<?php

/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.3.3
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
 */

/**
 * Calculate number of fragments contained inside a blog post
 *
 * The returned numbers are mainly used for showing info in WordPress Dashboard
 *
 * @since 0.2
 * @uses $notations Content is compared to regex specified in $notations
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
 * Returns number of cached images inside cache directory
 *
 * @since 0.2
 * @return integer number of images inside cache directory, or -1 if cache dir can't be read
 */
function scorerender_get_num_of_images ()
{
	global $sr_options;

	if (!is_dir ($sr_options['CACHE_DIR']) || !is_readable ($sr_options['CACHE_DIR']))
		return -1;

	$count = 0;
	if ($handle = opendir ($sr_options['CACHE_DIR']))
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
 * Display info in WordPress Dashboard
 *
 * @since 0.2
 * @uses scorerender_get_fragment_count()
 * @uses scorerender_get_num_of_images()
 * @uses $notations Regex in $notations is used to compose SQL statement
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
 * Remove all cached images in cache directory
 *
 * @since 0.2
 */
function scorerender_remove_cache ()
{
	global $sr_options;

	// extra guard, doesn't hurt
	if (!is_dir      ($sr_options['CACHE_DIR']) ||
	    !is_readable ($sr_options['CACHE_DIR']) ||
	    !is_writable ($sr_options['CACHE_DIR']))
		return;

	if ($handle = opendir ($sr_options['CACHE_DIR']))
	{
		while (false !== ($file = readdir ($handle)))
		{
			// FIXME: how to decide if some image is generated by plugin?
			if (preg_match (REGEX_CACHE_IMAGE, $file))
				unlink ($sr_options['CACHE_DIR'] . DIRECTORY_SEPARATOR . $file);
		}
		closedir ($handle);
	}
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
 * @param string $path Cache full path
 * @param string $url Cache URL
 * @return boolean Whether both point to the same location.
 */
function scorerender_cache_location_match ($path, $url)
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
 */
function scorerender_update_options ()
{
	if ( function_exists ('current_user_can') && !current_user_can ('manage_options') )
		wp_die (__('Cheatin&#8217; uh?', TEXTDOMAIN));

	global $sr_options;

	$newopt = (array) $_POST['ScoreRender'];
	transform_paths ($newopt, TRUE);
	$errmsgs = array ();

	$messages = array
	(
		'temp_dir_not_writable'    => array ('level' => MSG_WARNING, 'content' => __('Temporary directory is NOT writable! Will fall back to system default setting.')),
		'cache_dir_undefined'      => array ('level' => MSG_FATAL  , 'content' => __('Cache directory is NOT defined! Image can not be placed inside appropriate directory. The plugin will stop working.', TEXTDOMAIN)),
		'cache_dir_not_writable'   => array ('level' => MSG_FATAL  , 'content' => __('Cache directory is NOT writable! Image can not be placed inside appropriate directory. The plugin will stop working.', TEXTDOMAIN)),
		'cache_url_undefined'      => array ('level' => MSG_FATAL  , 'content' => __('Cache URL is NOT defined! The plugin will stop working.', TEXTDOMAIN)),
		'cache_dir_url_unmatch'    => array ('level' => MSG_WARNING, 'content' => __('Cache directory and URL probably do not correspond to the same location.', TEXTDOMAIN)),
		'wrong_content_length'     => array ('level' => MSG_WARNING, 'content' => __('Content length is not a non-negative integer. Value discarded.', TEXTDOMAIN)),
		'wrong_frag_per_comment'   => array ('level' => MSG_WARNING, 'content' => __('Fragment per comment is not a non-negative integer. Value discarded.', TEXTDOMAIN)),
		'wrong_image_max_width'    => array ('level' => MSG_WARNING, 'content' => __('Image maximum width must be positive integer >= 72. Value discarded.', TEXTDOMAIN)),
		'convert_bin_problem'      => array ('level' => MSG_FATAL  , 'content' => __('<tt>convert</tt> program is NOT defined or NOT executable! The plugin will stop working.', TEXTDOMAIN)),
		'abcm2ps_bin_problem'      => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>abcm2ps</tt>', 'ABC')),
		'lilypond_bin_problem'     => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>lilypond</tt>', 'LilyPond')),
		'mup_bin_problem'          => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>mup</tt>', 'Mup')),
		'pmw_bin_problem'          => array ('level' => MSG_WARNING, 'content' => sprintf (__('%s program does not look like a correct one. %s notation support will most likely stop working.', TEXTDOMAIN), '<tt>pmw</tt>', 'Philip\'s Music Writer')),
		'prog_check_disabled'      => array ('level' => MSG_WARNING, 'content' => sprintf (__('Some PHP functions are disabled due to security reasons. Program validation will not be done.', TEXTDOMAIN))),
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

	if ( ! in_array ('cache_dir_undefined'   , $errmsgs) &&
	     ! in_array ('cache_dir_not_writable', $errmsgs) &&
	     ! in_array ('cache_url_undefined'   , $errmsgs) )
	{
		if ( ! scorerender_cache_location_match ($newopt['CACHE_DIR'], $newopt['CACHE_URL']) )
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

	if ( !ctype_digit ($newopt['CONTENT_MAX_LENGTH']) )
	{
		$errmsgs[] = 'wrong_content_length';
		unset ($newopt['CONTENT_MAX_LENGTH']);
	}

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

	// FIXME: Anyway to do pluggable checking without access these methods directly?
	if ( ! empty ($newopt['LILYPOND_BIN']) &&
			! lilypondRender::is_notation_usable ('prog=' . $newopt['LILYPOND_BIN']) )
		$errmsgs[] = 'lilypond_bin_problem';

	if ( ! empty ($newopt['MUP_BIN']) &&
			! mupRender::is_notation_usable ('prog=' . $newopt['MUP_BIN']) )
		$errmsgs[] = 'mup_bin_problem';

	if ( ! empty ($newopt['ABCM2PS_BIN']) &&
			! abcRender::is_notation_usable ('prog=' . $newopt['ABCM2PS_BIN']) )
		$errmsgs[] = 'abcm2ps_bin_problem';

	if ( ! empty ($newopt['PMW_BIN']) &&
			! pmwRender::is_notation_usable ('prog=' . $newopt['PMW_BIN']) )
		$errmsgs[] = 'pmw_bin_problem';

	$sr_options = array_merge ($sr_options, $newopt);
	transform_paths ($sr_options, TRUE);
	update_option ('scorerender_options', $sr_options);
	transform_paths ($sr_options, FALSE);

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
		echo '<div id="message" class="updated fade"><p><strong>' .
			__('Options saved.', TEXTDOMAIN) . "</strong></p></div>\n";
}


/**
 * Section of admin page about path options
 *
 * @since 0.2
 * @access private
 */
function scorerender_admin_section_path ()
{
	global $sr_options;
?>
	<fieldset class="options">
		<h3><?php _e('Path options', TEXTDOMAIN) ?></h3>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<tr valign="top">
			<th scope="row"><?php _e('Temporary directory:', TEXTDOMAIN) ?></th>
			<td>
				<input name="ScoreRender[TEMP_DIR]" class="code" type="text" id="temp_dir" value="<?php echo $sr_options['TEMP_DIR']; ?>" size="60" /><br />
				<?php _e('Must be writable and ideally <strong>NOT</strong> accessible from web. System default will be used if left blank.', TEXTDOMAIN) ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache directory:', TEXTDOMAIN) ?></th>
			<td>
				<input name="ScoreRender[CACHE_DIR]" class="code" type="text" id="cache_dir" value="<?php echo $sr_options['CACHE_DIR']; ?>" size="60" /><br />
				<?php _e('Must be writable and accessible from web.', TEXTDOMAIN) ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Image cache URL:', TEXTDOMAIN) ?></th>
			<td>
				<input name="ScoreRender[CACHE_URL]" class="code" type="text" id="cache_url" value="<?php echo $sr_options['CACHE_URL']; ?>" size="60" /><br />
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
	global $sr_options;
?>
	<fieldset class="options">
		<h3><?php _e('Program and file locations', TEXTDOMAIN) ?></h3>
		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<caption><?php _e('ImageMagick 6.x <code>convert</code> must be present and working. For each kind of notation, leaving corresponding program location empty means disabling that notation support automatically, except GUIDO which does not use any program.', TEXTDOMAIN); ?></caption>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>convert</code>') ?></th>
			<td>
				<input name="ScoreRender[CONVERT_BIN]" class="code" type="text" id="convert_bin" value="<?php echo $sr_options['CONVERT_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>lilypond</code>'); ?></th>
			<td>
				<input name="ScoreRender[LILYPOND_BIN]" class="code" type="text" id="lilypond_bin" value="<?php echo $sr_options['LILYPOND_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_BIN]" class="code" type="text" id="mup_bin" value="<?php echo $sr_options['MUP_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s magic file:', TEXTDOMAIN), '<code>mup</code>'); ?></th>
			<td>
				<input name="ScoreRender[MUP_MAGIC_FILE]" class="code" type="text" id="mup_magic_file" value="<?php echo $sr_options['MUP_MAGIC_FILE']; ?>" size="50" />
				<br />
				<?php printf (__('Leave it empty if you have not <a href="%s">registered</a> Mup. This file must be readable by the user account running web server.', TEXTDOMAIN), 'http://www.arkkra.com/doc/faq.html#payment'); ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>abcm2ps</code>'); ?></th>
			<td>
				<input name="ScoreRender[ABCM2PS_BIN]" class="code" type="text" id="abcm2ps_bin" value="<?php echo $sr_options['ABCM2PS_BIN']; ?>" size="50" />
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php printf (__('Location of %s binary:', TEXTDOMAIN), '<code>pmw</code>'); ?></th>
			<td>
				<input name="ScoreRender[PMW_BIN]" class="code" type="text" id="pmw_bin" value="<?php echo $sr_options['PMW_BIN']; ?>" size="50" />
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
	global $sr_options;
?>
	<fieldset class="options">
		<h3><?php _e('Image options', TEXTDOMAIN) ?></h3>
		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<tr valign="top">
			<th scope="row"><?php _e('Max image width (pixel):', TEXTDOMAIN) ?></th>
			<td>
				<input type="text" name="ScoreRender[IMAGE_MAX_WIDTH]" id="image_max_width" value="<?php echo $sr_options['IMAGE_MAX_WIDTH']; ?>" size="6" />
				<label for="image_max_width"><?php _e('(Default is 360)', TEXTDOMAIN) ?></label><br /><?php _e('Note that this value is just an approximation, please allow for &#x00B1;10% difference. Some programs like lilypond would not use the full image width if passage is not long enough.', TEXTDOMAIN) ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th score="row"><?php _e('Image post-processing', TEXTDOMAIN) ?></th>
			<td>
				<p><input type="checkbox" name="ScoreRender[INVERT_IMAGE]" id="invert_image" value="1" <?php checked('1', $sr_options['INVERT_IMAGE']); ?> />
				<label for="invert_image"><?php _e('White colored notes (default is black)', TEXTDOMAIN); ?></label></p>
				<p><input type="checkbox" name="ScoreRender[TRANSPARENT_IMAGE]" id="transparent_image" value="1" <?php checked('1', $sr_options['TRANSPARENT_IMAGE']); ?> onclick="var box = document.getElementById('use_ie6_png_alpha_fix'); box.disabled = !box.disabled; return true;" />
				<label for="transparent_image"><?php _e('Use transparent background', TEXTDOMAIN); ?></label></p>
				<p style="padding-left: 30px;"><input type="checkbox" name="ScoreRender[USE_IE6_PNG_ALPHA_FIX]" id="use_ie6_png_alpha_fix" value="1" <?php checked('1', $sr_options['USE_IE6_PNG_ALPHA_FIX']); if (1 != $sr_options['TRANSPARENT_IMAGE']) { echo ' disabled="disabled"'; } ?> />
				<label for="use_ie6_png_alpha_fix"><?php _e('Enable fake translucent image in IE6', TEXTDOMAIN) ?></label><br /><small><?php printf ('Turning on this option enables <a href="%s">emulation of translucency in PNG images</a> in IE 5.5/6, which is not supported by IE below version 7. This option only affects images rendered by ScoreRender. <strong>Make sure you have NOT installed any plugin with the same functionality before turning on this option, which may conflict with each other.</strong>', 'http://www.twinhelix.com/css/iepngfix/' ); ?></small></p>
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
	global $sr_options;
?>
	<fieldset class="options">
		<h3><?php _e('Content options', TEXTDOMAIN) ?></h3>

		<table width="100%" cellspacing="2" cellpadding="5" class="optiontable editform form-table">
		<tr valign="top">
			<th score="row"><?php _e('Clickable image:', TEXTDOMAIN) ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[SHOW_SOURCE]" id="show_input" value="1" <?php checked('1', $sr_options['SHOW_SOURCE']); ?> />
				<label for="show_input"><?php _e('Show music source in new browser window/tab when image is clicked', TEXTDOMAIN); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Maximum length per fragment:', TEXTDOMAIN) ?></th>
			<td>
				<input type="text" name="ScoreRender[CONTENT_MAX_LENGTH]" id="content_max_length" value="<?php echo $sr_options['CONTENT_MAX_LENGTH']; ?>" size="6" />
				<label for="content_max_length"><?php _e('(0 means unlimited)', TEXTDOMAIN) ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('When rendering failed:', TEXTDOMAIN); ?></th>
			<td>
				<input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_message" value="1" <?php checked(ON_ERR_SHOW_MESSAGE, $sr_options['ERROR_HANDLING']); ?> />
				<label for="on_err_show_message"><?php _e('Show error message', TEXTDOMAIN) ?></label><br />
				<input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_fragment" value="2" <?php checked(ON_ERR_SHOW_FRAGMENT, $sr_options['ERROR_HANDLING']); ?> />
				<label for="on_err_show_fragment"><?php _e('Show original, unmodified music fragment', TEXTDOMAIN) ?></label><br />
				<input type="radio" name="ScoreRender[ERROR_HANDLING]" id="on_err_show_nothing" value="3" <?php checked(ON_ERR_SHOW_NOTHING, $sr_options['ERROR_HANDLING']); ?> />
				<label for="on_err_show_nothing"><?php _e('Show nothing', TEXTDOMAIN) ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Comment rendering:', TEXTDOMAIN) ?></th>
			<td>
				<input type="checkbox" name="ScoreRender[COMMENT_ENABLED]" id="comment_enabled" value="1" <?php checked('1', $sr_options['COMMENT_ENABLED']); ?> onclick="var box = document.getElementById('fragment_per_comment'); box.disabled = !box.disabled; return true;" />
				<label for="comment_enabled"><?php printf ('%s %s', __('Enable rendering for comments', TEXTDOMAIN), '<span style="font-weight: bold; color: red;">' . __('(Only turn on if commenters are trusted)', TEXTDOMAIN) . '</span>'); ?></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Maximum number of fragment per comment:', TEXTDOMAIN) ?></th>
			<td>
				<input type="text" name="ScoreRender[FRAGMENT_PER_COMMENT]" id="fragment_per_comment" value="<?php echo $sr_options['FRAGMENT_PER_COMMENT']; ?>" size="6" <?php if (1 != $sr_options['COMMENT_ENABLED']) { echo ' disabled="disabled"'; } ?> />
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
	global $sr_options;
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
<?php if ( is_writable ($sr_options['CACHE_DIR']) ) : ?>
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
 * {@link scorerender_remove_cache() scorerender_remove_cache()} or
 * {@link scorerender_update_options() scorerender_update_options()} correspondingly.
 *
 * @uses scorerender_remove_cache() Activated when 'Remove Cache' button is clicked
 * @uses scorerender_update_options() Activate when 'Update Options' button is clicked
 * @uses scorerender_admin_section_path() Admin page -- path options
 * @uses scorerender_admin_section_prog() Admin page -- program and file locations
 * @uses scorerender_admin_section_image() Admin page -- image options
 * @uses scorerender_admin_section_content() Admin page -- content options
 * @uses scorerender_admin_section_caching() Admin page -- caching administration
 *
 * @access private
 */
function scorerender_admin_options ()
{
	global $sr_options, $notations;

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
	<?php if ( function_exists ('screen_icon') ) screen_icon(); ?>
	<h2><?php _e('ScoreRender options', TEXTDOMAIN) ?></h2>

	<form method="post" action="" id="scorerender-conf">
	<?php wp_nonce_field ('scorerender-update-options') ?>

	<p><?php _e('The following notations are supported by ScoreRender, along with starting and ending tag after each notation name. Each music fragment must be enclosed by corresponding pair of tags.', TEXTDOMAIN); ?></p>
	<ul>
<?php	foreach ($notations as $tag => $notation_data) : ?>
	<li><a target="_blank" href="<?php echo $notation_data['url']; ?>"><?php echo $notation_data['name']; ?></a>
	(<code><?php echo $notation_data['starttag']; ?></code>, <code><?php echo $notation_data['endtag']; ?></code>)</li>
<?php	endforeach; ?>
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

	</form>
</div>
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


if ( 0 != get_option('use_balanceTags') )
{
	/**
	 * @ignore
	 */
	function sr_turn_off_balance_tags()
	{
		echo '<div id="balancetag-warning" class="updated" style="background-color: #ff6666"><p>'
			. sprintf (__('<strong>OPTION CONFLICT</strong>: The &#8216;correct invalidly nested XHTML automatically&#8217; option conflicts with ScoreRender plugin, because it will mangle certain Lilypond and Mup fragments. The option is available in <a href="%s">Writing option page</a>.', TEXTDOMAIN), "options-writing.php")
			. "</p></div>";
	}
	add_filter ('admin_notices', 'sr_turn_off_balance_tags');
}

add_filter ('activity_box_end', 'scorerender_activity_box');
add_filter ('admin_menu', 'scorerender_admin_menu');

?>
