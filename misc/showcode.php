<?php
	// this file must be either 3 or 4 levels from WP top dir
	if (file_exists ('../../../../wp-config.php'))
		require_once ('../../../../wp-config.php');
	elseif (file_exists ('../../../wp-config.php'))
		require_once ('../../../wp-config.php');

	if ( isset ($_POST['code']) )
		$code = $_POST['code'];

	if ( get_magic_quotes_gpc() )
		$code = stripslashes ($code);

	define ('TEXTDOMAIN', 'scorerender');

?><!DOCTYPE
 html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
 "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php _e('Music fragment', TEXTDOMAIN) ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo get_option ('blog_charset'); ?>" />
<link rel="stylesheet" type="text/css" href="showcode.css" />
</head>

<body>

<script type="text/javascript" src="ZeroClipboard.js"></script>

<button id="copybutton"><?php _e('Copy to Clipboard', TEXTDOMAIN) ?></button><br />
<h3><?php _e('Code:', TEXTDOMAIN) ?></h3>
<div id="code">
<pre>
<?php
	if ( !empty ($code) )
		echo strip_tags (rawurldecode ($code));
	else
		_e("Nothing to see here.", TEXTDOMAIN);
?>
</pre>
</div>

<script language="JavaScript" type="text/javascript">
	var clip = new ZeroClipboard.Client();
	clip.setText( '' );
	clip.setHandCursor( true );

	clip.addEventListener( 'complete', function( client, text ) {
		alert( 'Music code is successfully copied to clipboard.' );
	} );

	clip.addEventListener( 'mousedown', function( client ) {
		clip.setText( "<?php echo addcslashes (html_entity_decode (rawurldecode ($code)), "\n\r\"\\"); ?>" );
	} );

	clip.glue( 'copybutton' );
</script>

</body>
</html>
