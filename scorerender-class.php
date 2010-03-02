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
 * {@link SrNotationInterface} interface below, plus one more:
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
abstract class SrNotationBase
{

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
 * @var string $imagick_ver Version of ImageMagick installed on host 
 */
protected $imagick_ver = '';

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
 * @var string $midiprog Program used for generating MIDI
 */
protected $midiprog;

/**
 * @var integer $error_code Contains error code about which kind of failure is encountered during rendering
 */
protected $error_code;



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
 * Output raw fragment content
 * @since 0.3.50
 * @return string The raw fragment content without any processing
 */
public function get_raw_input ()
{
	return $this->_input;
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
 * Set the program used to generate MIDI
 *
 * @param string $prog The full path of program
 * @since 0.3.50
 */
public function set_midi_program ($prog = null)
{
	if ( empty ($prog) ) return;
	$this->midiprog = $prog;
	return;
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
public static function format_error_msg ($mesg)
{
	return sprintf (__('[ScoreRender Error: %s]', TEXTDOMAIN), $mesg);
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
 * Reads input content (after necessary conversion),
 * and converts it to a MIDI file.
 *
 * @uses _exec()
 * @param string $input_file File name of raw input file containing music content
 * @param string $final_midi File name of MIDI file to be generated
 * @return boolean TRUE if MIDI is successfully generated, otherwise FALSE
 */
//abstract protected function generate_midi ($input_file, $final_midi);

/**
 * Check if certain functions are disabled
 *
 * @since 0.3
 * @return boolean Return TRUE if {@link popen() popen()} or
 * {@link pclose() pclose()} are disabled, FALSE otherwise
 */
public static function is_web_hosting ()
{
	return ( !function_exists ('popen') || !function_exists ('pclose') );
}

/**
 * Check if given program is usable, by identifying existance of certain string
 * in command output, and optionally checking program version requirement as well
 *
 * @since 0.2
 * @uses is_web_hosting()
 * @param string|Array $regexes Regular expression (PCRE) to be matched in program output.
 * Can be an array of regexes, in this case ALL regexes must be matched.
 * @param string $prog The program to be checked
 * @param array $args Array of strings containing extra command line arguments (if any)
 * @param string $minver Program version to match against. The version of program
 * to be checked must not be smaller than the version supplied in argument.
 * Using this argument means only the first string in $regexes is searched.
 * @param int $verpos Regex position inside $regexes that should contain
 * version information, default is 1 (first parenthesis). Only used if $minver is
 * not null or empty.
 * @param string $realver If version check is performed, then the detected program
 * version will be written to this variable
 * @return WP_Error|bool TRUE if all regex strings are found in command output,
 * WP_Error otherwise (including unexpected error and unmatched regex)
 */
public function is_prog_usable ($regexes, $prog, $args = array(), $minver = "", $verpos = 1, &$realver = null)
{
	if ( ! file_exists ($prog) ) return new WP_Error
		( 'sr-prog-notexist', __('Program does not exist.', TEXTDOMAIN));

	if ( ! is_executable ($prog) ) return new WP_Error
		( 'sr-not-executable', __('Program is not executable.', TEXTDOMAIN) );

	// safe guard
	$prog = realpath ($prog);
	if ( false === $prog ) return new WP_Error
		( 'sr-realpath-fail', __("Can't determine real path of program.", TEXTDOMAIN) );

	// short circuit if some funcs are disabled by web host
	if (self::is_web_hosting()) return new WP_Error
		( 'sr-webhost-mode', __("Certain PHP functions are disabled by web host, most likely due to security reasons. Therefore program usability will not be checked.", TEXTDOMAIN) );

	// TODO: check that elements inside $args are indeed strings
	$args = (array) $args;

	$cmd = sprintf ( '"%s" %s 2>&1', $prog, implode (' ', $args) );
	if (false === ($handle = @popen ($cmd, 'r'))) return new WP_Error
		( 'sr-popen-fail', __('Failed to execute popen() for running command.', TEXTDOMAIN), $cmd );

	while ( ! feof ($handle) )
		$output .= fread ($handle, 1024);
	pclose ($handle);

	if ( ! empty ($minver) )
	{
		if ( !is_int ($verpos) || $verpos < 0 )
			return new WP_Error ( 'sr-verpos-invalid', __('Version position is invalid', TEXTDOMAIN), $verpos );

		if ( is_array ($regexes) ) $regexes = $regexes[0];

		if ( !preg_match ( $regexes, $output, $matches ) )
			return new WP_Error ( 'sr-prog-regex-notmatch', __('Desired regular expression not found in program output', TEXTDOMAIN), $regexes );
		else
		{
			if ( ! is_null ( $realver ) )
				$realver = $matches[$verpos];

			if ( version_compare ( $matches[$verpos], $minver, '>=' ) )
				return true;

			// Fail if installed program doesn't fulfill version requirement
			return new WP_Error ( 'sr-prog-ver-req-unfulfilled',
					sprintf (__("Program does not meet minimum version requirement, detected version is &#8216;%s&#8217; but &#8216;%s&#8217; is required.", TEXTDOMAIN),
						$matches[$verpos], $minver),
					array (
						'desired' => $minver,
						'actual'  => $matches[$verpos],
					)
			);
		}
	}

	foreach ( (array)$regexes as $regex )
		if ( !preg_match ( $regex, $output ) )
			return new WP_Error ( 'sr-prog-regex-notmatch',
					__('Desired regular expression not found in program output', TEXTDOMAIN),
					$regexes
			);

	// no version check performed, and all strings matched
	return true;
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
 * @uses SrNotationInterface::get_music_fragment()
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
	global $sr_options;

	$hash = md5 (preg_replace ('/\s/', '', $this->_input));
	$final_image = $this->cache_dir. DIRECTORY_SEPARATOR .
		       "sr-" . $this->get_notation_name() . "-$hash.png";

	// If cache exists, short circuit
	if (is_file ($final_image))
		if (is_readable ($final_image))
			return basename ($final_image);
		else
			return new WP_Error ( 'sr-image-unreadable',
					__('Image exists but is not readable', TEXTDOMAIN), $final_image );

	// Check for code validity or security issues
	if ( empty ($this->_input) ||
			( method_exists ($this, 'is_valid_input') && !$this->is_valid_input() ) )
		return new WP_Error ( 'sr-content-invalid',
				__('Content is illegal or poses security concern', TEXTDOMAIN) );

	// Check ImageMagick
	$result = $this->is_prog_usable ('/^Version: ImageMagick ([\d.-]+)/',
			$this->imagemagick, array('-version'), '6.3.5-7', 1, $this->imagick_ver);
	if ( is_wp_error ($result) || !$result )
		return new WP_Error ( 'sr-imagick-unusable',
				__('ImageMagick program is unusable', TEXTDOMAIN), $result );

	// Check notation rendering apps
	$result = $this->is_notation_usable (null, $sr_options);
	if ( is_wp_error ($result) || !$result )
		return new WP_Error ( 'sr-render-apps-unusable',
				__('Rendering application is unusable', TEXTDOMAIN), $result );

	// Check cache folder
	if ( (!is_dir      ($this->cache_dir)) ||
	     (!is_writable ($this->cache_dir)) )
		return new WP_Error ( 'sr-cache-dir-unwritable',
				__('Cache directory not writable', TEXTDOMAIN) );

	// Use fallback tmp dir if original one not working
	if ( (!is_dir      ($this->temp_dir)) ||
	     (!is_writable ($this->temp_dir)) )
		$this->temp_dir = sys_get_temp_dir ();

	if ( (!is_dir      ($this->temp_dir)) ||
	     (!is_writable ($this->temp_dir)) )
		return new WP_Error ( 'sr-temp-dir-unwritable',
				__('Temporary directory not writable', TEXTDOMAIN) );

	if ( false === ($temp_working_dir = create_temp_dir ($this->temp_dir, 'sr-')) )
		return new WP_Error ( 'sr-temp-dir-unwritable',
				__('Temporary directory not writable', TEXTDOMAIN) );

	if ( false === ($input_file = tempnam ($temp_working_dir,
		'scorerender-' . $this->get_notation_name() . '-')) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', TEXTDOMAIN) );

	$intermediate_image = $input_file . '.ps';

	if ( file_exists ($intermediate_image) )
		if ( ! @unlink ( $intermediate_image ) )
			return new WP_Error ( 'sr-temp-file-create-fail',
					__('Temporary file creation failure', TEXTDOMAIN) );

	// Create empty output file first ASAP
	if ( ! @touch ($intermediate_image) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', TEXTDOMAIN) );

	if (false === file_put_contents ($input_file, $this->get_music_fragment()))
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', TEXTDOMAIN) );


	// Render using external application
	$current_dir = getcwd();
	chdir ($temp_working_dir);
	if (!$this->conversion_step1($input_file, $intermediate_image) ||
	    (filesize ($intermediate_image)) === 0)
	{
		if (! SR_DEBUG) {
			@unlink ($input_file);
			@unlink ($intermediate_image);
			@rmdir ($temp_working_dir);
		}
		return new WP_Error ( 'sr-img-render-fail',
				__('Image rendering process failure', TEXTDOMAIN) );
	}
	chdir ($current_dir);

	if (!$this->conversion_step2 ($intermediate_image, $final_image))
		return new WP_Error ( 'sr-img-convert-fail',
				__('Image conversion failure', TEXTDOMAIN) );

	// Cleanup
	if (! SR_DEBUG) {
		@unlink ($input_file);
		@unlink ($intermediate_image);
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
interface SrNotationInterface
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
	 * It can be called both as static method AND class method.
	 *
	 * In the first case, it is used as a WP action hook inside admin page
	 * as program checker. $errmsgs is used to remember error messages
	 * that shall be printed in top portion of admin page. No value is
	 * returned.
	 *
	 * In second case, it is executed during rendering phase. $errmsgs
	 * shall be set to NULL, and the function returns either TRUE or
	 * FALSE, depending on program checking result.
	 *
	 * @since 0.3.50
	 * @param array $errmsgs An array of messages to be added if program
	 * checking failed. Only used if this is called as a static method.
	 * Please refer to description of this function for more detail.
	 * @param array $opt Reference of ScoreRender option array, containing
	 * all program paths
	 *
	 * @return null|bool NULL if it is called as static method, TRUE/FALSE
	 * if called from instantiated class object. Please refer to description
	 * of this function for more detail.
	 */
	function is_notation_usable ($errmsgs = null, $opt);

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
