<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
 Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
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

/*
 Mostly based on class.lilypondrender.inc.php from FigureRender
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006
*/

/**
 * Implements rendering of Lilypond notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Lilypond notation.
 * @package ScoreRender
*/
class lilypondRender extends ScoreRender
{
	/**
	 * Class constructor
	 * @param array $options Options to be passed into class
	 * @access private
	 */
	function lilypondRender ($options = array())
	{
		$this->init_options ($options);
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
	function get_input_content ()
	{
		$header = <<<EOD
\\version "2.8.1"
\\header {
	tagline= ""
}
\\paper {
	ragged-right = ##t
	indent = 0.0\\mm
	line-width = {$this->_options['IMAGE_MAX_WIDTH']}\\pt
}
\\layout {
	\\context {
		\\Score
		\\remove "Bar_number_engraver"
	}
}
EOD;
		return $header . $this->_input;
	}

	/**
	 * Render raw input file into PostScript file.
	 *
	 * @uses ScoreRender::_exec
	 * @param string $input_file File name of raw input file containing music content
	 * @param string $rendered_image File name of rendered PostScript file
	 * @return boolean Whether rendering is successful or not
	 */
	function execute ($input_file, $rendered_image)
	{
		/* lilypond adds .ps extension by itself */
		$cmd = sprintf ('%s --safe --ps --output %s %s 2>&1',
			$this->_options['LILYPOND_BIN'],
			dirname($rendered_image) . DIRECTORY_SEPARATOR . basename($rendered_image, ".ps"),
			$input_file);

		$retval = $this->_exec ($cmd);

		return ($retval == 0);
	}

	/**
	 * @uses ScoreRender::_exec
	 * @param string $rendered_image The rendered PostScript file name
	 * @param string $final_image The final PNG image file name
	 * @param boolean $invert True if image should be white on black instead of vice versa
	 * @param boolean $transparent True if image background should be transparent
	 * @return boolean Whether conversion from PostScript to PNG is successful
	 */
	function convertimg ($rendered_image, $final_image, $invert, $transparent)
	{
		// default staff size for lilypond is 20px, expected 24px, a ratio of 1.2:1
		// and 72*1.2 = 86.4
		$retval = parent::convertimg ($rendered_image, $final_image, $invert,
			$transparent, '-density 86');

		return ($retval === 0);
	}

	/**
	 * Check if given program is LilyPond, and whether it is usable.
	 *
	 * @param string $prog The program to be checked.
	 * @return boolean Return true if the given program is LilyPond AND it is executable.
	 */
	function is_lilypond_usable ($prog)
	{
		return parent::is_prog_usable ('GNU LilyPond', $prog, '--version');
	}
}

?>
