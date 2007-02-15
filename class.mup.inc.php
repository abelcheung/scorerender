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
 class.muprender.inc.php
 Abel Cheung <abelcheung@gmail.com>
 3rd June 2006

 Implements rendering of Mup figures in FigureRender.
*/

class MupRender extends FigureRender
{
	var $_uniqueID = "mup";

	function MupRender ($input, $options = array())
	{

		$options = array_merge
		(
			array
			(
				'MUP_BIN' => '/usr/local/bin/mup'
			),
			$options
		);

		parent::FigureRender($input, $options);
	}

	function getInputFileContents ($input)
	{
		return $input;
	}

	function execute ($input_file, $output_file)
	{
		/* Mup requires a file ".mup" present in $HOME or
		   current working directory. It must be present even if
		   not registered, otherwise mup refuse to render anything.
		   Even worse, the exist status in this case is 0, so
		   _exec succeeds yet no postscript is rendered. */

		$temp_magic_file = $this->_options['TEMP_DIR'] . DIRECTORY_SEPARATOR . '.mup';
		if (!file_exists($temp_magic_file))
		{
			if (is_readable($this->_options['MUP_MAGIC_FILE']))
				copy($this->_options['MUP_MAGIC_FILE'], $temp_magic_file);
			else
				touch ($temp_magic_file);
		}

		/* mup forces this kind of crap */
		putenv ("HOME=" . $this->_options['TEMP_DIR']);

		$cmd = sprintf ('%s -f %s %s 2>&1',
		                $this->_options['MUP_BIN'],
		                $output_file, $input_file);
		$retval = parent::_exec($cmd);

		unlink ($temp_magic_file);

		return (filesize ($output_file) != 0);
		//return ($result['return_val'] == 0);
	}

	function convertimg ($output_file, $cache_filename, $invert, $transparent)
	{
		// Convert to specified format
		$cmd = $this->_options['CONVERT_BIN'] . ' -density 90 -trim ';

		if (!$transparent)
		{
			$cmd .= (($invert) ? '-negate ' : '')
			        . $output_file . ' ' . $cache_filename;
		}
		else
		{
			// Is it possible to execute convert only once?
			$cmd .= ' -channel alpha -fx intensity ' .
				$output_file . ' png:- | ' .
				$this->_options['CONVERT_BIN'] .
				' -channel ' . (($invert)? 'rgba' : 'alpha')
			        . ' -negate png:- ' . $cache_filename;
		}

		$retval = parent::_exec($cmd);

		return ($retval == 0);
	}
}

?>
