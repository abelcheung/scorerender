<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
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
 * Implements rendering of Mup notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Mup notation.
 * @package ScoreRender
*/
class mupRender extends ScoreRender
{

private $width;

/**
 * @var string $magic_file Location of magic file used by Mup
 * @access private
 */
private $magic_file;

/**
 * Set maximum width of generated images
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.2.50
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);
	$this->width = $this->img_max_width / DPI;
}

/**
 * Set the location of magic file
 *
 * @param string $file Full path of magic file
 * @since 0.2.50
 */
public function set_magic_file ($file)
{
	$this->magic_file = $file;
}

/**
 * Checks if given content is invalid or dangerous content
 *
 * @param string $input
 * @return boolean True if content is deemed safe
 */
protected function is_valid_input ()
{
	$blacklist = array
	(
		'/^\s*\binclude\b/', '/^\s*\bfontfile\b/'
	);

	foreach ($blacklist as $pattern)
		if (preg_match ($pattern, $this->_input))
			return false;

	return true;
}

/**
 * Outputs complete music input file for rendering.
 *
 * Most usually user supplied content does not contain correct
 * rendering options like page margin, staff width etc, and
 * each notation has its own requirements. This method adds
 * such necessary content to original content for processing.
 *
 * @return string The full music content to be rendered
 */
public function get_music_fragment ()
{
	$header = <<<EOD
//!Mup-Arkkra-5.0
score
leftmargin = 0
rightmargin = 0
topmargin = 0
bottommargin = 0
pagewidth = {$this->width}
label = ""
EOD;
	return $header . "\n" . $this->_input;
}

/**
 * Execute the real command for first time rendering
 *
 * The command reads input content (after prepending and appending
 * necessary stuff to user supplied content), and converts it to
 * a PostScript file.
 *
 * @uses ScoreRender::_exec
 * @param string $input_file File name of raw input file containing music content
 * @param string $intermediate_image File name of rendered PostScript file
 * @return boolean Whether rendering is successful or not
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	/* Mup requires a file ".mup" present in $HOME or
	   current working directory. It must be present even if
	   not registered, otherwise mup refuse to render anything.
	   Even worse, the exist status in this case is 0, so
	   _exec succeeds yet no postscript is rendered. */

	$temp_magic_file = $this->temp_dir . DIRECTORY_SEPARATOR . '.mup';
	if (!file_exists($temp_magic_file))
	{
		if (is_readable($this->magic_file))
			copy($this->magic_file, $temp_magic_file);
		else
			touch ($temp_magic_file);
	}

	/* mup forces this kind of crap */
	putenv ("HOME=" . $this->temp_dir);

	$cmd = sprintf ('"%s" -f "%s" "%s"',
			$this->mainprog,
			$intermediate_image, $input_file);
	$retval = $this->_exec($cmd);

	unlink ($temp_magic_file);

	return (filesize ($intermediate_image) != 0);
	//return ($result['return_val'] == 0);
}

/**
 * Check if given program is Mup, and whether it is usable.
 *
 * @param string $prog The program to be checked.
 * @return boolean Return true if the given program is Mup AND it is executable.
 */
public function is_notation_usable ($args = '')
{
	wp_parse_str ($args, $r);
	extract ($r, EXTR_SKIP);
	return parent::is_prog_usable ('Arkkra Enterprises', $prog, '-v');
}

}  // end of class

?>
