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

include_once ('scorerender-ext-scripts.inc');

check_param ( array (
	'color' => '/^#?([0-9A-Fa-f]{6})$/',
	'img'   => '/^sr-\w+-[0-9A-Fa-f]{32}\.png$/',
) );
$color = hexdec ( urldecode ($_GET['color']) );

// this file must be either 3 or 4 levels from WP top dir
if (file_exists ('../../../../wp-config.php'))
	require_once ('../../../../wp-config.php');
elseif (file_exists ('../../../wp-config.php'))
	require_once ('../../../wp-config.php');
else
	exit_and_dump_error ("Failed to locate config");

if ( !function_exists ('get_option') )
	exit_and_dump_error ("Crucial Wordpress function not found");

$sr_settings = get_option ('scorerender_options');
if ( empty ($sr_settings) )
	exit_and_dump_error ("Failed to retrieve WP config");

if ( !empty ($sr_settings['CACHE_DIR']) )
	$file = $sr_settings['CACHE_DIR'] . '/' . $_GET['img'];
else
{
	$upload_dir = wp_upload_dir();
	$file = $upload_dir['basedir'] . '/' . $_GET['img'];
}
check_file_existance ($file);

// short circuit for better caching
// TODO: check etag too
$mtime = filemtime ( $file );
if ( @strtotime ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) == $mtime )
{
	header ( 'x', true, 304 );
	exit (0);
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
	exit_and_dump_error ("Failed to create image resource");


imagealphablending ($img, false);
imagesavealpha ($img, true);
convertcolor ($img, $color);

header ("Content-type: image/png");
header ("Content-Transfer-Encoding: binary");
header ("Cache-Control: public");
// TODO: print etag header too
header ("Last-modified: " . date ('D, d M Y H:i:s T', filemtime ($file)));
imagepng ($img);
imagedestroy ($img);
?>
