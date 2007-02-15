<?php

/*
 FigureRender - Renders inline LaTeX, LilyPond and Mup figures in WordPress
 Copyright (C) 2006 Chris Lamb <chris@chris-lamb.co.uk>
 http://www.chris-lamb.co.uk/code/figurerender/

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


/*
 class.latexrender.inc.php
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Implements rendering of LaTeX figures in FigureRender.
*/

class LatexRender extends FigureRender {
	var $_uniqueID = "latex";

	function LatexRender($input, $options=array()) {

		$options = array_merge(
			array(
				'LATEX_BIN' => '/usr/bin/latex',
				'DVIPS_BIN' => '/usr/bin/dvips'
			),
			$options
		);

		parent::FigureRender($input, $options);
	}

	function isValidInput($input) {
		// From LatexRender source
		$blacklist = array(
			"include", "def", "command", "loop", "repeat", "open", "toks",
			"output", "input", "catcode", "name", "^^", "\\every", "\\errhelp",
			"\\errorstopmode", "\\scrollmode", "\\nonstopmode", "\\batchmode",
			"\\read", "\\write", "csname", "\\newhelp", "\\uppercase", "\\lowercase",
			"\\relax", "\\aftergroup", "\\afterassignment", "\\expandafter", "\\noexpand",
			"\\special"
		);

		for ($i=0; $i < count(blacklist); $i++) {

			if (stristr($input, $blacklist[$i])) {
				return false;
			}
                }

		return true;
	}

	function getInputFileContents($input)
	{
		return '\documentclass[12pt]{article}
			\usepackage[utf8]{inputenc}
			\usepackage{amsmath}
			\usepackage{amsfonts}
			\usepackage{amssymb}
			\pagestyle{empty}
			\begin{document}
			\[' . $input . '\]
			\end{document}
			';
	}

	function execute($input_file, $output_file)
	{
		$cmd = sprintf ('%s --interaction=nonstopmode %s 2>&1', $this->_options['LATEX_BIN'],
			$input_file);
		$retval = parent::_exec($cmd);

		if ($retval != 0)
			return false;

		$cmd = sprintf ('%s -E -o %s %s.dvi 2>&1', $this->_options['DVIPS_BIN'],
			$output_file, $input_file);
		$retval = parent::_exec($cmd);

		// Cleanup
		unlink ($input_file . '.dvi');
		unlink ($input_file . '.aux');
		unlink ($input_file . '.log');

		return ($retval == 0);
	}

}

?>
