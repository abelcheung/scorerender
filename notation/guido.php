<?php
/**
 * Implements rendering of GUIDO notation in ScoreRender.
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung <abelcheung at gmail dot com>
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
*/

/**
 * Inherited from ScoreRender class, for supporting GUIDO notation.
 * @package ScoreRender
*/
class guidoRender extends ScoreRender
                  implements ScoreRender_Notation
{

/**
 * Refer to {@link ScoreRender::get_music_fragment() parent method} for more detail.
 */
public function get_music_fragment ()
{
	return $this->_input;
}

/**
 * Retrieve score fragment image from GUIDO server.
 *
 * Though retrieved image is in GIF format, it is still named 'xxx.ps'
 * for simplicity (and luckily ImageMagick doesn't care source image extension)
 *
 * @param string $input_file File name of raw input file containing music content
 * @param string $intermediate_image File name of rendered PostScript file
 * @return boolean Whether image can be downloaded from GUIDO server
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	// 1.125 = 72/64; guido server use 64pixel per cm
	$url = sprintf ('%s?defpw=%fcm;defph=%fcm;zoom=%f;crop=yes;gmndata=%s',
			'http://clef.cs.ubc.ca/scripts/salieri/gifserv.pl',
			$this->img_max_width / DPI * 2.54, 100.0, 1.125,
			rawurlencode (file_get_contents ($input_file)));

	return (@copy ($url, $intermediate_image));
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// Under Windows, percent sign must be escaped
	return parent::conversion_step2 ($intermediate_image, $final_image, FALSE,
		(is_windows())? '-shave 1x1 -geometry 56%%' : '-shave 1x1 -geometry 56%');
}

/**
 * Check if file access functions can handle URL.
 *
 * @return boolean Return true if remote URL can be fopen'ed.
 */
/*
public function is_notation_usable ($args = '')
{
	return ini_get('allow_url_fopen');
}
 */

/**
 * @ignore
 */
public static function is_notation_usable ($errmsgs, $opt) {}

/**
 * @ignore
 */
public static function define_admin_messages ($adm_msgs) {}

/**
 * @ignore
 */
public static function program_setting_entry ($output) {}

/**
 * @ignore
 */
public static function define_setting_type ($settings) {}

/**
 * @ignore
 */
public static function define_setting_value ($settings) {}

} // end of class


$notations['guido'] = array (
	'name'        => 'GUIDO',
	'url'         => 'http://scorerender.abelcheung.org/demo/demo-guido/',
	'regex'       => '~\[guido\](.*?)\[/guido\]~si',
	'starttag'    => '[guido]',
	'endtag'      => '[/guido]',
	'classname'   => 'guidoRender',
	'progs'       => array (),
);

?>
