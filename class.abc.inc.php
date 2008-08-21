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
	/**
	 * Class constructor
	 * @param array $options Options to be passed into class
	 * @access private
	 */
	function abcRender ($options = array())
	{
		$this->init_options ($options);
		// $this->_options['IMAGE_MAX_WIDTH'] /= DPI;
		// Seems abcm2ps is using something like 120 dpi,
		// with 72DPI the notes and letters are very thin :(
		$this->_options['IMAGE_MAX_WIDTH'] /= 120;
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
		$header = <<<EOT
%abc
%%staffwidth {$this->_options['IMAGE_MAX_WIDTH']}in
%%stretchlast no
%%leftmargin 0.2in
%abc2mtex: yes
EOT;
		// input must not contain any empty line
		$this->_input = preg_replace ('/^$/m', '%', $this->_input);
		return $header . "\n" . $this->_input;
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
		$cmd = sprintf ('%s %s -O %s 2>&1',
		                $this->_options['ABCM2PS_BIN'],
		                $input_file, $rendered_image);
		$retval = $this->_exec($cmd);

		return ($result['return_val'] == 0);
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
		// old abcm2ps output is Grayscale by default. When attempting to add
		// transparency, it can only have value 0 or 1; that means notes,
		// slurs and letters won't have smooth outline. Converting to
		// RGB colorspace seems to fix the problem, but can't have all
		// options in one single pass. But...

		// abcm2ps devel version outputs DSC 2.0 Level 2 PostScript now,
		// and ImageMagick automatically converts it to transparent image.
		// So whole logic have to be rewritten.
		$cmd = $this->_options['CONVERT_BIN'] . ' -density 96 -trim +repage ';

		if (!$invert)
			$cmd .= (($transparent) ? '' : '-flatten ') .
					$rendered_image . ' ' . $final_image;
		else
		{
			if ($transparent)
				$cmd .= sprintf (' -negate %s %s',
					$rendered_image, $final_image);
			else
				$cmd .= sprintf (' -flatten %s png:- | %s -negate png:- %s',
					$rendered_image,
					$this->_options['CONVERT_BIN'],
					$final_image);
		}

		$retval = $this->_exec($cmd);

		return ($retval == 0);
	}
}

?>
