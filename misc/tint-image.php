<?php
/*
	Copyright (C) 2010 Abel Cheung

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as
	published by the Free Software Foundation, either version 3 of the
	License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

define ('SR_DEBUG', false);
$log = sys_get_temp_dir() . '/sr-tint-image.log';

// this file must be either 3 or 4 levels from WP top dir
if (file_exists ('../../../../wp-config.php'))
	require_once ('../../../../wp-config.php');
elseif (file_exists ('../../../wp-config.php'))
	require_once ('../../../wp-config.php');
else
{
	if (SR_DEBUG) file_put_contents ($log,
			strftime ('%x %X') . "\tFailed to locate config\n", FILE_APPEND);
	exit (1);
}

$settings = get_option ('scorerender_options');
if ( !$settings || !array_key_exists ('NOTE_COLOR', $settings) )
{
	if (SR_DEBUG) file_put_contents ($log,
			strftime ('%x %X') . "\tCan't determine color from config\n", FILE_APPEND);
	exit (2);
}

$hexcolor = strtoupper ($settings['NOTE_COLOR']);
if ( !preg_match ('/^#?([0-9A-F]{6})$/', $hexcolor, $matches) )
{
	if (SR_DEBUG) file_put_contents ($log,
			strftime ('%x %X') . "\tIncorrect color format\n", FILE_APPEND);
	exit (3);
}
$color = hexdec ($hexcolor);

if ( !isset ($_GET['img']) )
{
	if (SR_DEBUG) file_put_contents ($log,
			strftime ('%x %X') . "\tImage name not supplied\n", FILE_APPEND);
	exit (4);
}
if ( !preg_match ('/^sr-\w+-[0-9A-Fa-f]{32}\.png$/', $_GET['img']) )
{
	if (SR_DEBUG) file_put_contents ($log,
			strftime ('%x %X') . "\tImage name format incorrect\n", FILE_APPEND);
	exit (5);
}

$file = '';
if ( !empty ($settings['CACHE_DIR']) )
{
	$file = $settings['CACHE_DIR'] . '/' . $_GET['img'];
	if ( !file_exists ($file) ) {
		if (SR_DEBUG) file_put_contents ($log,
				strftime ('%x %X') . "\tImage not found\n", FILE_APPEND);
		exit (6);
	}
}
else
{
	$upload_dir = wp_upload_dir();
	$file = $upload_dir['basedir'] . '/' . $_GET['img'];
	if ( !file_exists ($file) ) {
		if (SR_DEBUG) file_put_contents ($log,
				strftime ('%x %X') . "\tImage not found\n", FILE_APPEND);
		exit (6);
	}
}

function convertcolor ( $img, $color )
{
	$w = imagesx ($img);
	$h = imagesy ($img);
	// loop through all image pixels and do in-place replacement
	for ( $x = 0; $x < $w; $x++ ) {
		for( $y = 0; $y < $h; $y++ ) {
			$colorxy = imagecolorat ( $img, $x, $y );
			$alpha = ( $colorxy >> 24 ) & 0xFF;
			$newcolor = imagecolorallocatealpha ( $img,
				( $color >> 16 ) & 0xFF,
				( $color >> 8  ) & 0xFF,
				$color & 0xFF,
				$alpha );
			imagesetpixel ( $img, $x, $y, $newcolor );
			imagecolordeallocate ( $img, $newcolor );
		}
	}
}

$img = imagecreatefrompng ($file);
if ( !is_resource ($img) ) {
	if (SR_DEBUG) file_put_contents ($log,
			strftime ('%x %X') . "\tFailed to create image resource\n", FILE_APPEND);
	exit (7);
}


imagealphablending ($img, false);
imagesavealpha ($img, true);
convertcolor ($img, $color);

header ('Content-type: image/png');
imagepng ($img);
imagedestroy ($img);
?>
