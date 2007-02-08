<?php
/*
Plugin Name: FigureRender
Plugin URI: http://chris-lamb.co.uk/code/figurerender/
Description: Renders inline LaTeX, Lilypond and Mup figures in posts and comments. 
Author: Chris Lamb
Version: 1.0
Author URI: http://chris-lamb.co.uk/
*/

/*
 FigureRender - Renders inline LaTeX, LilyPond and Mup figures in WordPress
 Copyright (C) 2006 Chris Lamb <chris@chris-lamb.co.uk>
 http://www.chris-lamb.co.uk/code/figurerender/
 
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
 wp-figurerender.php
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Entry point for WordPress.
*/

// Error constants
define('ERR_INVALID_INPUT', 1);
define('ERR_CACHE_DIRECTORY_NOT_WRITABLE', 2);
define('ERR_TEMP_DIRECTORY_NOT_WRITABLE', 4);
define('ERR_TEMP_FILE_NOT_WRITABLE', 8);
define('ERR_IMAGE_CONVERT_FAILURE', 16);
define('ERR_RENDERING_ERROR', 32);

require_once('class.figurerender.inc.php');
require_once('class.latexrender.inc.php');
require_once('class.lilypondrender.inc.php');
require_once('class.muprender.inc.php');

// Configure default options
add_option('figurerender_temp_dir', '/tmp');
add_option('figurerender_convert_bin', '/usr/bin/convert');
add_option('figurerender_cache_dir', ABSPATH . get_option('upload_path'));
add_option('figurerender_cache_url', get_option('siteurl') . '/' . get_option('upload_path'));
add_option('figurerender_invert_image', 0);
add_option('figurerender_transparent_image', 0);
add_option('figurerender_show_input', 1);

add_option('figurerender_latex_markup_start', '[math]');
add_option('figurerender_latex_markup_end', '[/math]');
add_option('figurerender_lilypond_markup_start', '[lilypond]');
add_option('figurerender_lilypond_markup_end', '[/lilypond]');
add_option('figurerender_mup_markup_start', '[mup]');
add_option('figurerender_mup_markup_end', '[/mup]');

add_option('figurerender_latex_content', '1');
add_option('figurerender_latex_comments', '0');
add_option('figurerender_latex_bin', '/usr/bin/latex');
add_option('figurerender_dvips_bin', '/usr/bin/dvips');
add_option('figurerender_lilypond_content', '1');
add_option('figurerender_lilypond_comments', '0');
add_option('figurerender_lilypond_bin', '/usr/bin/lilypond');
add_option('figurerender_mup_content', '1');
add_option('figurerender_mup_comments', '0');
add_option('figurerender_mup_bin', '/usr/local/bin/mup');
add_option('figurerender_mup_magic_file', '');

// Remove trailing forward slashes
update_option('figurerender_temp_dir', preg_replace('#/+$#', '', get_option('figurerender_temp_dir')));
update_option('figurerender_cache_dir', preg_replace('#/+$#', '', get_option('figurerender_cache_dir')));
update_option('figurerender_cache_url', preg_replace('#/+$#', '', get_option('figurerender_cache_url')));


function parse_input($input) {
	$input = html_entity_decode($input);
	
	/*
	$input = strtr(
		$input,
		array(
			'&#8217;'	=> '\'',
			'&#8220;'	=> '"',
			'&#8221;'	=> '"',
			'&#8243;'	=> '"',
			'<br />'	=> '',
			'<p>'	=> '',
			'</p>'	=> '',
			'&#038;'	=> '&',
			'&#8230;'	=> '...'
		)
	);
	 */
	
	$input = trim($input);
	
	return $input;
}


function figurerender_generate_html_error($msg) {
	return "<p><b><a href=\"http://www.chris-lamb.co.uk/code/figurerender/\">FigureRender</a> Error:</b> <i>" . $msg . '</i></p>';
}

function figurerender_process_result($result, $input, $render) {
	switch ($result) {
		case ERR_INVALID_INPUT:
			return figurerender_generate_html_error(__('Invalid input') . '<br/><pre>' . $input) . '</pre>';
		case ERR_CACHE_DIRECTORY_NOT_WRITABLE:
			return figurerender_generate_html_error(__('Cache directory not writable!'));
		case ERR_TEMP_DIRECTORY_NOT_WRITABLE:
			return figurerender_generate_html_error(__('Temporary directory not writable!'));
		case ERR_TEMP_FILE_NOT_WRITABLE:
			return figurerender_generate_html_error(__('Temporary file not writable!'));
		case ERR_IMAGE_CONVERT_FAILURE:
			return figurerender_generate_html_error(__('Image convert failure!'));
		case ERR_RENDERING_ERROR:
			return figurerender_generate_html_error(__('The external rendering application did not complete successfully.') . '<br /><textarea cols=80 rows=10 READONLY>' . $render->getPreviousOutput() . '</textarea>');
	}
	
	// No errors, so generate HTML
	$html = '<img style="vertical-align: bottom" ';
	
	if (get_option('figurerender_show_input')) {
		$html .= 'alt="Input: ' . htmlentities($input) . '" ';
	} else {
		$html .= 'alt="Figure" ';
	}
	
	$html .= 'src="' . get_option('figurerender_cache_url') . '/' . $result . '" />';
	
	return $html;
}

function figurerender_latex($matches) {
	$input = parse_input($matches[1]);
	
	$render = new LatexRender(
		$input,
		array(
			'TEMP_DIR'     => get_option('figurerender_temp_dir'),
			'CONVERT_BIN'  => get_option('figurerender_convert_bin'),
			'CACHE_DIR'    => get_option('figurerender_cache_dir'),
			'LATEX_BIN'    => get_option('figurerender_latex_bin'),
			'DVIPS_BIN'    => get_option('figurerender_dvips_bin'),
			'INVERT_IMAGE' => get_option('figurerender_invert_image'),
			'TRANSPARENT'  => get_option('figurerender_transparent_image')
		)
	);
	
	$result = $render->render();
	
	return figurerender_process_result($result, $input, $render);
}


function figurerender_lilypond($matches) {
	$input = parse_input($matches[1]);
	
	$render = new LilypondRender(
		$input,
		array(
			'TEMP_DIR'     => get_option('figurerender_temp_dir'),
			'CONVERT_BIN'  => get_option('figurerender_convert_bin'),
			'CACHE_DIR'    => get_option('figurerender_cache_dir'),
			'LILYPOND_BIN' => get_option('figurerender_lilypond_bin'),
			'INVERT_IMAGE' => get_option('figurerender_invert_image'),
			'TRANSPARENT'  => get_option('figurerender_transparent_image')
		)
	);
	
	$result = $render->render();
	
	return figurerender_process_result($result, $input, $render);
}

function figurerender_mup($matches) {
	$input = parse_input($matches[1]);
	
	$render = new MupRender(
		$input,
		array(
			'TEMP_DIR'       => get_option('figurerender_temp_dir'),
			'CONVERT_BIN'    => get_option('figurerender_convert_bin'),
			'CACHE_DIR'      => get_option('figurerender_cache_dir'),
			'MUP_BIN'        => get_option('figurerender_mup_bin'),
			'MUP_MAGIC_FILE' => get_option('figurerender_mup_magic_file'),
			'INVERT_IMAGE'   => get_option('figurerender_invert_image'),
			'TRANSPARENT'    => get_option('figurerender_transparent_image')
		)
	);
	
	$result = $render->render();
	
	return figurerender_process_result($result, $input, $render);
}

function figurerender_content($content) {
	$a = array(
		'[' => '\[',
		']' => '\]',
		'/' => '\/',
	);
	
	if (get_option('figurerender_latex_content')) {
		$search = '/' . strtr(get_option('figurerender_latex_markup_start'), $a) .
			'([[:print:]|[:space:]]*?)' .
			strtr(get_option('figurerender_latex_markup_end'), $a) .'/i';
		$content = preg_replace_callback($search, 'figurerender_latex', $content);
	}
	
	if (get_option('figurerender_lilypond_content')) {
		$search = '/' . strtr(get_option('figurerender_lilypond_markup_start'), $a) .
			'([[:print:]|[:space:]]*?)' .
			strtr(get_option('figurerender_lilypond_markup_end'), $a) .'/i';
		$content = preg_replace_callback($search, 'figurerender_lilypond', $content);
	}
	
	if (get_option('figurerender_mup_content')) {
		$search = '/' . strtr(get_option('figurerender_mup_markup_start'), $a) .
			'([[:print:]|[:space:]]*?)' .
			strtr(get_option('figurerender_mup_markup_end'), $a) .'/i';
		$content = preg_replace_callback($search, 'figurerender_mup', $content);
	}
	
	return $content;
}

function figurerender_comment($content) {
	$a = array(
		'[' => '\[',
		']' => '\]',
		'/' => '\/',
	);
	
	if (get_option('figurerender_latex_comment')) {
		$search = '/' . strtr(get_option('figurerender_latex_markup_start'), $a) .
			'([[:print:]|[:space:]]*?)' .
			strtr(get_option('figurerender_latex_markup_end'), $a) .'/i';
		$content = preg_replace_callback($search, 'figurerender_latex', $content);
	}
	
	if (get_option('figurerender_lilypond_comment')) {
		$search = '/' . strtr(get_option('figurerender_lilypond_markup_start'), $a) .
			'([[:print:]|[:space:]]*?)' .
			strtr(get_option('figurerender_lilypond_markup_end'), $a) .'/i';
		$content = preg_replace_callback($search, 'figurerender_lilypond', $content);
	}
	
	if (get_option('figurerender_mup_comment')) {
		$search = '/' . strtr(get_option('figurerender_mup_markup_start'), $a) .
			'([[:print:]|[:space:]]*?)' .
			strtr(get_option('figurerender_mup_markup_end'), $a) .'/i';
		$content = preg_replace_callback($search, 'figurerender_mup', $content);
	}

	return $content;
}

function figurerender_admin_options() {
	?>

	<form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . __FILE__ ?>">
	
	<div class="wrap">
	<h2><?php _e('FigureRender options') ?></h2>
	
	<p><?php _e('FigureRender renders inline <a target="_new" href="http://www.latex.org/">LaTeX</a>, <a target="_new" href="http://www.lilypond.org/">Lilypond</a> and <a target="_new" href="http://www.arkkra.com/">Mup</a> figures in posts and comments.</p>') ?></p>
	
	<fieldset class="options">
		<legend><?php _e('General options') ?></legend>
		
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="top">
			<th scope="row"><?php _e('Temporary directory:') ?></th>
			<td>
				<input name="figurerender_temp_dir" class="code" type="text" id="figurerender_temp_dir" value="<?php form_option('figurerender_temp_dir'); ?>" size="60" /><br />
				<?php _e('Must be writable and ideally located outside the web-accessible area.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Cache directory:') ?></th>
			<td>
				<input name="figurerender_cache_dir" class="code" type="text" id="figurerender_cache_dir" value="<?php form_option('figurerender_cache_dir'); ?>" size="60" /><br />
				<?php _e('Must be writable and located inside the web-accessible area.') ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Cache URL:') ?></th>
			<td>
				<input name="figurerender_cache_url" class="code" type="text" id="figurerender_cache_url" value="<?php form_option('figurerender_cache_url'); ?>" size="60" /><br />
				<?php _e('Must correspond with the specified cache directory.') ?>
			</td>
		</tr>
		</table>
	</fieldset>
	
	<fieldset class="options">
		<legend><?php _e('Image options') ?></legend>
		
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="top">
			<th scope="row"><?php _e('Show figure source in &lt;IMG&gt; ALT tag?') ?></th>
			<td>
				<label for="figurerender_show_input">
				<input type="checkbox" name="figurerender_show_input" id="figurerender_show_input" value="1" <?php checked('1', get_settings('figurerender_show_input')); ?> /></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Invert image colours?') ?></th>
			<td>
				<label for="figurerender_invert_image">
				<input type="checkbox" name="figurerender_invert_image" id="figurerender_invert_image" value="1" <?php checked('1', get_settings('figurerender_invert_image')); ?> /></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Use transparent background?') ?></th>
			<td>
				<label for="figurerender_transparent_image">
				<input type="checkbox" name="figurerender_transparent_image" id="figurerender_transparent_image" value="1" <?php checked('1', get_settings('figurerender_transparent_image')); ?> /></label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Location of <a target="_new" href="http://www.imagemagick.net/">ImageMagick</a>\'s <i>convert</i> binary:') ?></th>
			<td>
				<input name="figurerender_convert_bin" class="code" type="text" id="figurerender_convert_bin" value="<?php form_option('figurerender_convert_bin'); ?>" size="40" />
			</td>
		</tr>
		</table>
	</fieldset>
	
	<fieldset class="options">
		<legend><?php _e('LaTeX Configuration') ?></legend>
		
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="top">
			<th scope="row"><?php _e('Enable parsing for:') ?></th>
			<td>
				<label for="figurerender_latex_content">
				<input type="checkbox" name="figurerender_latex_content" id="figurerender_latex_content" value="1" <?php checked('1', get_settings('figurerender_latex_content')); ?> /> Posts and pages</label><br />
				<label for="figurerender_latex_comments"><input type="checkbox" name="figurerender_latex_comments" value="1" <?php checked('1', get_settings('figurerender_latex_comments')); ?> /> Comments</label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>
			
				<?php _e('Start:') ?> <input name="figurerender_latex_markup_start" class="code" type="text" id="figurerender_latex_markup_start" value="<?php form_option('figurerender_latex_markup_start'); ?>" size="14" /> <?php _e('End:') ?> <input name="figurerender_latex_markup_end" class="code" type="text" id="figurerender_latex_markup_end" value="<?php form_option('figurerender_latex_markup_end'); ?>" size="14" />					
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Location of <i>latex</i> binary:') ?></th>
			<td>
				<input name="figurerender_latex_bin" class="code" type="text" id="figurerender_latex_bin" value="<?php form_option('figurerender_latex_bin'); ?>" size="50" />
			</td>
		</tr> 
		<tr valign="top">
			<th scope="row"><?php _e('Location of <i>dvips</i> binary:') ?></th>
			<td>
				<input name="figurerender_dvips_bin" class="code" type="text" id="figurerender_dvips_bin" value="<?php form_option('figurerender_dvips_bin'); ?>" size="50" />
			</td>
		</tr> 
		</table>
	</fieldset>
	
	<fieldset class="options">
		<legend><?php _e('Lilypond Configuration') ?></legend>
		
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="top">
			<th scope="row"><?php _e('Enable parsing for:') ?></th>
			<td>
				<label for="figurerender_lilypond_content">
				<input type="checkbox" name="figurerender_lilypond_content" id="figurerender_lilypond_content" value="1" <?php checked('1', get_settings('figurerender_lilypond_content')); ?> /> Posts and pages</label><br />
				<label for="figurerender_lilypond_comments"><input type="checkbox" name="figurerender_lilypond_comments" value="1" <?php checked('1', get_settings('figurerender_lilypond_comments')); ?> /> Comments</label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>
			
				<?php _e('Start:') ?> <input name="figurerender_lilypond_markup_start" class="code" type="text" id="figurerender_lilypond_markup_start" value="<?php form_option('figurerender_lilypond_markup_start'); ?>" size="14" /> <?php _e('End:') ?> <input name="figurerender_lilypond_markup_end" class="code" type="text" id="figurerender_lilypond_markup_end" value="<?php form_option('figurerender_lilypond_markup_end'); ?>" size="14" />					
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Location of <i>lilypond</i> binary:') ?></th>
			<td>
				<input name="figurerender_lilypond_bin" class="code" type="text" id="figurerender_lilypond_bin" value="<?php form_option('figurerender_lilypond_bin'); ?>" size="50" />
			</td>
		</tr> 
		</table>
	</fieldset>

	<fieldset class="options">
		<legend><?php _e('Mup Configuration') ?></legend>
		
		<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="top">
			<th scope="row"><?php _e('Enable parsing for:') ?></th>
			<td>
				<label for="figurerender_mup_content">
				<input type="checkbox" name="figurerender_mup_content" id="figurerender_mup_content" value="1" <?php checked('1', get_settings('figurerender_mup_content')); ?> /> Posts and pages</label><br />
				<label for="figurerender_mup_comments"><input type="checkbox" name="figurerender_mup_comments" value="1" <?php checked('1', get_settings('figurerender_mup_comments')); ?> /> Comments</label>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Tag markup:') ?></th>
			<td>
			
				<?php _e('Start:') ?> <input name="figurerender_mup_markup_start" class="code" type="text" id="figurerender_mup_markup_start" value="<?php form_option('figurerender_mup_markup_start'); ?>" size="14" /> <?php _e('End:') ?> <input name="figurerender_mup_markup_end" class="code" type="text" id="figurerender_mup_markup_end" value="<?php form_option('figurerender_mup_markup_end'); ?>" size="14" />					
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e('Location of <i>mup</i> binary:') ?></th>
			<td>
				<input name="figurerender_mup_bin" class="code" type="text" id="figurerender_mup_bin" value="<?php form_option('figurerender_mup_bin'); ?>" size="50" />
			</td>
		</tr> 
		<tr valign="top">
			<th scope="row"><?php _e('Location of <i>mup</i> magic file:') ?></th>
			<td>
				<input name="figurerender_mup_magic_file" class="code" type="text" id="figurerender_mup_magic_file" value="<?php form_option('figurerender_mup_magic_file'); ?>" size="50" />
				<br />
				<?php printf (__('Leave it empty if you have not <a href="%s">registered</a> Mup'), 'http://www.arkkra.com/doc/faq.html#payment'); ?>
			</td>
		</tr> 
		</table>
	</fieldset>

	<p class="submit">
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="figurerender_temp_dir,figurerender_convert_bin,figurerender_cache_enabled,figurerender_cache_dir,figurerender_cache_url,figurerender_latex_content,figurerender_latex_comments,figurerender_latex_bin,figurerender_dvips_bin,figurerender_lilypond_content,figurerender_lilypond_comments,figurerender_lilypond_bin,figurerender_mup_content,figurerender_mup_comments,figurerender_mup_bin,figurerender_mup_magic_file,figurerender_invert_image,figurerender_transparent_image,figurerender_show_input,figurerender_latex_markup_start,figurerender_latex_markup_end,figurerender_lilypond_markup_start,figurerender_lilypond_markup_end,figurerender_mup_markup_start,figurerender_mup_markup_end" />
	<input type="submit" name="Submit" value="<?php _e('Update Options') ?> &raquo;" />
	</p>
	
	</div>
	</form>
	<?php
}

function figurerender_admin() {
	add_options_page('FigureRender Configuration', 'FigureRender', 9, __FILE__, 'figurerender_admin_options');
}


// Remove tag balancing filter
// There seems to be an bug in the balanceTags function of wp-includes/functions-formatting.php
// which means that strings containing ">>" (part of the LilyPond syntax for parallel music)
// are converted to "> >"  causing syntax errors.
remove_filter('content_save_pre', 'balanceTags', 50);
remove_filter('excerpt_save_pre', 'balanceTags', 50);
remove_filter('comment_save_pre', 'balanceTags', 50);
remove_filter('pre_comment_content', 'balanceTags', 30);

// earlier than default priority, since smilies conversion
// and wptexturize() can mess up the content
add_filter('the_content', 'figurerender_content', 6);
add_filter('comment_text', 'figurerender_comment', 6);
add_action('admin_menu', 'figurerender_admin');

?>
