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
 * Inherited from SrNotationBase class, for supporting Lilypond notation.
 * @package ScoreRender
*/
class lilypondRender extends SrNotationBase
                     implements SrNotationInterface
{

const code = 'lilypond';

protected $lilypond_ver = '';

protected static $notation_data = array ( /* {{{ */
	'name'        => 'LilyPond',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-lilypond/',
	'classname'   => 'lilypondRender',
	'progs'       => array (
		'LILYPOND_BIN' => array (
			'prog_name'   => 'lilypond',
			'type'        => 'prog',
			'value'       => '',
			'test_arg'    => array ('--version'),
			'test_output' => '/^GNU LilyPond ([\d.-]+)/',
			'min_version' => '2.8.1',
			'error_code'  => 'lilypond_bin_problem',
		),
	),
); /* }}} */


/**
 * Refer to {@link SrNotationInterface::get_music_fragment() interface method}
 * for more detail.
 */
public function get_music_fragment () /* {{{ */
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

	// Basically impossible to judge the place to add \midi{} block,
	// it has to be placed within \score{} block AND after some
	// music content (placing it immediately after \score result in
	// syntax error). Thus no automatic adding of \midi{}.
	return normalize_linebreak ($header . "\n" . $this->input);
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step1() parent method} for more detail.
 */
protected function conversion_step1 () /* {{{ */
{
	$safemode = '';
	/* LilyPond SUCKS unquestionably. On 2.8 safe mode is triggered by "--safe" option,
	 * on 2.10.x it becomes "--safe-mode", and on 2.12.x that"s "-dsafe"!
	 */
	if ( version_compare ($this->lilypond_ver, '2.11.11', '<') )
		$safemode = '-s';
	else
		$safemode = '-dsafe';

	// lilypond insists adding .ps extension by itself, pointless for temp file
	// generation here
	$cmd = sprintf ('"%s" %s --ps "%s"',
		$this->mainprog, $safemode, $this->input_file);

	$retval = $this->_exec ($cmd);

	if ( 0 !== $retval ) return $retval;

	// CAVEAT: Temp files are created with .tmp suffix on Windows.
	// Lilypond outputs file name with ".tmp" replaced by ".ps" .
	// This is not a problem on Unix because temp files have no suffix.
	if ( is_windows() )
		$file = sprintf ( "%s/%s.ps", dirname ($this->input_file), basename ($this->input_file, '.tmp') );
	else
		$file = $this->input_file.'.ps';

	return file_exists ($file) ? $file : -1;
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step2() parent method}
 * for more detail.
 */
protected function conversion_step2 ($intermediate_image)
{
	// default staff size for lilypond is 20px, expected 24px, a ratio of 1.2:1
	// and 72*1.2 = 86.4
	return parent::conversion_step2 ($intermediate_image, TRUE, '-equalize -density 86');
}

/**
 * Refer to {@link SrNotationBase::get_midi() parent method} for more detail.
 */
protected function get_midi ( $attr ) /* {{{ */
{
	/* Once correct \midi block is inserted, lilypond UNCONDITIONALLY generate MIDI
	 * file regardless of settings or command line options or whatever. Therefore
	 * it must be cached upon first rendering, there is no way to get MIDI file afterwards.
	 */
	$midifile = $this->input_file . ".midi";
	return file_exists ($midifile) ? $midifile : false;
} /* }}} */

/**
 * Refer to {@link SrNotationInterface::is_notation_usable() interface method}
 * for more detail.
 *
 * @uses SrNotationBase::is_prog_usable()
 */
public function is_notation_usable ($errmsgs = null, $opt) /* {{{ */
{
	static $ok;

	if ( !isset ($ok) )
	{
		$ok = true;
		foreach (self::$notation_data['progs'] as $setting_name => $progdata)
		{
			if ( 'prog' !== $progdata['type'] ) continue;

			if ( empty ($opt[$setting_name]) )
			{
				$ok = false;
				break;
			}
			$lily_ver = '';
			$result = parent::is_prog_usable ( $progdata['test_output'], $opt[$setting_name],
					$progdata['test_arg'], $progdata['min_version'], 1, $lily_ver );
			if ( is_wp_error ($result) || !$result )
			{
				$ok = false;
				break;
			}
			// FIXME: hackish, if more than one program this won't work
			if ( isset ($this) && get_class ($this) == __CLASS__ )
				$this->lilypond_ver = $lily_ver;
		}

		if (!$ok)
			if ( !is_null ($errmsgs) ) $errmsgs[] = $progdata['error_code'];
	}

	if ( isset ($this) && get_class ($this) == __CLASS__ )
		return $ok;
} /* }}} */

/**
 * Refer to {@link SrNotationInterface::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	$adm_msgs['lilypond_bin_problem'] = array (
		'level' => MSG_WARNING,
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
public static function define_setting_value ($settings) /* {{{ */
{
	foreach ( self::$notation_data['progs'] as $setting_name => $progdata )
	{
		$binary_name = $progdata['prog_name'];
		$fullpath = search_prog ( $binary_name,
			array ("C:\\Program Files\\LilyPond*\\usr\\bin"), array() );

		$settings[$setting_name]['value'] = $fullpath ? $fullpath : '';
	}
} /* }}} */

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


add_action ('scorerender_register_notations'  , array( 'lilypondRender', 'register_notation_data' ) );
add_action ('scorerender_define_adm_msgs'     , array( 'lilypondRender', 'define_admin_messages'  ) );
add_action ('scorerender_check_notation_progs', array( 'lilypondRender', 'is_notation_usable'     ), 10, 2 );
add_filter ('scorerender_prog_and_file_loc'   , array( 'lilypondRender', 'program_setting_entry'  ) );
add_filter ('scorerender_define_setting_type' , array( 'lilypondRender', 'define_setting_type'    ) );
add_filter ('scorerender_define_setting_value', array( 'lilypondRender', 'define_setting_value'   ) );

/* vim: set cindent foldmethod=marker : */
?>
