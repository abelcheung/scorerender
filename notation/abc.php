<?php
/**
 * Implements rendering of ABC notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting ABC notation.
 * @package ScoreRender
*/
class abcRender extends ScoreRender
{

private $width;

/**
 * Set maximum width of generated images
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.2.50
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);

	// Seems abcm2ps is using something like 120 dpi,
	// with 72DPI the notes and letters are very thin :(
	$this->width = $this->img_max_width / 120;
}

/**
 * Refer to {@link ScoreRender::get_music_fragment() parent method} for more detail.
 */
public function get_music_fragment ()
{
	$header = <<<EOT
%abc
%%staffwidth {$this->width}in
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
	foreach ($notations['abc']['progs'] as $prog)
		if ( ! empty ($opt[$prog]) && ! parent::is_prog_usable ('abcm2ps', $opt[$prog], '-V') )
			$ok = false;
			
	if (!$ok) $errmsgs[] = 'abcm2ps_bin_problem';
}

/**
 * Define any additional error or warning messages if settings for notation
 * has any problem.
 */
public static function define_admin_messages (&$adm_msgs)
{
	global $notations;

	$adm_msgs['abcm2ps_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['abc']['name'])
	);
}

} // end of class


$notations['abc'] = array (
	'regex'       => '~\[abc\](.*?)\[/abc\]~si',
	'starttag'    => '[abc]',
	'endtag'      => '[/abc]',
	'classname'   => 'abcRender',
	'progs'       => array ('ABCM2PS_BIN'),
	'url'         => 'http://abcnotation.org.uk/',
	'name'        => 'ABC',
);


add_action ('scorerender_define_adm_msgs',
	array( 'abcRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'abcRender', 'is_notation_usable' ), 10, 2 );
?>
