<?php
/**
 * Implements rendering of Philip's Music Writer notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.3
 * @author Abel Cheung
 * @copyright Copyright (C) 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from ScoreRender class, for supporting Philip's Music Writer notation.
 * @package ScoreRender
*/
class pmwRender extends ScoreRender
{

/**
 * Refer to {@link ScoreRender::get_music_fragment() parent method} for more detail.
 */
public function get_music_fragment ()
{
	// If page size is changed here, it must also be changed
	// under conversion_step2().
	$header = <<<EOD
Sheetsize A3
Linelength {$this->img_max_width}
Magnification 1.5

EOD;
	// PMW doesn't like \r\n
	return str_replace ("\r", '', $header . $this->_input);
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method} for more detail.
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	$cmd = sprintf ('"%s" -norc -includefont -o "%s" "%s"',
			$this->mainprog,
			$intermediate_image, $input_file);
	$retval = $this->_exec($cmd);

	return ($result['return_val'] == 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// ImageMagick mistakenly identify all PostScript produced by PMW as
	// having Letter (8.5"x11") size! Braindead. Without -page option it
	// just displays incomprehensible error. Perhaps PMW is to be blamed
	// though, since there is no BoundingBox nor page dimension specified
	// in PostScript produced by PMW.
	return parent::conversion_step2 ($intermediate_image,
		$final_image, true, '-page a3');
}

/**
 * Check if given program is Philip's Music Writer, and whether it is usable.
 *
 * @param string $args A CGI-like query string containing the program to be checked.
 * @uses is_prog_usable()
 * @return boolean Return true if the given program is pmw AND it is executable.
 */
public function is_notation_usable ($args = '')
{
	wp_parse_str ($args, $r);
	extract ($r, EXTR_SKIP);
	return parent::is_prog_usable ('PMW version', $prog, '-V');
}

} // end of class

?>
