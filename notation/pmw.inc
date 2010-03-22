<?php
/**
 * Implements rendering of Philip's Music Writer notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung
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

const code = 'pmw';

private $midi_file = null;

protected static $notation_data = array ( /* {{{ */
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
); /* }}} */


/**
 * Refer to {@link SrNotationInterface::get_music_fragment() interface method}
 * for more detail.
 *
 * @uses $img_max_width
 * @uses $_input
 */
public function get_music_fragment () /* {{{ */
{
	// If page size is changed here, it must also be changed
	// under conversion_step2().
	$header = <<<EOD
Sheetsize A3
Linelength {$this->img_max_width}
Magnification 1.5

EOD;

	return normalize_linebreak ($header . "\n" . $this->input);
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step1() parent method} for more detail.
 */
protected function conversion_step1 () /* {{{ */
{
	if ( false === ( $intermediate_image = tempnam ( getcwd(), '' ) ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', SR_TEXTDOMAIN) );

	if ( false === ( $this->midi_file = tempnam ( getcwd(), '' ) ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', SR_TEXTDOMAIN) );

	/*
	 * Unlike Mup, PMW doesn't allow generating MIDI alone; getting MIDI file
	 * means also generating PostScript. Therefore generate MIDI here and only
	 * check for its existance in get_midi().
	 */
	$cmd = sprintf ('"%s" -norc -includefont -o "%s" -midi "%s" "%s"',
			$this->mainprog, $intermediate_image, $this->midi_file, $this->input_file);
	$retval = $this->_exec($cmd);

	return ( 0 === $retval ) ? $intermediate_image : $retval;
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image) /* {{{ */
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
	return parent::conversion_step2 ($intermediate_image,
			version_compare ( $this->imagick_ver, '6.3.9-6', '>=' ),
			'-page a3');
} /* }}} */

/**
 * Refer to {@link SrNotationBase::get_midi() parent method} for more detail.
 */
protected function get_midi ( $attr ) /* {{{ */
{
	return filesize ($this->midi_file) ? $this->midi_file : false;
} /* }}} */

/**
 * Refer to {@link SrNotationInterface::is_notation_usable() interface method}
 * for more detail.
 *
 * @uses SrNotationBase::is_notation_usable()
 */
public function is_notation_usable ($errmsgs = null, $opt) /* {{{ */
{
	static $ok;

	if ( ! isset ($ok) )
		$ok = parent::is_notation_usable ( &$errmsgs, $opt, self::$notation_data['progs'] );

	if ( isset ($this) && get_class ($this) == __CLASS__ )
		return $ok;
} /* }}} */

/**
 * Refer to {@link SrNotationInterface::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	$adm_msgs['pmw_bin_problem'] = array (
		'level' => ScoreRenderAdmin::MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', SR_TEXTDOMAIN), self::$notation_data['name'])
	);
}

/**
 * Refer to {@link SrNotationInterface::program_setting_entry() interface method}
 * for more detail.
 */
public static function program_setting_entry ($output)
{
	foreach (self::$notation_data['progs'] as $setting_name => $progdata)
		$output .= parent::program_setting_entry (
			$progdata['prog_name'], $setting_name);
	return $output;
}

/**
 * Refer to {@link SrNotationInterface::define_setting_type() interface method}
 * for more detail.
 */
public static function define_setting_type ($settings)
{
	foreach (self::$notation_data['progs'] as $setting_name => $progdata )
		$settings[$setting_name] = $progdata;
}

/**
 * Refer to {@link SrNotationInterface::define_setting_value() interface method}
 * for more detail.
 */
public static function define_setting_value ($settings)
{
	parent::define_setting_value ( &$settings, self::$notation_data['progs'] );
}

/**
 * Refer to {@link SrNotationInterface::register_notation_data() interface method}
 * for more detail.
 */
public static function register_notation_data ($notations)
{
	$notations[self::code] = self::$notation_data;
	return $notations;
}

} // end of class


add_action ('scorerender_register_notations'  , array( 'pmwRender', 'register_notation_data' ) );
add_action ('scorerender_define_adm_msgs'     , array( 'pmwRender', 'define_admin_messages'  ) );
add_action ('scorerender_check_notation_progs', array( 'pmwRender', 'is_notation_usable'     ), 10, 2 );
add_filter ('scorerender_prog_and_file_loc'   , array( 'pmwRender', 'program_setting_entry'  ) );
add_filter ('scorerender_define_setting_type' , array( 'pmwRender', 'define_setting_type'    ) );
add_filter ('scorerender_define_setting_value', array( 'pmwRender', 'define_setting_value'   ) );

/* vim: set cindent foldmethod=marker : */
?>
