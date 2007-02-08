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
 class.figurerender.inc.php
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Follows the template method pattern. Subclasses should implement:
 - getInputFileContents($input)
 - execute($input_file, $output_file)
 optionally they can also implement:
 - isValidInput($input)
 - convertimg($output_file, $cache_filename, $invert, $transparent)
*/


class FigureRender {
	
	var $_options;
	var $_input;
	var $_uniqueID;
	var $_previousOutput;
	
	function FigureRender($input, $options=array()) {
		// Configure options
		$this->_options['CONVERT_BIN'] = '/usr/bin/convert';
		$this->_options['TEMP_DIR'] = '/tmp';
		$this->_options['FILE_FORMAT'] = 'png';
		$this->_options['INVERT_IMAGE'] = false;
		
		$this->_options = array_merge($this->_options, $options);
		
		$this->_input = $input;
	}
	
	function getPreviousOutput() {
		return $this->_previousOutput;
	}
		
	function _exec($cmd) {
		$output = array();
		$return_val = 0;
		
		exec($cmd, $output, $return_val);
		
		$this->_previousOutput = implode("\n", $output);
		$output['return_val'] = $return_val;
		
		return $output;
	}
	
	function convertimg($output_file, $cache_filename, $invert, $transparent) {
		// Convert to specified format
		$cmd = $this->_options['CONVERT_BIN'] . ' -density 110 -trim ';

		if (!$transparent) {
			if ($invert) {
				$cmd .= '-negate ';
			}
			$cmd .= $output_file . ' ' . $cache_filename;
		} else {
			if (!$invert) {
				$cmd .= '-channel alpha ' . $output_file . ' ' . $cache_filename;
			} else {
				// Is it possible to execute convert only once?
				$cmd .=	'-channel alpha ' .
					$output_file . ' png:- | ' .
					$this->_options['CONVERT_BIN'] .
					' -channel rgb -negate png:- ' .
					$cache_filename;
			}
		}

		$out = FigureRender::_exec($cmd);

		return ($out['return_val'] == 0);
	}

	function render() {
		// Check for valid code
		if (empty($this->_input) || (method_exists($this, 'isValidInput') && 
			!$this->isValidInput($this->_input))) {
			
			return ERR_INVALID_INPUT;
		}
		
		// Create unique hash
		$hash = md5($this->_input . $this->_options['INVERT_IMAGE'] . $this->_options['TRANSPARENT'] . $this->_uniqueID);
		$cache_filename = $this->_options['CACHE_DIR'] . DIRECTORY_SEPARATOR .
			$hash . '.' . $this->_options['FILE_FORMAT'];
		
		if (!is_file($cache_filename)) {
			
			// Check cache directory
			if (
				(!isset($this->_options['CACHE_DIR'])) ||
				(!is_dir($this->_options['CACHE_DIR'])) || 
				(!is_writable($this->_options['CACHE_DIR']))
			) {
				return ERR_CACHE_DIRECTORY_NOT_WRITABLE;
			}
			
			// Check temp directory
			if (
				(!isset($this->_options['TEMP_DIR'])) ||
				(!is_dir($this->_options['TEMP_DIR'])) || 
				(!is_writable($this->_options['TEMP_DIR']))
			) {
				return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
			}
			
			
			if (($input_file = tempnam($this->_options['TEMP_DIR'],
			    'fr-' . $this->_uniqueID . '-')) === false) {
				return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
			}
			$output_file = $input_file . '.ps';

			// Create empty output file first ASAP
			if ((file_exists($output_file)) ||
			    (!$handle = @fopen($output_file, 'w'))) {
				return ERR_TEMP_FILE_NOT_WRITABLE;
			}
			fclose($handle);
			
			// Write input file contents
			if (!$handle = @fopen($input_file, 'w')) {
				return ERR_TEMP_FILE_NOT_WRITABLE;
			}
			
			fwrite($handle, $this->getInputFileContents($this->_input));
			fclose($handle);
			
		
			// Render using external application
			$current_dir = getcwd();
			chdir($this->_options['TEMP_DIR']);
			if (!$this->execute($input_file, $output_file)) {
				//unlink($input_file);
				return ERR_RENDERING_ERROR;
			}
			chdir($current_dir);
			
			if (!$this->convertimg($output_file, $cache_filename, $this->_options['INVERT_IMAGE'], $this->_options['TRANSPARENT'])) {
				return ERR_IMAGE_CONVERT_FAILURE;
			} 

			// Cleanup
			unlink($output_file);
			unlink($input_file);

		}
		
		
		return basename($cache_filename);
	}
}

?>
