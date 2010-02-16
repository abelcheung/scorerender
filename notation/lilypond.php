<?php
/**
 * Implements rendering of Lilypond notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from ScoreRender class, for supporting Lilypond notation.
 * @package ScoreRender
*/
class lilypondRender extends ScoreRender
                     implements ScoreRender_Notation
{

/**
 * Refer to {@link ScoreRender_Notation::get_music_fragment() interface method}
 * for more detail.
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
 * Refer to {@link ScoreRender::conversion_step2() parent method}
 * for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// default staff size for lilypond is 20px, expected 24px, a ratio of 1.2:1
	// and 72*1.2 = 86.4
	return parent::conversion_step2 ($intermediate_image, $final_image, TRUE,
		'-equalize -density 86');
}

/**
 * Refer to {@link ScoreRender_Notation::is_notation_usable() interface method}
 * for more detail.
 * @uses ScoreRender::is_prog_usable()
 */
public static function is_notation_usable ($errmsgs, $opt)
{
	global $notations;

	$ok = true;
	foreach ($notations['lilypond']['progs'] as $setting_name => $program)
		if ( ! empty ($opt[$setting_name]) && ! parent::is_prog_usable (
			$program['test_output'], $opt[$setting_name], $program['test_arg']) )
				$ok = false;
			
	if (!$ok) $errmsgs[] = 'lilypond_bin_problem';
}

/**
 * Refer to {@link ScoreRender_Notation::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	global $notations;

	$adm_msgs['lilypond_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['lilypond']['name'])
	);
}

/**
 * Refer to {@link ScoreRender_Notation::program_setting_entry() interface method}
 * for more detail.
 */
public static function program_setting_entry ($output)
{
	global $notations;

	foreach ($notations['lilypond']['progs'] as $setting_name => $program)
		$output .= parent::program_setting_entry (
			$program['prog_name'], $setting_name);
	return $output;
}

/**
 * Refer to {@link ScoreRender_Notation::define_setting_type() interface method}
 * for more detail.
 */
public static function define_setting_type ($settings)
{
	global $notations;

	foreach ($notations['lilypond']['progs'] as $key => $value)
		$settings[$key] = $value;
}

/**
 * Refer to {@link ScoreRender_Notation::define_setting_value() interface method}
 * for more detail.
 */
public static function define_setting_value ($settings)
{
	global $notations;

	$binary_name = $notations['lilypond']['progs']['LILYPOND_BIN']['prog_name'];
	if ( is_windows() ) $binary_name .= '.exe';
	$fullpath = '';

	if ( is_windows() )
	{
		$fullpath = search_path ($binary_name);
		if ( !$fullpath && function_exists ('glob') )
		{
			$fullpath = glob ("C:\\Program Files\\*\\usr\\bin\\" . $binary_name);
			$fullpath = empty ($fullpath) ? '' : $fullpath[0];
		}
	}
	else
	{
		if ( function_exists ('shell_exec') )
			$fullpath = shell_exec ('which ' . $binary_name);
		else
			$fullpath = search_path ($binary_name);
	}

	$settings['LILYPOND_BIN']['value'] = empty ($fullpath) ? '' : $fullpath;
}

} // end of class


$notations['lilypond'] = array (
	'name'        => 'LilyPond',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-lilypond/',
	'regex'       => '~\[lilypond\](.*?)\[/lilypond\]~si',
	'starttag'    => '[lilypond]',
	'endtag'      => '[/lilypond]',
	'classname'   => 'lilypondRender',
	'progs'       => array (
		'LILYPOND_BIN' => array (
			'prog_name' => 'lilypond',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '--version',
			'test_output' => 'GNU LilyPond',
		),
	),
);


add_action ('scorerender_define_adm_msgs',
	array( 'lilypondRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'lilypondRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'lilypondRender', 'program_setting_entry' ) );

add_filter ('scorerender_define_setting_type',
	array( 'lilypondRender', 'define_setting_type' ) );

add_filter ('scorerender_define_setting_value',
	array( 'lilypondRender', 'define_setting_value' ) );
?>
