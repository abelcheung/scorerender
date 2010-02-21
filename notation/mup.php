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
 * Inherited from ScoreRender class, for supporting Mup notation.
 * @package ScoreRender
*/
class mupRender extends ScoreRender
                implements ScoreRender_Notation
{

/**
 * Registration key for MUP.
 *
 * @access private
 */
private $reg_key;

/**
 * Class constructor, for adding wordpress hook to perform notation specific
 * setup
 * @uses set_notation_action()
 * @access private
 */
function __construct ()
{
	add_action ('sr_set_class_variable', array (&$this, 'set_notation_action'));
}

/**
 * Refer to {@link ScoreRender::set_img_width() parent method}
 * for more detail.
 * For Mup notation, it is more convenient to use inch as unit
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);
	$this->img_max_width /= DPI;
}

/**
 * Checks if given content is invalid or dangerous content
 *
 * @return boolean True if content is deemed safe
 */
protected function is_valid_input ()
{
	$blacklist = array
	(
		'/^\s*\binclude\b/', '/^\s*\bfontfile\b/'
	);

	foreach ($blacklist as $pattern)
		if (preg_match ($pattern, $this->_input))
			return false;

	return true;
}

/**
 * Refer to {@link ScoreRender_Notation::get_music_fragment() interface method}
 * for more detail.
 */
public function get_music_fragment ()
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
	return $header . "\n" . $this->_input;
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method}
 * for more detail.
 *
 * @uses is_windows()
 * @uses $temp_dir For storing temporary copy of magic file
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
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
	$temp_magic_file = $this->temp_dir . ( is_windows() ? '\mup.ok' : '/.mup' );
	
	if ( ! file_exists ($temp_magic_file) )
		file_put_contents ( $temp_magic_file, $this->reg_key );

	/* mup forces this kind of crap */
	putenv ("HOME=" . $this->temp_dir);
	chdir ($this->temp_dir);
	
	$cmd = sprintf ('"%s" -f "%s" "%s"',
			$this->mainprog,
			$intermediate_image, $input_file);
	$retval = $this->_exec($cmd);

	unlink ($temp_magic_file);
	
	return (filesize ($intermediate_image) != 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// FIXME: mind boggling exercise: why ImageMagick identifies PostScript produced by Mup as having
	// transparency on Windows, yet otherwise on Linux?
	// FIXME: 2. more exercise: when is it interpreted as having transparency on Linux too?
	return parent::conversion_step2 ($intermediate_image, $final_image, true);
}


/**
 * Perform any notation specific action
 *
 * {@internal OK, I cheated. Shouldn't have been leaking external
 * config option names into class, but this can help saving me
 * headache in the future}}
 *
 * @since 0.2.50
 */
public function set_notation_action ($options)
{
	if ( isset ( $options['MUP_REG_KEY'] ) )
		$this->reg_key = $options['MUP_REG_KEY'];
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
	foreach ($notations['mup']['progs'] as $setting_name => $program)
		if ( ! empty ($opt[$setting_name]) && ! parent::is_prog_usable (
			$program['test_output'], $opt[$setting_name], $program['test_arg']) )
				$ok = false;
			
	if (!$ok) $errmsgs[] = 'mup_bin_problem';
}

/**
 * Refer to {@link ScoreRender_Notation::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	global $notations;

	$adm_msgs['mup_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['mup']['name'])
	);
}

/**
 * Refer to {@link ScoreRender_Notation::program_setting_entry() interface method}
 * for more detail.
 */
public static function program_setting_entry ($output)
{
	global $notations;

	foreach ($notations['mup']['progs'] as $setting_name => $program)
		$output .= parent::program_setting_entry (
			$program['prog_name'], $setting_name);

	$output .= parent::program_setting_entry (
		'', 'MUP_REG_KEY',
		sprintf (__('%s registration key:', TEXTDOMAIN), '<code>mup</code>'),
		sprintf (__('Leave it empty if you have not <a href="%s">registered</a> Mup.', TEXTDOMAIN),
			'http://www.arkkra.com/doc/faq.html#payment')
	);
	return $output;
}

/**
 * Refer to {@link ScoreRender_Notation::define_setting_type() interface method}
 * for more detail.
 */
public static function define_setting_type ($settings)
{
	global $notations;

	foreach ($notations['mup']['progs'] as $key => $value)
		$settings[$key] = $value;
}

/**
 * Refer to {@link ScoreRender_Notation::define_setting_value() interface method}
 * for more detail.
 */
public static function define_setting_value ($settings)
{
	global $notations;

	$binary_name = $notations['mup']['progs']['MUP_BIN']['prog_name'];
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

	$settings['MUP_BIN']['value'] = empty ($fullpath) ? '' : $fullpath;
}

}  // end of class


$notations['mup'] = array (
	'name'        => 'Mup',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-mup/',
	'regex'       => '~\[mup\](.*?)\[/mup\]~si',
	'starttag'    => '[mup]',
	'endtag'      => '[/mup]',
	'classname'   => 'mupRender',
	'progs'       => array (
		'MUP_BIN' => array (
			'prog_name' => 'mup',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '-v',
			'test_output' => 'Arkkra Enterprises',
		),
	),
);


add_action ('scorerender_define_adm_msgs',
	array( 'mupRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'mupRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'mupRender', 'program_setting_entry' ) );

add_filter ('scorerender_define_setting_type',
	array( 'mupRender', 'define_setting_type' ) );

add_filter ('scorerender_define_setting_value',
	array( 'mupRender', 'define_setting_value' ) );
?>
