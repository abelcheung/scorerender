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
 * Implements rendering of ABC notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting ABC notation.
 * @package ScoreRender
*/
class abcRender extends ScoreRender
{

private $width;

/**
 * Set maximum width of generated images
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.2.50
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);

	// Seems abcm2ps is using something like 120 dpi,
	// with 72DPI the notes and letters are very thin :(
	$this->width = $this->img_max_width / 120;
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
	$header = <<<EOT
%abc
%%staffwidth {$this->width}in
%%stretchlast no
%%leftmargin 0.2in
%abc2mtex: yes
EOT;
	// input must not contain any empty line
	return $header . "\n" . preg_replace ('/^$/m', '%', $this->_input);
}

/**
 * Render raw input file into PostScript file.
 *
 * @uses ScoreRender::_exec
 * @param string $input_file File name of raw input file containing music content
 * @param string $intermediate_image File name of rendered PostScript file
 * @return boolean Whether rendering is successful or not
 * @access protected
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	$cmd = sprintf ('%s %s -O %s 2>&1',
			$this->mainprog,
			$input_file, $intermediate_image);
	$retval = $this->_exec($cmd);

	return ($result['return_val'] == 0);
}

/**
 * @uses ScoreRender::conversion_step2
 * @param string $intermediate_image The rendered PostScript file name
 * @param string $final_image The final PNG image file name
 * @return boolean Whether conversion from PostScript to PNG is successful
 * @access protected
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	return parent::conversion_step2 ($intermediate_image, $final_image, TRUE, '-density 96');
}

/**
 * Check if given program is abcm2ps, and whether it is usable.
 *
 * @param string $prog The program to be checked.
 * @return boolean Return true if the given program is abcm2ps AND it is executable.
 */
public function is_notation_usable ($args = '')
{
	wp_parse_str ($args, $r);
	extract ($r, EXTR_SKIP);
	return parent::is_prog_usable ('abcm2ps', $prog, '-V');
}

} // end of class

?>
