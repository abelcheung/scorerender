<?php
/**
 * Implements rendering of Mup notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from SrNotationBase class, for supporting Mup notation.
 * @package ScoreRender
*/
class mupRender extends SrNotationBase
                implements SrNotationInterface
{

const code = 'mup';

protected static $notation_data = array ( /* {{{ */
	'name'        => 'Mup',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-mup/',
	'classname'   => 'mupRender',
	'progs'       => array (
		'MUP_BIN' => array (
			'prog_name'   => 'mup',
			'type'        => 'prog',
			'value'       => '',
			'test_arg'    => '-v',
			'test_output' => '/^Mup - Music Publisher\s+Version ([\d.]+)/',
			'error_code'  => 'mup_bin_problem',
		),
	),
); /* }}} */


/**
 * Registration key for MUP.
 *
 * @access private
 */
private $reg_key;

/**
 * Class constructor, for adding wordpress hook to perform notation specific
 * setup
 * @uses set_notation_variable()
 * @access private
 */
function __construct ()
{
	add_action ('sr_set_class_variable', array (&$this, 'set_notation_variable'));
}

/**
 * Refer to {@link SrNotationBase::set_img_width() parent method}
 * for more detail.
 * For Mup notation, it is more convenient to use inch as unit
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);
	$this->img_max_width /= 72;
}

/**
 * Checks if given content is invalid or dangerous content
 *
 * @return boolean True if content is deemed safe
 */
protected function is_valid_input () /* {{{ */
{
	$blacklist = array
	(
		'/^\s*\binclude\b/', '/^\s*\bfontfile\b/'
	);

	foreach ($blacklist as $pattern)
		if (preg_match ($pattern, $this->input))
			return false;

	return true;
} /* }}} */

/**
 * Refer to {@link SrNotationInterface::get_music_fragment() interface method}
 * for more detail.
 */
public function get_music_fragment () /* {{{ */
{
	$header = <<<EOD
//!Mup-Arkkra-5.0
score
leftmargin = 0
rightmargin = 0
topmargin = 0
bottommargin = 0
pagewidth = {$this->img_max_width}
label = ""
EOD;

	return normalize_linebreak ($header . "\n" . $this->input);
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step1() parent method} for more detail.
 *
 * @uses is_windows() For determining the registration key file name
 * @uses $temp_dir For storing temporary copy of registration key
 */
protected function conversion_step1 () /* {{{ */
{
	if ( false === ( $intermediate_image = tempnam ( getcwd(), '' ) ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', TEXTDOMAIN) );

	/*
	 * Mup requires a magic file before it is usable.
	 * On Unix this file is named ".mup", and must reside in $HOME or
	 * current working directory.  On Windows/DOS, it is named "mup.ok"
	 * instead, and located in current working directory or same location
	 * as mup.exe do.
	 * It must be present even if not registered, otherwise mup refuse to
	 * render anything.  Even worse, the exist status in this case is 0,
	 * so _exec() succeeds yet no postscript is rendered.
	 */
	$magic_file = is_windows() ? 'mup.ok' : '.mup';

	if ( false === @file_put_contents ( $magic_file, $this->reg_key, LOCK_EX ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', TEXTDOMAIN) );

	/* mup forces this kind of crap */
	putenv ( "HOME=" . getcwd() );

	$cmd = sprintf ('"%s" -f "%s" "%s"',
			$this->mainprog, $intermediate_image, $this->input_file);
	$retval = $this->_exec($cmd);

	// Mup return status can't be fully trusted
	if ( ( 0 !== $retval ) || !filesize ($intermediate_image) )
		return $retval;
	else
		return $intermediate_image;
} /* }}} */

/**
 * Refer to {@link SrNotationBase::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image)
{
	// A bug involving alpha channel in paletted PNG was fixed in 6.3.9-6;
	// seems it affects any paletted image and level 1 PostScript too?
	return parent::conversion_step2 ($intermediate_image,
			version_compare ( $this->imagick_ver, '6.3.9-6', '>=' ));
}


/**
 * Refer to {@link SrNotationBase::get_midi() parent method} for more detail.
 */
protected function get_midi () /* {{{ */
{
	if ( false === ( $temp_midifile = tempnam ( getcwd(), '' ) ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', TEXTDOMAIN) );

	$cmd = sprintf ('"%s" -m "%s" "%s"',
			$this->mainprog, $temp_midifile, $this->input_file);
	$retval = $this->_exec($cmd);

	if ( ( 0 !== $retval ) || !filesize ($temp_midifile) )
		return $retval;
	else
		return $temp_midifile;
} /* }}} */


/**
 * Perform any notation specific action
 *
 * {@internal OK, I cheated. Shouldn't have been leaking external
 * config option names into class, but this can help saving me
 * headache in the future}}
 *
 * @since 0.2.50
 */
public function set_notation_variable ($options)
{
	if ( isset ( $options['MUP_REG_KEY'] ) )
		$this->reg_key = $options['MUP_REG_KEY'];
}

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
	$adm_msgs['mup_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), self::$notation_data['name'])
	);
}

/**
 * Refer to {@link SrNotationInterface::program_setting_entry() interface method}
 * for more detail.
 */
public static function program_setting_entry ($output) /* {{{ */
{
	foreach (self::$notation_data['progs'] as $setting_name => $progdata)
		$output .= parent::program_setting_entry (
			$progdata['prog_name'], $setting_name);

	$output .= parent::program_setting_entry (
		'', 'MUP_REG_KEY',
		sprintf (__('%s registration key:', TEXTDOMAIN), '<code>mup</code>'),
		sprintf (__('Leave it empty if you have not <a href="%s">registered</a> Mup.', TEXTDOMAIN),
			'http://www.arkkra.com/doc/faq.html#payment')
	);
	return $output;
} /* }}} */

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
}

}  // end of class


add_action ('scorerender_register_notations'  , array( 'mupRender', 'register_notation_data' ) );
add_action ('scorerender_define_adm_msgs'     , array( 'mupRender', 'define_admin_messages'  ) );
add_action ('scorerender_check_notation_progs', array( 'mupRender', 'is_notation_usable'     ), 10, 2 );
add_filter ('scorerender_prog_and_file_loc'   , array( 'mupRender', 'program_setting_entry'  ) );
add_filter ('scorerender_define_setting_type' , array( 'mupRender', 'define_setting_type'    ) );
add_filter ('scorerender_define_setting_value', array( 'mupRender', 'define_setting_value'   ) );

/* vim: set cindent foldmethod=marker : */
?>
