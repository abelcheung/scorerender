<?php
/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
 */

/**
 * Base class shared by all notations.
 * Most mandatory subclass methods are already listed in 
 * {@link ScoreRender_Notation} interface below, plus one more:
 *
 * - {@link conversion_step1()}
 *
 * The methods below are optional for subclasses:
 *
 * - {@link conversion_step2()}
 * - {@link is_valid_input()}
 *
 * Please refer to class.*.inc.php for examples.
 * @package ScoreRender
 */
abstract class ScoreRender
{

/**
 * Error constant used when content is known to be invalid or dangerous.
 *
 * Dangerous content means special constructs causing
 * inclusion of another file or command execution, etc.
 */
const ERR_INVALID_INPUT = 1;

/**
 * Error constant used when cache directory is not writable.
 */
const ERR_CACHE_DIRECTORY_NOT_WRITABLE = 2;

/**
 * Error constant used when temporary working directory is not writable.
 */
const ERR_TEMP_DIRECTORY_NOT_WRITABLE = 3;

/**
 * Error constant used when temporary file is not writable.
 *
 * This error is very rare, most usually it's the directory (not file) which is not writable.
 */
const ERR_TEMP_FILE_NOT_WRITABLE = 4;

/**
 * Error constant used when conversion of rendered image to proper format (PostScript -> PNG) failed.
 */
const ERR_IMAGE_CONVERT_FAILURE = 5;

/**
 * Error constant used when any generic error occurred during rendering.
 */
const ERR_RENDERING_ERROR = 6;

/**
 * Error constant representing that ImageMagick convert is unusable.
 */
const ERR_CONVERT_UNUSABLE = 9;

/**
 * Error constant representing final image unreadable.
 */
const ERR_IMAGE_NOT_VIEWABLE = 10;

/**
 * Error constant representing web host disabled certain PHP functions
 */
const ERR_FUNC_DISABLED = 11;


/*
 * Variables
 */


/**
 * @var string $_input The music fragment to be rendered.
 */
protected $_input;

/**
 * @var string $_commandOutput Stores output message of rendering command.
 */
protected $_commandOutput;

/**
 * @var string $imagemagick Full path of ImageMagick convert
 */
protected $imagemagick;

/**
 * @var string $temp_dir Temporary working directory
 */
protected $temp_dir;

/**
 * @var string $cache_dir Folder for storing cached images
 */
protected $cache_dir;

/**
 * @var integer $img_max_width Maximum width of generated images
 */
protected $img_max_width = 360;

/**
 * @var string $mainprog Main program for rendering music into images
 */
protected $mainprog;

/**
 * @var integer $error_code Contains error code about which kind of failure is encountered during rendering
 */
protected $error_code;


/*
 * Methods
 */

/**
 * Sets music fragment content
 *
 * @since 0.2
 * @uses $_input Stores music fragment content into this variable
 * @param string $input The music fragment content
 */
public function set_music_fragment ($input)
{
	$this->_input = $input;
}

/**
 * Set the main program used to render music
 *
 * @param mixed $progs A single program or array of programs to be used
 * @uses $mainprog Full path is stored in this variable
 * @todo For some notations like PMX/MusiXTeX, multiple programs must be set, currently this function can only handle one.
 * @since 0.3
 */
public function set_programs ($progs)
{
	if (is_string ($progs))
	{
		$this->mainprog = $progs;
		return;
	}
	elseif (!is_array ($progs) || empty ($progs))
		return;
	else
	{
		switch (count ($progs))
		{
		  case 1:
			$v = array_values ($progs);
			$this->mainprog = $v[0];
			break;
		  default:
			// Only picks the first element
			list ($k) = array_keys ($progs);
			$this->mainprog = $progs[$k];
			break;
		}
	}
}

/**
 * Returns output message of rendering command.
 *
 * @uses $_commandOutput Returns this variable
 * @return string
 */
public function get_command_output ()
{
	return $this->_commandOutput;
}

/**
 * Returns notation name, by comparing class name with notation name list.
 *
 * @return string|boolean Return notation name if found, FALSE otherwise.
 * @uses format_error_msg()
 * @since 0.2
 */
public function get_notation_name ()
{
	global $notations;
	$classname = strtolower (get_class ($this));

	foreach ($notations as $notationname => $notation)
		if ($classname === strtolower ($notation['classname']))
			return $notationname;

	return false;
}

/**
 * Sets path of ImageMagick convert
 *
 * @param string $path Full path of ImageMagick convert binary
 * @since 0.3
 */
public function set_imagemagick_path ($path)
{
	$this->imagemagick = $path;
}

/**
 * Set temporary folder
 *
 * @param string $path The desired temporary folder to use.
 * @since 0.3
 */
public function set_temp_dir ($path)
{
	$this->temp_dir = $path;
}

/**
 * Get temporary folder path
 *
 * @return string|boolean The temporary folder if already set, or FALSE otherwise
 * @since 0.3
 */
public function get_temp_dir ()
{
	return (isset ($this->temp_dir)) ? $this->temp_dir : false;
}

/**
 * Set cache folder for storing images
 *
 * @param string $path The desired cache folder to use.
 * @since 0.3
 */
public function set_cache_dir ($path)
{
	$this->cache_dir = $path;
}

/**
 * Get cache folder path
 *
 * @return string|boolean The cache folder if already set, or FALSE otherwise
 * @since 0.3
 */
public function get_cache_dir ()
{
	return (isset ($this->cache_dir)) ? $this->cache_dir : false;
}

/**
 * Set maximum width of generated images
 *
 * For some notations the unit for width is not pixel; under those situations
 * the methods are overrided in inherited notations.
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.3
 */
public function set_img_width ($width)
{
	$this->img_max_width = $width;
}

/**
 * Utility function: format error message into standardized format
 *
 * @param string $mesg Original error message
 * @return string Formatted message
 * @since 0.3
 */
private function format_error_msg ($mesg)
{
	return sprintf (__('[ScoreRender Error: %s]', TEXTDOMAIN), $mesg);
}

/**
 * Retrieve error message
 *
 * @uses $error_code Message is generated according to error code
 * @uses format_error_msg()
 * @return string Localized error message
 * @since 0.3
 */
public function get_error_msg ()
{
	switch ($this->error_code)
	{
	  case ERR_INVALID_INPUT:
		return $this->format_error_msg (__('Content is illegal or poses security concern!', TEXTDOMAIN));

	  case ERR_CACHE_DIRECTORY_NOT_WRITABLE:
		return $this->format_error_msg (__('Cache directory not writable!', TEXTDOMAIN));

	  case ERR_TEMP_DIRECTORY_NOT_WRITABLE:
		return $this->format_error_msg (__('Temporary directory not writable!', TEXTDOMAIN));

	  case ERR_TEMP_FILE_NOT_WRITABLE:
		return $this->format_error_msg (__('Temporary file not writable!', TEXTDOMAIN));

	  case ERR_IMAGE_CONVERT_FAILURE:
		return $this->format_error_msg (__('Image conversion failure!', TEXTDOMAIN));

	  case ERR_RENDERING_ERROR:
		return $this->format_error_msg (__('The external rendering application failed!', TEXTDOMAIN));

	  case ERR_CONVERT_UNUSABLE:
		return $this->format_error_msg (__('ImageMagick program is unusable!', TEXTDOMAIN));

	  case ERR_IMAGE_NOT_VIEWABLE:
		return $this->format_error_msg (__('Image is not viewable!', TEXTDOMAIN));

	  case ERR_FUNC_DISABLED:
		return $this->format_error_msg (__('Some PHP functions are disabled by web host.', TEXTDOMAIN));
	}
}

/**
 * Executes command and stores output message
 *
 * It is basically {@link exec() exec()} with additional stuff. On Windows,
 * however, the command is dumped into a batch first before executing,
 * in order to circumvent an ancient unfixed bug.
 *
 * @param string $cmd Command to be executed
 * @uses $_commandOutput Command output is stored after execution.
 * @uses is_windows() Command line is adjusted if under Windows
 * @return integer Exit status of the command
 * @final
 */
final protected function _exec ($cmd)
{
	if (SR_DEBUG) { echo '<pre style="overflow: auto">' . $cmd . "</pre>\n"; }

	$cmd_output = array();

	$retval = 0;

	$cmd .= " 2>&1";
	// ": &" is used to bypass cmd.exe /c, which prevents commands
	// with more than 2 double quotes to run
	if (is_windows())
		$cmd = '\: \& ' . $cmd;
		
	exec ($cmd, $cmd_output, $retval);
	$this->_commandOutput = implode ("\n", $cmd_output);

	return $retval;	
}

/**
 * First step of rendering process: execute the real command
 *
 * The command reads input content (after necessary conversion),
 * and converts it to a PostScript file.
 *
 * @uses _exec()
 * @param string $input_file File name of raw input file containing music content
 * @param string $intermediate_image File name of rendered PostScript file
 * @return boolean Whether rendering is successful or not
 */
abstract protected function conversion_step1 ($input_file, $intermediate_image);

/**
 * Second step of rendering process: Convert rendered PostScript page into PNG format
 *
 * (Almost) All rendering commands generate PostScript format as output.
 * In order to show the image, it must be converted to PNG format first,
 * using ImageMagick. Various effects are also applied, like white edge
 * trimming, color inversion and alpha blending.
 *
 * In inherited classes, only the first 2 arguments are supplied. All other arguments
 * are automatically determined within the inherited classes.
 *
 * @uses _exec()
 * @uses $imagemagick
 * @param string $intermediate_image The rendered PostScript file name
 * @param string $final_image The final PNG image file name
 * @param boolean $ps_has_alpha True if PostScript produced by music rendering program has transparency capability
 * @param string $extra_arg Extra arguments supplied to ImageMagick convert
 * @return boolean Whether PostScript -> PNG conversion is successful and PNG file is successfully generated in cache folder
 */
protected function conversion_step2 ($intermediate_image, $final_image, $ps_has_alpha = false, $extra_arg = '')
{
	$cmd = sprintf ('"%s" %s -trim +repage ', $this->imagemagick, $extra_arg);

	// Damn it, older ImageMagick can't handle transparency in PostScript,
	// but suddenly it can now, and renders all previous logic broken
	if ($ps_has_alpha)
	{
		$cmd .= sprintf (' "%s" "%s"',
			$intermediate_image, $final_image);
	}
	else
	{
		// Adding alpha channel and changing alpha value
		// need separate invocations, can't do in one pass
		$cmd .= sprintf ('-alpha activate "%s" png:- | "%s" -channel alpha -fx "1-intensity" -channel rgb -fx 0 png:- "%s"',
			$intermediate_image,
			$this->imagemagick,
			$final_image);
	}

	return (0 === $this->_exec ($cmd));
}

/**
 * Check if certain functions are disabled
 *
 * @since 0.3
 * @return boolean Return TRUE if {@link popen() popen()} or
 * {@link pclose() pclose()} are disabled, FALSE otherwise
 */
public static function is_web_hosting ()
{
	if (!function_exists ('popen') ||
	    !function_exists ('pclose'))
		return true;
}

/**
 * Check if given program is usable.
 *
 * @since 0.2
 * @uses is_absolute_path()
 * @uses is_web_hosting()
 * @param mixed $match The string to be searched in program output.
 * Can be an array of strings, in this case all strings must be found.
 * Any non-string element in the array is ignored.
 * @param string $prog The program to be checked
 * @param string $args Extra variable arguments supplied to the program (if any)
 * @return boolean Return TRUE if the given program is usable, FALSE otherwise
 */
public static function is_prog_usable ($match, $prog)
{
	if (empty ($prog)) return false;

	// safe guard
	if (!is_absolute_path ($prog))
		return false;

	$prog = realpath ($prog);

	if (! is_executable ($prog)) return false;

	// short circuit if some funcs are disabled by web host
	if (self::is_web_hosting())
		return true;

	$args = func_get_args ();
	array_shift ($args);
	array_shift ($args);

	$cmdline = '"' . $prog . '" ' . implode (' ', $args) . ' 2>&1';
	if (false == ($handle = popen ($cmdline, 'r'))) return false;

	$output = fread ($handle, 2048);
	$ok = false;

	$needles = (array) $match;
	foreach ($needles as $needle)
	{
		if (is_string ($needle) && (false !== strpos ($output, $needle)))
		{
			$ok = true;
			break;
		}
	}

	pclose ($handle);
	return $ok;
}


/**
 * Render music fragment into images
 *
 * First it tries to check if image is already rendered, and return
 * existing image file name immediately. Otherwise the music fragment is
 * rendered in 2 passes (with {@link conversion_step1()} and
 * {@link conversion_step2()}, and resulting image is stored in cache folder.
 * Error code will be set appropriately.
 *
 * @uses ScoreRender_Notation::get_music_fragment()
 * @uses is_valid_input() Validate content before rendering
 * @uses is_prog_usable() Check if ImageMagick is functional
 * @uses conversion_step1() First pass rendering: Convert input file -> PS
 * @uses conversion_step2() Second pass rendering: Convert PS -> PNG
 * @uses $_input
 * @uses $cache_dir
 * @uses $error_code Type of error encountered is stored here
 *
 * @todo render image in background, especially for lilypond, which can take
 * minutes or even hours to finish rendering
 * @return string|boolean Resulting image file name, or FALSE upon error
 * @final
 */
final public function render()
{
	$hash = md5 (preg_replace ('/\s/', '', $this->_input));
	$final_image = $this->cache_dir. DIRECTORY_SEPARATOR .
		       "sr-" . $this->get_notation_name() . "-$hash.png";

	// If cache exists, short circuit
	if (is_file ($final_image))
		if (is_readable ($final_image))
			return basename ($final_image);
		else
		{
			$this->error_code = ERR_IMAGE_NOT_VIEWABLE;
			return false;
		}

	// Check for code validity or security issues
	if ( empty ($this->_input) ||
		( method_exists ($this, 'is_valid_input') && !$this->is_valid_input() ) )
	{
		$this->error_code = ERR_INVALID_INPUT;
		return false;
	}

	if ( ! $this->is_prog_usable ('ImageMagick', $this->imagemagick, '-version') )
	{
		$this->error_code = ERR_CONVERT_UNUSABLE;
		return false;
	}

	if ( (!is_dir      ($this->cache_dir)) ||
	     (!is_writable ($this->cache_dir)) )
	{
		$this->error_code = ERR_CACHE_DIRECTORY_NOT_WRITABLE;
		return false;
	}

	// Use fallback tmp dir if original one not working
	if ( (!is_dir      ($this->temp_dir)) ||
	     (!is_writable ($this->temp_dir)) )
		$this->temp_dir = sys_get_temp_dir ();

	if ( (!is_dir      ($this->temp_dir)) ||
	     (!is_writable ($this->temp_dir)) )
	{
		$this->error_code = ERR_TEMP_DIRECTORY_NOT_WRITABLE;
		return false;
	}

	if ( false === ($temp_working_dir = create_temp_dir ($this->temp_dir, 'sr-')) )
	{
		$this->error_code = ERR_TEMP_DIRECTORY_NOT_WRITABLE;
		return false;
	}

	if ( false === ($input_file = tempnam ($temp_working_dir,
		'scorerender-' . $this->get_notation_name() . '-')) )
	{
		$this->error_code = ERR_TEMP_FILE_NOT_WRITABLE;
		return false;
	}
	
	$intermediate_image = $input_file . '.ps';

	if (! file_exists ($intermediate_image) )
	{
		// Create empty output file first ASAP
		if (! touch ($intermediate_image) )
		{
			$this->error_code = ERR_TEMP_FILE_NOT_WRITABLE;
			return false;
		}
	}
	elseif (! is_writable ($intermediate_image) )
	{
		$this->error_code = ERR_TEMP_FILE_NOT_WRITABLE;
		return false;
	}

	if (false === file_put_contents ($input_file, $this->get_music_fragment()))
	{
		$this->error_code = ERR_TEMP_FILE_NOT_WRITABLE;
		return false;
	}


	// Render using external application
	$current_dir = getcwd();
	chdir ($temp_working_dir);
	if (!$this->conversion_step1($input_file, $intermediate_image) ||
	    (filesize ($intermediate_image)) === 0)
	{
		if (! SR_DEBUG) {
			unlink ($input_file);
			@rmdir ($temp_working_dir);
		}
		$this->error_code = ERR_RENDERING_ERROR;
		return false;
	}
	chdir ($current_dir);

	if (!$this->conversion_step2 ($intermediate_image, $final_image))
	{
		$this->error_code = ERR_IMAGE_CONVERT_FAILURE;
		return false;
	}

	// Cleanup
	if (! SR_DEBUG) {
		unlink ($intermediate_image);
		unlink ($input_file);
		@rmdir ($temp_working_dir);
	}

	return basename ($final_image);
}

/**
 * Output program setting HTML for notation
 *
 * @param string $bin_name Name of binary program
 * @param string $setting_name Name of setting used by the binary
 * @param string $title Title for setting entry
 * @param string $desc Optional description, shown under setting entry
 *
 * @return string HTML for the program setting in admin page
 * @since 0.3.50
 */
public static function program_setting_entry ($bin_name, $setting_name, $title = '', $desc = '')
{
	global $sr_options;
	$id = strtolower ($setting_name);

	$output = "<tr valign='top'>\n"
		. "<th scope='row'><label for='{$id}'>"
		. ( empty ($title) ? sprintf (__('Location of %s binary:', TEXTDOMAIN), '<code>'.$bin_name.'</code>') : $title )
		. "</label></th>\n"
		. "<td><input name='ScoreRender[{$setting_name}]' type='text' id='{$id}' "
		. "value='{$sr_options[$setting_name]}' class='regular-text code' />"
		. ( empty ($desc) ? '' : "<div class='setting-description'>{$desc}</div>\n" )
		. "</td>\n</tr>\n";

	return $output;
}

} // end of class

/**
 * Interface for every notations used by ScoreRender
 * @package ScoreRender
 */
interface ScoreRender_Notation
{
	/**
	 * Outputs music fragment content
	 *
	 * Most usually user supplied content does not contain correct
	 * rendering options like page margin, staff width etc, and
	 * each notation has its own requirements. Some also require additional
	 * filtering before able to be used by rendering programs.
	 * Such conversions are applied on output as well, though original content
	 * ($_input) is not modified in any way.
	 *
	 * @return string The full music content to be rendered, after necessary filtering
	 */
	function get_music_fragment ();

	/**
	 * Check if given program locations are correct and usable
	 *
	 * @param array $errmsgs An array of messages to be added if program checking failed
	 * @param array $opt Reference of ScoreRender option array, containing all program paths
	 *
	 * @since 0.3.50
	 */
	static function is_notation_usable ($errmsgs, $opt);

	/**
	 * Define any additional error or warning messages if settings for notation
	 * has any problem.
	 *
	 * @param array $adm_msgs Array of messages potentially shown in
	 * admin page if any problem occurs
	 *
	 * @since 0.3.50
	 */
	static function define_admin_messages ($adm_msgs);

	/**
	 * Output program setting HTML for notation
	 *
	 * @param string $output string containing all HTML output, where extra settings shall be added
	 * @return string The new HTML output after adding input entries for settings
	 *
	 * @since 0.3.50
	 */
	static function program_setting_entry ($output);

	/**
	 * Define types of variables used for notation
	 *
	 * @param array $settings Reference of ScoreRender default settings to be modified
	 *
	 * @since 0.3.50
	 */
	static function define_setting_type ($settings);

	/**
	 * Define values for variables used for notation, usually program paths
	 *
	 * @param array $settings Reference of ScoreRender default settings to be modified
	 *
	 * @since 0.3.50
	 */
	static function define_setting_value ($settings);

} // end of interface

?>
