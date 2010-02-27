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

function exit_and_dump_error ($string, $status)
{
	error_log ('ScoreRender (tint-image.php): ' . $string . "\n");
	exit ($status);
}

// this file must be either 3 or 4 levels from WP top dir
if (file_exists ('../../../../wp-config.php'))
	require_once ('../../../../wp-config.php');
elseif (file_exists ('../../../wp-config.php'))
	require_once ('../../../wp-config.php');
else
	exit_and_dump_error ("Failed to locate config", 1);

if ( !function_exists ('get_option') )
	exit_and_dump_error ("Crucial Wordpress function not found", 2);

$settings = get_option ('scorerender_options');
if ( empty ($settings) )
	exit_and_dump_error ("Failed to retrieve WP config", 2);

if ( array_key_exists ('color', $_GET) )
	$hexcolor = strtoupper (urldecode ($_GET['color']));
else
{
	if ( !array_key_exists ('NOTE_COLOR', $settings) )
		exit_and_dump_error ("Can't determine color from config", 3);
	$hexcolor = strtoupper ($settings['NOTE_COLOR']);
}

if ( !preg_match ('/^#?([0-9A-F]{6})$/', $hexcolor) )
	exit_and_dump_error ("Incorrect color format: '$hexcolor''", 4);
$color = hexdec ($hexcolor);

if ( !array_key_exists ('img', $_GET) )
	exit_and_dump_error ("Image name not supplied", 5);

if ( !preg_match ('/^sr-\w+-[0-9A-Fa-f]{32}\.png$/', $_GET['img']) )
	exit_and_dump_error ("Image name format incorrect", 6);

$file = '';
if ( !empty ($settings['CACHE_DIR']) )
{
	$file = $settings['CACHE_DIR'] . '/' . $_GET['img'];
	if ( !file_exists ($file) )
		exit_and_dump_error ("Image not found: '$file''", 7);
}
else
{
	$upload_dir = wp_upload_dir();
	$file = $upload_dir['basedir'] . '/' . $_GET['img'];
	if ( !file_exists ($file) )
		exit_and_dump_error ("Image not found: '$file''", 7);
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
if ( !is_resource ($img) )
	exit_and_dump_error ("Failed to create image resource", 8);


imagealphablending ($img, false);
imagesavealpha ($img, true);
convertcolor ($img, $color);

header ('Content-type: image/png');
imagepng ($img);
imagedestroy ($img);
?>
