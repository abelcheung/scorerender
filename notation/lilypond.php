<?php
/**
 * Implements rendering of Lilypond notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.3
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from ScoreRender class, for supporting Lilypond notation.
 * @package ScoreRender
*/
class lilypondRender extends ScoreRender
{

/**
 * Refer to {@link ScoreRender::get_music_fragment() parent method} for more detail.
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
 * Determine LilyPond version
 * @param string $lilypond The path of lilypond program
 * @return string|boolean The version number string if it can be determined, otherwise FALSE 
 */
public static function lilypond_version ($lilypond)
{
	if ( !function_exists ('exec') ) return FALSE;

	exec ("\"$lilypond\" -v 2>&1", $output, $retval);
	
	if ( empty ($output) ) return FALSE;
	if ( !preg_match('/^gnu lilypond (\d+\.\d+\.\d+)/i', $output[0], $matches) ) return FALSE;
	return $matches[1];
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method}
 * for more detail.
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	$safemode = '';
	/* LilyPond SUCKS unquestionably. On 2.8 safe mode is triggered by "--safe" option,
	 * on 2.10.x it becomes "--safe-mode", and on 2.12.x that"s "-dsafe"!
	 */
	if ( false !== ( $lilypond_ver = self::lilypond_version ($this->mainprog) ) )
		if ( version_compare ($lilypond_ver, '2.11.11', '<') )
			$safemode = '-s';
		else
			$safemode = '-dsafe';
	
	/* lilypond adds .ps extension by itself, sucks for temp file generation */
	$cmd = sprintf ('"%s" %s --ps --output "%s" "%s"',
		$this->mainprog,
		$safemode,
		dirname($intermediate_image) . DIRECTORY_SEPARATOR . basename($intermediate_image, ".ps"),
		$input_file);

	$retval = $this->_exec ($cmd);

	return ($retval == 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
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
 * @param string $args A CGI-like query string containing the program to be checked.
 * @uses is_prog_usable()
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
