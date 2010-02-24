<?php
/**
 * A customized version of shortcode functions specifically for use in
 * ScoreRender, taken from WP 2.9.2. The version contained in WP 2.6-2.7
 * is incapable of parsing shortcode tag into callback function.
 *
 * @package ScoreRender
 */

/**
 * Search content for shortcodes and filter shortcodes through their hooks.
 *
 * If there are no shortcode tags defined, then the content will be returned
 * without any filtering. This might cause issues when plugins are disabled but
 * the shortcode will still show up in the post or content.
 *
 * @since 0.3.50
 * @uses $shortcode_tags
 * @uses scorerender_get_shortcode_regex() Gets the search pattern for searching shortcodes.
 *
 * @param string $content Content to search for shortcodes
 * @param int $limit Maximum number of shortcode replacements, default is no limit
 * @return string Content with shortcodes filtered out.
 */
function scorerender_do_shortcode($content, $limit = -1) {
	global $shortcode_tags;

	if (empty($shortcode_tags) || !is_array($shortcode_tags))
		return $content;

	$pattern = scorerender_get_shortcode_regex();
	$limit = (int)$limit;
	return preg_replace_callback('/'.$pattern.'/s', 'scorerender_do_shortcode_tag', $content, $limit);
}

/**
 * Retrieve the shortcode regular expression for searching.
 *
 * The regular expression combines the shortcode tags in the regular expression
 * in a regex class.
 *
 * The regular expresion contains 6 different sub matches to help with parsing.
 *
 * 1/6 - An extra [ or ] to allow for escaping shortcodes with double [[]]
 * 2 - The shortcode name
 * 3 - The shortcode argument list
 * 4 - The self closing /
 * 5 - The content of a shortcode when it wraps some content.
 *
 * @since 0.3.50
 * @uses $shortcode_tags
 *
 * @return string The shortcode search regular expression
 */
function scorerender_get_shortcode_regex() {
	global $shortcode_tags;
	$tagnames = array_keys($shortcode_tags);
	$tagregexp = join( '|', array_map('preg_quote', $tagnames) );

	return '(.?)\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
}

/**
 * Regular Expression callable for do_shortcode() for calling shortcode hook.
 * @see get_shortcode_regex for details of the match array contents.
 *
 * @since 0.3.50
 * @access private
 * @uses $shortcode_tags
 *
 * @param array $m Regular expression match array
 * @return mixed False on failure.
 */
function scorerender_do_shortcode_tag($m) {
	global $shortcode_tags;

	// allow [[foo]] syntax for escaping a tag
	if ($m[1] == '[' && $m[6] == ']') {
		return substr($m[0], 1, -1);
	}

	$tag = $m[2];
	$attr = scorerender_shortcode_parse_atts($m[3]);

	if ( isset($m[5]) ) {
		// enclosing tag - extra parameter
		return $m[1] . call_user_func($shortcode_tags[$tag], $attr, $m[5], $m[2]) . $m[6];
	} else {
		// self-closing tag
		return $m[1] . call_user_func($shortcode_tags[$tag], $attr, NULL, $m[2]) . $m[6];
	}
}

/**
 * Retrieve all attributes from the shortcodes tag.
 *
 * The attributes list has the attribute name as the key and the value of the
 * attribute as the value in the key/value pair. This allows for easier
 * retrieval of the attributes, since all attributes have to be known.
 *
 * @since 0.3.50
 *
 * @param string $text
 * @return array List of attributes and their value.
 */
function scorerender_shortcode_parse_atts($text) {
	$atts = array();
	$pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
	$text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);
	if ( preg_match_all($pattern, $text, $match, PREG_SET_ORDER) ) {
		foreach ($match as $m) {
			if (!empty($m[1]))
				$atts[strtolower($m[1])] = stripcslashes($m[2]);
			elseif (!empty($m[3]))
				$atts[strtolower($m[3])] = stripcslashes($m[4]);
			elseif (!empty($m[5]))
				$atts[strtolower($m[5])] = stripcslashes($m[6]);
			elseif (isset($m[7]) and strlen($m[7]))
				$atts[] = stripcslashes($m[7]);
			elseif (isset($m[8]))
				$atts[] = stripcslashes($m[8]);
		}
	} else {
		$atts = ltrim($text);
	}
	return $atts;
}

?>
