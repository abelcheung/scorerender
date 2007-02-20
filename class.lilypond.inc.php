<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
 Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
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
 Mostly based on class.lilypondrender.inc.php from FigureRender
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Implements rendering of LilyPond figures in ScoreRender.
*/

class LilypondRender extends ScoreRender
{
	var $_uniqueID = "lilypond";

	function LilypondRender ($input, $options = array())
	{
		parent::init_options ($input, $options);
	}

	function getInputFileContents ($input)
	{
		$header = <<<EOD
\\version "2.8.1"
\\header {
	tagline= ""
}
\\paper {
	ragged-right = ##t
	indent = 0.0\\mm
	line-width = 5\\in
}
#(set-global-staff-size 24)
\\layout {
	\\context {
		\\Score
		\\remove "Bar_number_engraver"
	}
}
EOD;
		return $header . $input;
	}

	function execute ($input_file, $rendered_image)
	{
		/* lilypond adds .ps extension by itself */
		$cmd = sprintf ('%s --ps --output %s %s 2>&1',
			$this->_options['LILYPOND_BIN'],
			dirname($rendered_image) . DIRECTORY_SEPARATOR . basename($rendered_image, ".ps"),
			$input_file);

		$retval = parent::_exec ($cmd);

		return ($retval == 0);
	}

}

?>
