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

function exit_and_dump_error ($string, $httpstatus = 204)
{
	error_log ('ScoreRender (get-midi.php): ' . $string . "\n");
	header ( 'x', true, $httpstatus);
	exit (1);
}

// this file must be either 3 or 4 levels from WP top dir
if (file_exists ('../../../../wp-config.php'))
	require_once ('../../../../wp-config.php');
elseif (file_exists ('../../../wp-config.php'))
	require_once ('../../../wp-config.php');
else
	exit_and_dump_error ("Failed to locate config");

if ( !function_exists ('get_option') )
	exit_and_dump_error ("Crucial Wordpress function not found");

$settings = get_option ('scorerender_options');
if ( empty ($settings) )
	exit_and_dump_error ("Failed to retrieve WP config");

if ( !array_key_exists ('file', $_GET) )
	exit_and_dump_error ("MIDI file name not supplied");

if ( !preg_match ('/^sr-\w+-[0-9A-Fa-f]{32}\.mid$/', $_GET['file']) )
	exit_and_dump_error ("MIDI file name format incorrect");

if ( !empty ($settings['CACHE_DIR']) )
	$dir = $settings['CACHE_DIR'];
else
{
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
}
$file = $dir . '/' . $_GET['file'];
if ( !file_exists ($file) )
	exit_and_dump_error ("File not found: '$file'", 404);

if ( !is_readable ($file) )
	exit_and_dump_error ("File not readable: '$file'", 403);

// short circuit for better caching
// TODO: check etag too
$mtime = filemtime ( $file );
if ( @strtotime ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) == $mtime )
{
	header ('x', true, 304);
	exit (0);
}

header ("Content-type: audio/midi");
header ("Content-Disposition: attachment; filename=" . basename ($file));
header ("Content-Transfer-Encoding: binary");
header ("Cache-Control: public");
header ("Content-Length: " . filesize ($file) );
// TODO: print etag header too
header ("Last-modified: " . date ('D, d M Y H:i:s T', filemtime ($file)));

readfile ($file);
?>
