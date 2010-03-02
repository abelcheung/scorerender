<?php
/**
 * Implements rendering of Philip's Music Writer notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from SrNotationBase class, for supporting Philip's Music Writer notation.
 * @package ScoreRender
*/
class pmwRender extends SrNotationBase
                implements SrNotationInterface
{

/**
 * Refer to {@link SrNotationInterface::get_music_fragment() interface method}
 * for more detail.
 *
 * @uses $img_max_width
 * @uses $_input
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
 * Refer to {@link SrNotationBase::conversion_step1() parent method} for more detail.
 *
 * @uses $mainprog
 * @uses _exec()
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
 * Refer to {@link SrNotationBase::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	/*
	 * ImageMagick mistakenly identify all PostScript produced by PMW as
	 * having Letter (8.5"x11") size! Braindead. Without -page option it
	 * just displays incomprehensible error. Perhaps PMW is to be blamed
	 * though, since there is no BoundingBox nor page dimension specified
	 * in PostScript produced by PMW.
	 *
	 * A bug involving alpha channel in paletted PNG was fixed in 6.3.9-6;
	 * seems it affects any paletted image and level 1 PostScript too?
	 */
	return parent::conversion_step2 ($intermediate_image, $final_image,
			version_compare ( $this->imagick_ver, '6.3.9-6', '>=' ),
			'-page a3');
}

/**
 * Refer to {@link SrNotationInterface::is_notation_usable() interface method}
 * for more detail.
 * @uses is_prog_usable()
 */
public function is_notation_usable ($errmsgs = null, $opt)
{
	global $notations;
	static $ok;

	if ( !isset ($ok) )
	{
		$ok = true;
		foreach ($notations['pmw']['progs'] as $setting_name => $program)
		{
			if ( 'prog' !== $program['type'] ) continue;

			if ( empty ($opt[$setting_name]) )
			{
				$ok = false;
				break;
			}
			$result = parent::is_prog_usable ( $program['test_output'],
					$opt[$setting_name], $program['test_arg']);

			if ( is_wp_error ($result) || !$result )
			{
				$ok = false;
				break;
			}
		}

		if (!$ok)
		       if ( !is_null ($errmsgs) ) $errmsgs[] = $program['error_code'];
	}

	if ( isset ($this) && get_class ($this) == __CLASS__ )
		return $ok;
}

/**
 * Refer to {@link SrNotationInterface::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	global $notations;

	$adm_msgs['pmw_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['pmw']['name'])
	);
}

/**
 * Refer to {@link SrNotationInterface::program_setting_entry() interface method}
 * for more detail.
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
 * Refer to {@link SrNotationInterface::define_setting_type() interface method}
 * for more detail.
 */
public static function define_setting_type ($settings)
{
	global $notations;

	foreach ($notations['pmw']['progs'] as $key => $value)
		$settings[$key] = $value;
}

/**
 * Refer to {@link SrNotationInterface::define_setting_value() interface method}
 * for more detail.
 */
public static function define_setting_value ($settings)
{
	global $notations;

	foreach ( $notations['pmw']['progs'] as $setting_name => $progdata )
	{
		$binary_name = $progdata['prog_name'];
		if ( is_windows() ) $binary_name .= '.exe';
		$fullpath = '';

		// PMW doesn't even have public available Win32 binary, perhaps
		// somebody might be able to compile it with MinGW?
		if ( !is_windows() )
		{
			if ( function_exists ('shell_exec') )
				$fullpath = shell_exec ('which ' . $binary_name);
			else
				$fullpath = search_path ($binary_name);
		}

		$settings[$setting_name]['value'] = empty ($fullpath) ? '' : $fullpath;
	}
}

} // end of class


$notations['pmw'] = array (
	'name'        => "Philip's Music Writer",
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-pmw/',
	'classname'   => 'pmwRender',
	'progs'       => array (
		'PMW_BIN' => array (
			'prog_name' => 'pmw',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '-V',
			'test_output' => '/^PMW version ([\d.-]+)/',
			'error_code'  => 'pmw_bin_problem',
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

add_filter ('scorerender_define_setting_value',
	array( 'pmwRender', 'define_setting_value' ) );
?>
