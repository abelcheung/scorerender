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
 * Implements rendering of GUIDO notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting GUIDO notation.
 * @package ScoreRender
*/
class guidoRender extends ScoreRender
{
	/**
	 * Class constructor
	 * @param array $options Options to be passed into class
	 * @access private
	 */
	function guidoRender ($options = array())
	{
		$this->init_options ($options);
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
		// 1.125 = 72/64; guido server use 64pixel per cm
		$url = sprintf ('%s?defpw=%fcm;defph=%fcm;zoom=%f;crop=yes;gmndata=%s',
				'http://clef.cs.ubc.ca/scripts/salieri/gifserv.pl',
				$this->_options['IMAGE_MAX_WIDTH'] / DPI * 2.54, 100.0, 1.125,
				rawurlencode (file_get_contents ($input_file)));

		return (copy ($url, $rendered_image));
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
		// Image from noteserver contains border
		$cmd = $this->_options['CONVERT_BIN'] . ' -shave 1x1 -trim -geometry 56% +repage ';

		if (!$transparent)
		{
			$cmd .= (($invert) ? '-negate ' : ' ')
			        . $rendered_image . ' ' . $final_image;
		}
		else
		{
			// Is it possible to execute convert only once?
			$cmd .= ' -channel alpha -fx intensity ' .
				$rendered_image . ' png:- | ' .
				$this->_options['CONVERT_BIN'] .
				' -channel ' . (($invert)? 'rgba' : 'alpha')
			        . ' -negate png:- ' . $final_image;
		}

		$retval = $this->_exec($cmd);

		return ($retval == 0);
	}

	function is_guido_usable ()
	{
		return ini_get('allow_url_fopen');
	}
}

?>
