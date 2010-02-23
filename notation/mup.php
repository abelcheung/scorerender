<?php
/**
 * Implements rendering of Mup notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.3
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from ScoreRender class, for supporting Mup notation.
 * @package ScoreRender
*/
class mupRender extends ScoreRender
{

private $width;

/**
 * @var string $magic_file Location of magic file used by Mup
 * @access private
 */
private $magic_file;

function __construct ()
{
	add_action ('sr_set_class_variable', array (&$this, 'set_magic_file_hook'));
}

/**
 * Set maximum width of generated images
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.2.50
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);
	$this->width = $this->img_max_width / DPI;
}

/**
 * Set the location of magic file
 *
 * @param string $file Full path of magic file
 * @since 0.2.50
 */
public function set_magic_file ($file)
{
	$this->magic_file = $file;
}

/**
 * Checks if given content is invalid or dangerous content
 *
 * @param string $input
 * @return boolean True if content is deemed safe
 */
protected function is_valid_input ()
{
	$blacklist = array
	(
		'/^\s*\binclude\b/', '/^\s*\bfontfile\b/'
	);

	foreach ($blacklist as $pattern)
		if (preg_match ($pattern, $this->_input))
			return false;

	return true;
}

/**
 * Refer to {@link ScoreRender::get_music_fragment() parent method} for more detail.
 */
public function get_music_fragment ()
{
	$header = <<<EOD
//!Mup-Arkkra-5.0
score
leftmargin = 0
rightmargin = 0
topmargin = 0
bottommargin = 0
pagewidth = {$this->width}
label = ""
EOD;
	return $header . "\n" . $this->_input;
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method} for more detail.
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	/* Mup requires a magic file before it is usable.
	   On Unix this file is named ".mup", and must reside in $HOME or current working directory.
	   On Windows / DOS, it is named "mup.ok" instead, and located in current working directory or same location as mup.exe do.
	   It must be present even if not registered, otherwise mup refuse to render anything.
	   Even worse, the exist status in this case is 0, so _exec() succeeds yet no postscript is rendered. */

	if (is_windows())
		$temp_magic_file = $this->temp_dir . '\mup.ok';
	else
		$temp_magic_file = $this->temp_dir . '/.mup';
	
	if (!file_exists($temp_magic_file))
	{
		if (is_readable($this->magic_file))
			copy($this->magic_file, $temp_magic_file);
		else
			touch ($temp_magic_file);
	}

	/* mup forces this kind of crap */
	putenv ("HOME=" . $this->temp_dir);
	chdir ($this->temp_dir);
	
	$cmd = sprintf ('"%s" -f "%s" "%s"',
			$this->mainprog,
			$intermediate_image, $input_file);
	$retval = $this->_exec($cmd);

	unlink ($temp_magic_file);
	
	return (filesize ($intermediate_image) != 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// FIXME: mind boggling exercise: why ImageMagick identifies PostScript produced by Mup as having
	// transparency on Windows, yet otherwise on Linux?
	// FIXME: 2. more exercise: when is it interpreted as having transparency on Linux too?
	return parent::conversion_step2 ($intermediate_image, $final_image, true);
}


/**
 * Check if given program is Mup, and whether it is usable.
 *
 * @param string $args A CGI-like query string containing the program to be checked.
 * @uses is_prog_usable()
 * @return boolean Return true if the given program is Mup AND it is executable.
 */
public function is_notation_usable ($args = '')
{
	wp_parse_str ($args, $r);
	extract ($r, EXTR_SKIP);
	return parent::is_prog_usable ('Arkkra Enterprises', $prog, '-v');
}

/**
 * Set the location of magic file
 * This is not supposed to be called directly; it is used as a
 * WordPress action hook instead.
 *
 * {@internal OK, I cheated. Shouldn't have been leaking external
 * config option names into class, but this can help saving me
 * headache in the future}}
 *
 * @since 0.2.50
 */
public function set_magic_file_hook ($options)
{
	if (isset ($options['MUP_MAGIC_FILE']))
		$this->set_magic_file ($options['MUP_MAGIC_FILE']);
}

}  // end of class

?>
