<?php
/**
 * Implements rendering of ABC notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from ScoreRender class, for supporting ABC notation.
 * @package ScoreRender
*/
class abcRender extends ScoreRender
                implements ScoreRender_Notation
{

/**
 * Refer to {@link ScoreRender::set_img_width() parent method}
 * for more detail.
 *
 * Seems abcm2ps is using something like 120 dpi,
 * with 72DPI the notes and letters are very thin :(
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);
	$this->img_max_width /= 120;
}

/**
 * Refer to {@link ScoreRender_Notation::get_music_fragment() interface method} for more detail.
 */
public function get_music_fragment ()
{
	$header = <<<EOT
%abc
%%staffwidth {$this->img_max_width}in
%%stretchlast no
%%leftmargin 0.2in
%abc2mtex: yes
EOT;
	// input must not contain any empty line
	return $header . "\n" . preg_replace ('/^$/m', '%', $this->_input);
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method} for more detail.
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	$cmd = sprintf ('"%s" "%s" -O "%s"',
			$this->mainprog,
			$input_file, $intermediate_image);
	$retval = $this->_exec($cmd);

	return ($result['return_val'] == 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	return parent::conversion_step2 ($intermediate_image, $final_image, TRUE, '-density 96');
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
	foreach ($notations['abc']['progs'] as $setting_name => $program)
		if ( ! empty ($opt[$setting_name]) && ! parent::is_prog_usable (
			$program['test_output'], $opt[$setting_name], $program['test_arg']) )
				$ok = false;
			
	if (!$ok) $errmsgs[] = 'abcm2ps_bin_problem';
}

/**
 * Refer to {@link ScoreRender_Notation::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	global $notations;

	$adm_msgs['abcm2ps_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['abc']['name'])
	);
}

/**
 * Refer to {@link ScoreRender_Notation::program_setting_entry() interface method}
 * for more detail.
 */
public static function program_setting_entry ($output)
{
	global $notations;

	foreach ($notations['abc']['progs'] as $setting_name => $program)
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

	foreach ($notations['abc']['progs'] as $key => $value)
		$settings[$key] = $value;
}

/**
 * Refer to {@link ScoreRender_Notation::define_setting_value() interface method}
 * for more detail.
 */
public static function define_setting_value ($settings)
{
	global $notations;

	$binary_name = $notations['abc']['progs']['ABCM2PS_BIN']['prog_name'];
	if ( is_windows() ) $binary_name .= '.exe';
	$fullpath = '';

	if ( is_windows() )
	{
		$fullpath = search_path ($binary_name);
		if ( !$fullpath && function_exists ('glob') )
		{
			$fullpath = glob ("C:\\Program Files\\*\\" . $binary_name);
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

	$settings['ABCM2PS_BIN']['value'] = empty ($fullpath) ? '' : $fullpath;
}

} // end of class


$notations['abc'] = array (
	'name'        => 'ABC',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-abc/',
	'regex'       => '~\[abc\](.*?)\[/abc\]~si',
	'starttag'    => '[abc]',
	'endtag'      => '[/abc]',
	'classname'   => 'abcRender',
	'progs'       => array (
		'ABCM2PS_BIN' => array (
			'prog_name' => 'abcm2ps',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '-V',
			'test_output' => 'abcm2ps',
		),
	),
);


add_action ('scorerender_define_adm_msgs',
	array( 'abcRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'abcRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'abcRender', 'program_setting_entry' ) );

add_filter ('scorerender_define_setting_type',
	array( 'abcRender', 'define_setting_type' ) );

add_filter ('scorerender_define_setting_value',
	array( 'abcRender', 'define_setting_value' ) );
?>
