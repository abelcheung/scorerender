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
	error_log ('ScoreRender (' . basename($_SERVER['PHP_SELF']) . '): ' . $string . "\n");
	header ( 'x', true, $httpstatus);
	exit (1);
}

function check_param ($params)
{
	foreach ((array)$params as $param => $regex)
	{
		if ( !array_key_exists ( $param, $_GET ) )
			exit_and_dump_error ("Essential parameter not provided: '{$param}'");

		if ( !preg_match ($regex, urldecode ($_GET[$param]) ) )
			exit_and_dump_error ("Incorrect value for parameter '{$param}': '{$_GET[$param]}'");
	}
}

function check_file_existance ($file)
{
	if ( !file_exists ($file) )
		exit_and_dump_error ("File not found: '$file'", 404);

	if ( !is_readable ($file) )
		exit_and_dump_error ("File not readable: '$file'", 403);
}

?>
