<!DOCTYPE
 html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
 "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<?php
	// plugin file should be either 2 or 3 levels from WP top dir
	if (file_exists ('../../../wp-config.php'))
		require_once ('../../../wp-config.php');
	elseif (file_exists ('../../wp-config.php'))
		require_once ('../../wp-config.php');

	if ( isset ($_POST['code']) )
		$code = $_POST['code'];

	if ( get_magic_quotes_gpc() )
		$code = stripslashes ($code);
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php _e('Music fragment') ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo get_option ('blog_charset'); ?>" />
<link rel="stylesheet" type="text/css" href="showcode.css" />
</head>

<script type="text/javascript">
<!--
window.focus();

function Copy()
{
	text="<?php echo addcslashes ($code, "\n\r\"\\"); ?>";
	if (window.clipboardData) {	// IE, Opera
		window.clipboardData.setData("Text", text);
		document.getElementById('sr_message').innerHTML = '<?php _e('Successfully copied to clipboard.') ?>';
	} else {
		document.getElementById('copybutton').disabled = true;
		document.getElementById('sr_message').innerHTML = '<?php _e('Cut and paste not supported yet in Mozilla family browsers. Please select and copy the code below manually.') ?>';
	}
}
-->
</script>
<body>
<div id="sr_message"></div>
<button id="copybutton" onclick="Copy();"><?php _e('Copy to Clipboard') ?></button><br />
<h3><?php _e('Code:') ?></h3>
<div id="code">
<pre>
<?php
	if ( !empty ($code) ) {
		echo strip_tags (rawurldecode ($code));
	} else {
		_e("Nothing to see here.\n");
	}
?>
</pre>
</div>
</body>
</html>
