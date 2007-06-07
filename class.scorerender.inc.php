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
 Mostly based on class.figurerender.inc.php from FigureRender
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Follows the template method pattern. Subclasses should implement:
 - getInputFileContents($input)
 - execute($input_file, $rendered_image)
 optionally they can also implement:
 - isValidInput($input)
 - convertimg($rendered_image, $final_image, $invert, $transparent)
*/

/**
 * ScoreRender documentation
 * @package ScoreRender
 */

/**
 * ScoreRender Class
 * @package ScoreRender
 * @todo Avoid sneaking configuration options and music content directly into class when creating object
 * @abstract
 */
class ScoreRender
{
	/**
	 * @var array ScoreRender config options.
	 * @access private
	 */
	var $_options;

	/**
	 * @var string The music fragment to be rendered.
	 * @access private
	 */
	var $_input;

	/**
	 * @var string A unique identifier for each kind of notation.
	 * @abstract
	 * @access private
	 */
	var $_uniqueID;

	/**
	 * @var string Stores output message of rendering command.
	 * @access private
	 */
	var $_commandOutput;

	/**
	 * Initialize ScoreRender options
	 *
	 * @param array $options
	 * @access protected
	 */
	function init_options ($options = array())
	{
		$this->_options['FILE_FORMAT'] = 'png';
		$this->_options = array_merge ($this->_options, $options);
	}

	function setMusicFragment ($input)
	{
		$this->_input = $input;
	}

	function getMusicFragment ()
	{
		return $this->_input;
	}

	/**
	 * Returns output message of rendering command.
	 * @return string
	 */
	function getCommandOutput ()
	{
		return $this->_commandOutput;
	}

	/**
	 * Executes command and stores output message
	 *
	 * {@internal It is basically exec() with additional stuff}}
	 *
	 * @param string $cmd Command to be executed
	 * @access protected
	 * @final
	 */
	function _exec ($cmd)
	{
		$cmd_output = array();
		$retval = 0;

		exec ($cmd, $cmd_output, $retval);

		$this->_commandOutput = implode ("\n", $cmd_output);

		return $retval;
	}

	/**
	 * Converts rendered PostScript page into PNG format.
	 *
	 * All rendering command would generate PostScript format as output.
	 * In order to show the image, it must be converted to PNG format first,
	 * and the process is done here, using ImageMagick. Various effects are also
	 * applied here, like white edge trimming, color inversion and alpha blending.
	 *
	 * @param string $rendered_image The rendered PostScript file name
	 * @param string $final_image The final PNG image file name
	 * @param boolean $invert True if image should be white on black instead of vice versa
	 * @param boolean $transparent True if image background should be transparent
	 * @return boolean Whether conversion from PostScript to PNG is successful
	 * @access protected
	 */
	function convertimg ($rendered_image, $final_image, $invert, $transparent)
	{
		// Convert to specified format
		$cmd = $this->_options['CONVERT_BIN'] . ' -trim +repage ';

		if (!$transparent)
		{
			$cmd .= (($invert) ? '-negate ' : ' ')
			        . $rendered_image . ' ' . $final_image;
		}
		else
		{
			if (!$invert)
			{
				$cmd .= '-channel alpha ' . $rendered_image . ' ' . $final_image;
			}
			else
			{
				$cmd .=	'-channel alpha -fx intensity -channel rgb -negate ' .
					$rendered_image . ' ' .  $final_image;
			}
		}

		$retval = ScoreRender::_exec ($cmd);

		return ($retval === 0);
	}

	/**
	 * Render music fragment into images
	 *
	 * First it tries to check if image is already rendered, and return
	 * existing image file name immediately. Otherwise the music fragment is
	 * rendered, resulting image is stored in cache folder, and the file name
	 * is returned.
	 *
	 * If any error occurs during rendering process, an error code is returned
	 * instead.
	 *
	 * @return mixed
	 * @final
	 */
	function render()
	{
		// Check for valid code
		if (empty ($this->_input) ||
		    (method_exists ($this, 'isValidInput') &&
		     !$this->isValidInput($this->_input)))
			return ERR_INVALID_INPUT;

		// Check for content length
		if (isset ($this->_options['CONTENT_MAX_LENGTH']) &&
		    ($this->_options['CONTENT_MAX_LENGTH'] > 0) &&
		    (strlen ($this->_input) > $this->_options['CONTENT_MAX_LENGTH']))
			return ERR_LENGTH_EXCEEDED;

		// Create unique hash
		$hash = md5 ($this->_input . $this->_options['INVERT_IMAGE']
			     . $this->_options['TRANSPARENT_IMAGE'] . $this->_uniqueID);
		$final_image = $this->_options['CACHE_DIR'] . DIRECTORY_SEPARATOR
		                  . $hash . '.' . $this->_options['FILE_FORMAT'];

		if (!is_file ($final_image))
		{
			// Check cache directory
			if ( (!isset       ($this->_options['CACHE_DIR'])) ||
			     (!is_dir      ($this->_options['CACHE_DIR'])) ||
			     (!is_writable ($this->_options['CACHE_DIR'])) )
			{
				return ERR_CACHE_DIRECTORY_NOT_WRITABLE;
			}

			// Check temp directory
			if ( (!isset       ($this->_options['TEMP_DIR'])) ||
			     (!is_dir      ($this->_options['TEMP_DIR'])) ||
			     (!is_writable ($this->_options['TEMP_DIR'])) )
			{
				return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
			}

			if (($input_file = tempnam($this->_options['TEMP_DIR'],
				'fr-' . $this->_uniqueID . '-')) === false)
			{
				return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
			}
			
			if (! method_exists ($this, 'getInputFileContents') ||
			    ! method_exists ($this, 'execute'))
			{
				return ERR_INTERNAL_CLASS;
			}

			$rendered_image = $input_file . '.ps';

			// Create empty output file first ASAP
			if (! file_exists ($rendered_image))
				touch ($rendered_image);

			if (! is_writable ($rendered_image))
				return ERR_TEMP_FILE_NOT_WRITABLE;

			// Write input file contents
			if (($handle = fopen ($input_file, 'w')) === false)
				return ERR_TEMP_FILE_NOT_WRITABLE;

			fwrite ($handle, $this->getInputFileContents($this->_input));
			fclose ($handle);


			// Render using external application
			$current_dir = getcwd();
			chdir ($this->_options['TEMP_DIR']);
			if (!$this->execute($input_file, $rendered_image) ||
			    !file_exists ($rendered_image))
			{
				//unlink($input_file);
				return ERR_RENDERING_ERROR;
			}
			chdir ($current_dir);

			if (!$this->convertimg ($rendered_image, $final_image,
			                        $this->_options['INVERT_IMAGE'],
			                        $this->_options['TRANSPARENT_IMAGE']))
				return ERR_IMAGE_CONVERT_FAILURE;

			// Cleanup
			unlink ($rendered_image);
			unlink ($input_file);

		}

		return basename ($final_image);
	}
}

?>
