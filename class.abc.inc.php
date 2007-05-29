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
 Implements rendering of ABC notation in ScoreRender, using abc2ps.
*/

class abcRender extends ScoreRender
{
	var $_uniqueID = "abc";

	function abcRender ($input, $options = array())
	{
		parent::init_options ($input, $options);
		$this->_options['IMAGE_MAX_WIDTH'] /= DPI;
	}

	function getInputFileContents ($input)
	{
		$header = <<<EOT
%abc
%%staffwidth {$this->_options['IMAGE_MAX_WIDTH']}in
%%stretchlast no
%%leftmargin 0.2in
%abc2mtex: yes
EOT;
		// input must not contain any empty line
		$input = preg_replace ('/^$/m', '%', $input);
		return $header . "\n" . $input;
	}

	function execute ($input_file, $rendered_image)
	{
		$cmd = sprintf ('%s %s -O %s 2>&1',
		                $this->_options['ABCM2PS_BIN'],
		                $input_file, $rendered_image);
		$retval = parent::_exec($cmd);

		return ($result['return_val'] == 0);
	}

	function convertimg ($rendered_image, $final_image, $invert, $transparent)
	{
		// abcm2ps output is Grayscale by default. When attempting to add
		// transparency, it can only have value 0 or 1; that means notes,
		// slurs and letters won't have smooth outline. Converting to
		// RGB colorspace seems to fix the problem, but can't have all
		// options in one single pass.
		$cmd = $this->_options['CONVERT_BIN'] . ' -density 96 -trim +repage ';

		if (!$transparent)
		{
			$cmd .= (($invert) ? '-negate ' : ' ')
			        . $rendered_image . ' ' . $final_image;
		}
		else
		{
			// Really need to execute convert twice this time
			$cmd .= $rendered_image . ' png:- | ' .
				$this->_options['CONVERT_BIN'] .
				' -channel ' . (($invert)? 'rgba' : 'alpha')
			        . ' -fx "1-intensity" png:- ' . $final_image;
		}

		$retval = parent::_exec($cmd);

		return ($retval == 0);
	}
}

?>
