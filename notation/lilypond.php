<?php
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
 * Refer to {@link ScoreRender::conversion_step1() parent method} for more detail.
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
 * Check if given program locations are correct and usable
 *
 * @param array $errmsgs An array of messages to be added if program checking failed
 * @param array $opt Array of ScoreRender options, containing all program paths
 * @uses ScoreRender::is_prog_usable()
 */
public static function is_notation_usable (&$errmsgs, &$opt)
{
	global $notations;

	$ok = true;
	foreach ($notations['lilypond']['progs'] as $prog)
		if ( ! empty ($opt[$prog]) && ! parent::is_prog_usable ('GNU LilyPond', $opt[$prog], '--version') )
			$ok = false;
			
	if (!$ok) $errmsgs[] = 'lilypond_bin_problem';
}

/**
 * Define any additional error or warning messages if settings for notation
 * has any problem.
 */
public static function define_admin_messages (&$adm_msgs)
{
	global $notations;

	$adm_msgs['lilypond_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['lilypond']['name'])
	);
}

/**
 * Output program setting HTML for notation
 */
public static function program_setting_entry ($output)
{
	$output .= parent::program_setting_entry (
		'lilypond_bin', 'lilypond', 'LILYPOND_BIN');
	return $output;
}

} // end of class


$notations['lilypond'] = array (
	'regex'       => '~\[lilypond\](.*?)\[/lilypond\]~si',
	'starttag'    => '[lilypond]',
	'endtag'      => '[/lilypond]',
	'classname'   => 'lilypondRender',
	'progs'       => array ('LILYPOND_BIN'),
	'url'         => 'http://www.lilypond.org/',
	'name'        => 'LilyPond',
);


add_action ('scorerender_define_adm_msgs',
	array( 'lilypondRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'lilypondRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'lilypondRender', 'program_setting_entry' ) );
?>
