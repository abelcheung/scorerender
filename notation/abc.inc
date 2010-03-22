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
 * Inherited from SrNotationBase class, for supporting ABC notation.
 * @package ScoreRender
*/
class abcRender extends SrNotationBase
                implements SrNotationInterface
{

const code = 'abc';

protected static $notation_data = array ( /* {{{ */
	'name'        => 'ABC',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-abc/',
	'classname'   => 'abcRender',
	'progs'       => array (
		'ABCM2PS_BIN' => array (
			'prog_name'   => 'abcm2ps',
			'type'        => 'prog',
			'value'       => '',
			'test_arg'    => '-V',
			'test_output' => '/^abcm2ps-([\d.]+)/',
			'error_code'  => 'abcm2ps_bin_problem',
		),
		'ABC2MIDI_BIN' => array (
			'prog_name'   => 'abc2midi',
			'type'        => 'midiprog',
			'value'       => '',
			'test_arg'    => '-h',
			'test_output' => '/^abc2midi version ([\d.]+)/',
			'error_code'  => 'abc2midi_bin_problem',
		),
	),
); /* }}} */

/**
 * Refer to {@link SrNotationInterface::get_music_fragment() interface method} for more detail.
 */
public function get_music_fragment () /* {{{ */
{
	$header = <<<EOT
%%stretchlast no
EOT;

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

	$cmd = sprintf ('"%s" -m 1in -w %d -s 1 -O "%s" "%s"',
			$this->mainprog, $this->img_max_width, $intermediate_image, $this->input_file);
	$retval = $this->_exec($cmd);

	return ( 0 === $retval ) ? $intermediate_image : $retval;
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image)
{
	return parent::conversion_step2 ($intermediate_image, TRUE);
}

/**
 * Refer to {@link SrNotationBase::get_midi() parent method} for more detail.
 */
protected function get_midi ( $attr ) /* {{{ */
{
	if ( false === ( $temp_midifile = tempnam ( getcwd(), '' ) ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', SR_TEXTDOMAIN) );

	/* While abcm2ps accepts a string as tempo (Q:) for score printing,
	 * abc2midi expects it to be a beat-per-minute number, and fall back
	 * to 120 bpm if not found. However specifying a number there affects
	 * abcm2ps score printing in turn. In such case, better use command
	 * line for specifying tempo.
	 */
	if ( is_numeric ($attr['tempo']) && $attr['tempo'] > 0 )
		$tempo = ' -Q ' . $attr['tempo'];
	else
		$tempo = '';

	$cmd = sprintf ('"%s" "%s" %s -v -o "%s"',
			$this->midiprog, $this->input_file, $tempo, $temp_midifile);
	$retval = $this->_exec($cmd);

	return ( 0 === $retval ) ? $temp_midifile : $retval;
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
public static function define_admin_messages ($adm_msgs) /* {{{ */
{
	$adm_msgs['abcm2ps_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', SR_TEXTDOMAIN), self::$notation_data['name'])
	);
	$adm_msgs['abc2midi_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('MIDI generation for %s notation may not work, because dependent program failed checking.', SR_TEXTDOMAIN), self::$notation_data['name'])
	);
} /* }}} */

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


add_action ('scorerender_register_notations'  , array( 'abcRender', 'register_notation_data' ) );
add_action ('scorerender_define_adm_msgs'     , array( 'abcRender', 'define_admin_messages'  ) );
add_action ('scorerender_check_notation_progs', array( 'abcRender', 'is_notation_usable'     ), 10, 2 );
add_filter ('scorerender_prog_and_file_loc'   , array( 'abcRender', 'program_setting_entry'  ) );
add_filter ('scorerender_define_setting_type' , array( 'abcRender', 'define_setting_type'    ) );
add_filter ('scorerender_define_setting_value', array( 'abcRender', 'define_setting_value'   ) );

/* vim: set cindent foldmethod=marker : */
?>
