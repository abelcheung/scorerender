<?php
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
 class.lilypondrender.inc.php
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Implements rendering of LilyPond figures in FigureRender.
*/

class LilypondRender extends FigureRender
{
	var $_uniqueID = "lilypond";

	function LilypondRender ($input, $options = array())
	{
		$options = array_merge
		(
			array
			(
				'LILYPOND_BIN' => '/usr/bin/lilypond'
			),
			$options
		);

		parent::FigureRender ($input, $options);
	}

	function getInputFileContents ($input)
	{
		return '
			\version "2.8.1"
			\header {
				tagline= ""
			}
			\paper {
				ragged-right = ##t
				indent = 0.0\mm
			}
			' . $input . '
		';
	}

	function execute ($input_file, $output_file)
	{
		/* lilypond adds .ps extension by itself */
		$cmd = sprintf ('%s --safe-mode --ps --output %s %s 2>&1',
			$this->_options['LILYPOND_BIN'],
			dirname($output_file) . DIRECTORY_SEPARATOR . basename($output_file, ".ps"),
			$input_file);

		$retval = parent::_exec ($cmd);

		return ($retval == 0);
	}

}

?>
