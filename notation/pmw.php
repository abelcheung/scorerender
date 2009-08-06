<?php
/**
 * Implements rendering of Philip's Music Writer notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Philip's Music Writer notation.
 * @package ScoreRender
*/
class pmwRender extends ScoreRender
                implements ScoreRender_Notation
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
	$cmd = sprintf ('"%s" -includefont -o "%s" "%s"',
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
		$final_image, FALSE, '-page a3');
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
	foreach ($notations['pmw']['progs'] as $setting_name => $program)
		if ( ! empty ($opt[$setting_name]) && ! parent::is_prog_usable (
			$program['test_output'], $opt[$setting_name], $program['test_arg']) )
				$ok = false;
			
	if (!$ok) $errmsgs[] = 'pmw_bin_problem';
}

/**
 * Define any additional error or warning messages if settings for notation
 * has any problem.
 */
public static function define_admin_messages (&$adm_msgs)
{
	global $notations;

	$adm_msgs['pmw_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['pmw']['name'])
	);
}

/**
 * Output program setting HTML for notation
 */
public static function program_setting_entry ($output)
{
	global $notations;

	foreach ($notations['pmw']['progs'] as $setting_name => $program)
		$output .= parent::program_setting_entry (
			$program['prog_name'], $setting_name);
	return $output;
}

/**
 * Define types of variables used for notation
 */
public static function define_setting_type (&$settings)
{
	global $notations;

	$settings += $notations['pmw']['progs'];
}

} // end of class


$notations['pmw'] = array (
	'name'        => "Philip's Music Writer",
	'url'         => 'http://www.quercite.com/pmw.html',
	'regex'       => '~\[pmw\](.*?)\[/pmw\]~si',
	'starttag'    => '[pmw]',
	'endtag'      => '[/pmw]',
	'classname'   => 'pmwRender',
	'progs'       => array (
		'PMW_BIN' => array (
			'prog_name' => 'pmw',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '-V',
			'test_output' => 'PMW version',
		),
	),
);


add_action ('scorerender_define_adm_msgs',
	array( 'pmwRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'pmwRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'pmwRender', 'program_setting_entry' ) );

add_filter ('scorerender_define_setting_type',
	array( 'pmwRender', 'define_setting_type' ) );
?>
