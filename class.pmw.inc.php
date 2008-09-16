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
 * Implements rendering of Philip's Music Writer notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Philip's Music Writer notation.
 * @package ScoreRender
*/
class pmwRender extends ScoreRender
{

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
		// If page size is changed here, it must also be changed
		// under conversion_step2().
		$header = <<<EOD
Sheetsize A3
Linelength {$this->img_max_width}
Magnification 1.5

EOD;
		// PMW doesn't like \n\r
		return str_replace ("\r", '', $header . $this->_input);
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
		$cmd = sprintf ('%s -includefont -o %s %s 2>&1',
		                $this->mainprog,
		                $intermediate_image, $input_file);
		$retval = $this->_exec($cmd);

		return ($result['return_val'] == 0);
	}

	/**
	 * @param string $intermediate_image The rendered PostScript file name
	 * @param string $final_image The final PNG image file name
	 * @return boolean Whether conversion from PostScript to PNG is successful
	 * @access protected
	 */
	protected function conversion_step2 ($intermediate_image, $final_image)
	{
		// ImageMagick mistakenly identify all PostScript produced by PMW as
		// having Letter (8.5"x11") size! Braindead.
		return parent::conversion_step2 ($intermediate_image, $final_image, TRUE,
			'-page a3');
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
		return parent::is_prog_usable ('PMW version', $prog, '-V');
	}
}

?>
