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

/**
 * Check if a file is MIDI audio
 *
 * Since file info checking functionality is only available on PHP 5.3,
 * it can't be used here.
 *
 * @since 0.3.50
 * @param string $file File to be checked
 * @return bool True if file conforms to MIDI format, False otherwise
 */
function is_midi_file ($file) /* {{{ */
{
	// too small
	if ( filesize ($file) <= 18 ) return false;
	$data = substr ( file_get_contents ($file), 0, 18 );

	$array = unpack ('a4head/Nhdrlen/nformat/ntracks/ntempo/a4hdrtrk', $data);

	return ( ( $array['head'  ] == "MThd" ) &&
	         ( $array['hdrlen'] == 6      ) &&
	         ( $array['hdrtrk'] == "MTrk" ) );
} /* }}} */


check_param ( array(
	'file' => '/^sr-\w+-[0-9A-Fa-f]{32}\.mid$/',
) );
$file = $_GET['file'];

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
	$dir = $sr_settings['CACHE_DIR'];
else
{
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
}
$fullpath = $dir . '/' . $file;
check_file_existance ($fullpath);

if ( !is_midi_file ($fullpath) )
	exit_and_dump_error ($file . " is not a MIDI file");

// short circuit for better caching
// TODO: check etag too
$mtime = filemtime ( $fullpath );
if ( @strtotime ( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) == $mtime )
{
	header ('x', true, 304);
	exit (0);
}

header ("Content-type: audio/midi");
header ("Content-Disposition: attachment; filename=" . basename ($fullpath));
header ("Content-Transfer-Encoding: binary");
header ("Cache-Control: public");
header ("Content-Length: " . filesize ($fullpath) );
// TODO: print etag header too
header ("Last-modified: " . date ('D, d M Y H:i:s T', filemtime ($fullpath)));

readfile ($fullpath);
?>
