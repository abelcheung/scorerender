<!DOCTYPE
 html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
 "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<?php
	// plugin file should be either 2 or 3 levels from WP top dir
	if (file_exists ('../../../wp-config.php'))
		require_once ('../../../wp-config.php');
	elseif (file_exists ('../../wp-config.php'))
		require_once ('../../wp-config.php');

	if ( isset ($_GET['code']) )
		$code = $_GET['code'];

	if ( get_magic_quotes_gpc() )
		$code = stripslashes ($code);
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php _e('Music fragment') ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo get_option ('blog_charset'); ?>" />
</head>

<script type="text/javascript">
<!--
window.focus();

function Copy()
{
	text="<?php echo addcslashes ($code, "\n\r\"\\"); ?>";
	if (window.clipboardData) {	// IE, Opera
		window.clipboardData.setData("Text", text);
	} else {
		document.getElementById('copybutton').disabled = true;
		document.getElementById('error_msg').innerHTML = '<?php _e('Not supported yet in Mozilla family browsers. Please select and copy the code manually.') ?>';
	}
}
-->
</script>
<body>
<button id="copybutton" onclick="Copy();"><?php _e('Copy to Clipboard') ?></button><span id="error_msg"></span><br />
<div id="code">
<pre>
<?php
	if ( !empty ($code) ) {
		echo strip_tags (urldecode ($code));
	} else {
		_e("Nothing to see here.\n");
	}
?>
</pre>
</div>
</body>
</html>
