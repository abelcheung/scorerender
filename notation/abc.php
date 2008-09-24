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
 * Check if given program is abcm2ps, and whether it is usable.
 *
 * @param string $args A CGI-like query string containing the program to be checked.
 * @uses is_prog_usable()
 * @return boolean Return true if the given program is abcm2ps AND it is executable.
 */
public function is_notation_usable ($args = '')
{
	wp_parse_str ($args, $r);
	extract ($r, EXTR_SKIP);
	return parent::is_prog_usable ('abcm2ps', $prog, '-V');
}

} // end of class

?>
