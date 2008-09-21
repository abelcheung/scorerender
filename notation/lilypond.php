<?php
/*
 Mostly based on class.lilypondrender.inc.php from FigureRender
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006
*/

/**
 * Implements rendering of Lilypond notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Lilypond notation.
 * @package ScoreRender
*/
class lilypondRender extends ScoreRender
{

/**
 * Outputs complete music input file for rendering.
 *
 * Most usually user supplied content does not contain correct
 * rendering options like page margin, staff width etc, and
 * each notation has its own requirements. This method adds
 * such necessary content to original content for processing.
 *
 * @return string The full music content to be rendered
 */
public function get_music_fragment ()
{
	$header = <<<EOD
\\version "2.8.1"
\\header {
	tagline= ""
}
\\paper {
	ragged-right = ##t
	indent = 0.0\\mm
	line-width = {$this->img_max_width}\\pt
}
\\layout {
	\\context {
		\\Score
		\\remove "Bar_number_engraver"
	}
}
EOD;

	// When does lilypond start hating \r ? 
	return $header . str_replace (chr(13), '', $this->_input);
}

/**
 * Render raw input file into PostScript file.
 *
 * @uses ScoreRender::_exec
 * @param string $input_file File name of raw input file containing music content
 * @param string $intermediate_image File name of rendered PostScript file
 * @return boolean Whether rendering is successful or not
 * @access protected
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	/* lilypond adds .ps extension by itself */
	$cmd = sprintf ('"%s" --safe --ps --output "%s" "%s"',
		$this->mainprog,
		dirname($intermediate_image) . DIRECTORY_SEPARATOR . basename($intermediate_image, ".ps"),
		$input_file);

	$retval = $this->_exec ($cmd);

	return ($retval == 0);
}

/**
 * @param string $intermediate_image The rendered PostScript file name
 * @param string $final_image The final PNG image file name
 * @return boolean Whether conversion from PostScript to PNG is successful
 * @access protected
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// default staff size for lilypond is 20px, expected 24px, a ratio of 1.2:1
	// and 72*1.2 = 86.4
	return parent::conversion_step2 ($intermediate_image, $final_image, TRUE,
		'-equalize -density 86');
}

/**
 * Check if given program is LilyPond, and whether it is usable.
 *
 * @param string $prog The program to be checked.
 * @return boolean Return true if the given program is LilyPond AND it is executable.
 */
public function is_notation_usable ($args = '')
{
	wp_parse_str ($args, $r);
	extract ($r, EXTR_SKIP);
	return parent::is_prog_usable ('GNU LilyPond', $prog, '--version');
}

} // end of class

?>
