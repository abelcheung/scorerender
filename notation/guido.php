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
 * Inherited from SrNotationBase class, for supporting GUIDO notation.
 * @package ScoreRender
*/
class guidoRender extends SrNotationBase
                  implements SrNotationInterface
{

/**
 * Refer to {@link SrNotationBase::get_music_fragment() parent method} for more detail.
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
	/*
	 * Staff height (px) = zoom*40-1; under this zoom ratio,
	 * so-called '1cm' = 30px in the image, and staff height = 24px.
	 * Under this setting the staff line is more solid.
	 * Besides, 1cm is used for left and right margin (already counted
	 * in page width), while left margin is occupied in advertising clause,
	 * right margin would be cropped later, thus add 1am to the width.
	 */
	$url = sprintf ('%s?defpw=%fcm;defph=%fcm;zoom=%f;crop=yes;gmndata=%s',
			'http://clef.cs.ubc.ca/scripts/salieri/gifserv.pl',
			$this->img_max_width / 30 + 1, 100.0, 0.625,
			rawurlencode (file_get_contents ($input_file)));

	return (@copy ($url, $intermediate_image));
}

/**
 * Refer to {@link SrNotationBase::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	/*
	 * The conversion from non-transparent GIF to transparent PNG was
	 * successful if there were a resizing as it was done in earlier
	 * version of ScoreRender. But now without resizing transparency
	 * is not enabled in pixels despite the -alpha option. Therefore
	 * some other operations must be performed to re-enable transparency
	 * in pixels; changing colorspace is one of them (but only selected
	 * few colorspaces!)
	 */
	return parent::conversion_step2 ( $intermediate_image,
			$final_image, FALSE, '-colorspace cmyk -shave 1x1' );
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
 * Refer to {@link SrNotationInterface::is_notation_usable() interface method}
 * for more detail.
 */
public function is_notation_usable ($errmsgs = null, $opt)
{
	if ( isset ($this) && get_class ($this) == __CLASS__ )
		return true;
}

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
	'classname'   => 'guidoRender',
	'progs'       => array (),
);

?>
