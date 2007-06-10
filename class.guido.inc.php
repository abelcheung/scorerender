<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
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
 Implements fetching of Guido figures in ScoreRender.
*/

class guidoRender extends ScoreRender
{
	var $_uniqueID = "guido";

	function guidoRender ($options = array())
	{
		$this->init_options ($options);
	}

	function getInputFileContents ()
	{
		return $this->_input;
	}

	function execute ($input_file, $rendered_image)
	{
		$url = sprintf ('%s?defpw=%fcm;defph=%fcm;zoom=%f;crop=yes;gmndata=%s',
				'http://clef.cs.ubc.ca/scripts/salieri/gifserv.pl',
				$this->_options['IMAGE_MAX_WIDTH'] / DPI * 2.54, 100.0, 1,
				rawurlencode (file_get_contents ($input_file)));

		return (copy ($url, $rendered_image));
	}

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
}

?>
