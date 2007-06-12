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
	 * @var array $_options ScoreRender config options.
	 * @access private
	 */
	var $_options;

	/**
	 * @var string $_input The music fragment to be rendered.
	 * @access private
	 */
	var $_input;

	/**
	 * @var string $_uniqueID A unique identifier for each kind of notation.
	 * @abstract
	 * @access private
	 */
	var $_uniqueID;

	/**
	 * @var string $_commandOutput Stores output message of rendering command.
	 * @access private
	 */
	var $_commandOutput;

	/**
	 * Initialize ScoreRender options
	 *
	 * @param array $options Instances are initialized with this option array
	 * @access protected
	 */
	function init_options ($options = array())
	{
		$this->_options = $options;
	}

	/**
	 * Sets music fragment content
	 *
	 * @since 0.2
	 * @uses $_input Stores music fragment content into this variable
	 * @param string $input The music fragment content
	 */
	function setMusicFragment ($input)
	{
		$this->_input = $input;
	}

	/**
	 * Outputs music fragment content
	 *
	 * @since 0.2
	 * @uses $_input Return input content, optionally prepended/appended with header or footer, and filtered in other ways
	 * @return string
	 */
	function getMusicFragment ()
	{
		return $this->_input;
	}

	/**
	 * Returns output message of rendering command.
	 *
	 * @uses $_commandOutput Returns this variable
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
	 * @uses $_commandOutput Command output is stored in this variable.
	 * @access protected
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
	 * Render raw input file into PostScript file.
	 *
	 * @param string $input_file File name of raw input file containing music content
	 * @param string $rendered_image File name of rendered PostScript file
	 * @abstract
	 */
	function execute ($input_file, $rendered_image)
	{
	}

	/**
	 * Converts rendered PostScript page into PNG format.
	 *
	 * All rendering command would generate PostScript format as output.
	 * In order to show the image, it must be converted to PNG format first,
	 * and the process is done here, using ImageMagick. Various effects are also
	 * applied here, like white edge trimming, color inversion and alpha blending.
	 *
	 * @uses _exec
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

		$retval = _exec ($cmd);

		return ($retval === 0);
	}

	/**
	 * Render music fragment into images
	 *
	 * First it tries to check if image is already rendered, and return
	 * existing image file name immediately. Otherwise the music fragment is
	 * rendered in 2 passes (with {@link convertimg} and {@link execute},
	 * and resulting image is stored in cache folder.
	 *
	 * @uses ERR_INVALID_INPUT Return this error code if isValidInput method returned false
	 * @uses ERR_LENGTH_EXCEEDED Return this error code if content length limit is exceeded
	 * @uses ERR_CACHE_DIRECTORY_NOT_WRITABLE Return this error code if cache directory is not writable
	 * @uses ERR_TEMP_DIRECTORY_NOT_WRITABLE Return this error code if temporary directory is not writable
	 * @uses ERR_INTERNAL_CLASS Return this error code if essential methods are missing from subclass
	 * @uses ERR_TEMP_FILE_NOT_WRITABLE Return this error if input file or postscript file is not writable
	 * @uses ERR_RENDERING_ERROR Return this error code if rendering command fails
	 * @uses ERR_IMAGE_CONVERT_FAILURE Return this error code if PS -> PNG conversion failed
	 *
	 * @uses execute First pass rendering: Convert input file -> PS
	 * @uses convertimg Second pass rendering: Convert PS -> PNG
	 *
	 * @return mixed Resulting image file name, or an error code in case any error occurred
	 * @final
	 */
	function render()
	{
		// Check for valid code
		if ( empty ($this->_input) ||
		     ( method_exists ($this, 'isValidInput') &&
		       !$this->isValidInput($this->_input)) )
			return ERR_INVALID_INPUT;

		// Check for content length
		if ( isset ($this->_options['CONTENT_MAX_LENGTH']) &&
		     ($this->_options['CONTENT_MAX_LENGTH'] > 0) &&
		     (strlen ($this->_input) > $this->_options['CONTENT_MAX_LENGTH']) )
			return ERR_LENGTH_EXCEEDED;

		// Create unique hash
		$hash = md5 ($this->_input . $this->_options['INVERT_IMAGE']
			     . $this->_options['TRANSPARENT_IMAGE'] . $this->_uniqueID);
		$final_image = $this->_options['CACHE_DIR'] . DIRECTORY_SEPARATOR
		                  . $hash . '.png';

		if (is_file ($final_image)) return basename ($final_image);

		// Create image if it does not exist
		if ( (!isset       ($this->_options['CACHE_DIR'])) ||
		     (!is_dir      ($this->_options['CACHE_DIR'])) ||
		     (!is_writable ($this->_options['CACHE_DIR'])) )
		{
			return ERR_CACHE_DIRECTORY_NOT_WRITABLE;
		}

		if ( (!isset       ($this->_options['TEMP_DIR'])) ||
		     (!is_dir      ($this->_options['TEMP_DIR'])) ||
		     (!is_writable ($this->_options['TEMP_DIR'])) )
		{
			return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
		}

		if (! method_exists ($this, 'getInputFileContents') ||
		    ! method_exists ($this, 'execute') )
		{
			return ERR_INTERNAL_CLASS;
		}

		if ( false === ($input_file = tempnam ($this->_options['TEMP_DIR'],
			'scorerender-' . $this->_uniqueID . '-')) )
		{
			return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
		}
		
		$rendered_image = $input_file . '.ps';

		// Create empty output file first ASAP
		if (! file_exists ($rendered_image) )
			touch ($rendered_image);

		if (! is_writable ($rendered_image) )
			return ERR_TEMP_FILE_NOT_WRITABLE;

		// Write input file contents
		if ( false === ($handle = fopen ($input_file, 'w')) )
			return ERR_TEMP_FILE_NOT_WRITABLE;

		fwrite ( $handle, $this->getInputFileContents() );
		fclose ( $handle );


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

		return basename ($final_image);
	}
}

?>
