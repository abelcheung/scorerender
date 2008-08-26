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

	define ('TEXTDOMAIN', 'scorerender');
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php _e('Music fragment', TEXTDOMAIN) ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo get_option ('blog_charset'); ?>" />
<link rel="stylesheet" type="text/css" href="showcode.css" />
</head>

<script language="javascript" type="text/javascript">
<!--
window.focus();

// http://www.krikkit.net/howto_javascript_copy_clipboard.html
// http://www.cnblogs.com/zhenyulu/archive/2007/02/08/644362.html
//
function CopyClipboard()
{
	if (window.clipboardData) // IE
	{
		var text="<?php echo addcslashes ($code, "\n\r\"\\"); ?>";
		window.clipboardData.clearData();
		window.clipboardData.setData("Text", text);
		document.getElementById('sr_message').innerHTML = '<?php _e('Successfully copied to clipboard.', TEXTDOMAIN) ?>';
	}
	else if (window.netscape && navigator.userAgent.indexOf("Opera") == -1) // Netscape or Mozilla family
	{
		try
		{
			netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");
		}
		catch (e)
		{
			document.getElementById('copybutton').disabled = true;
			document.getElementById('sr_message').innerHTML = '<?php _e('Automatic copy and paste is not enabled for this browser. Please copy and paste the code below manually.<br />For Gecko based browsers (e.g. Mozilla, Firefox, Flock), there is an alternative method: type &#8220;about:config&#8221; in browser address bar, find the preference &#8220;<code>signed.applets.codebase_principal_support</code>&#8221;, change its value to &#8220;true&#8221; and reload this page.', TEXTDOMAIN) ?>';
			return false;
		}

		var text = "<?php echo addcslashes (html_entity_decode (rawurldecode ($code)), "\n\r\"\\"); ?>";
		var clip = Components.classes['@mozilla.org/widget/clipboard;1'].createInstance(Components.interfaces.nsIClipboard); 
		if (!clip) 
			return; 
		var trans = Components.classes['@mozilla.org/widget/transferable;1'].createInstance(Components.interfaces.nsITransferable); 
		if (!trans) 
			return; 
		trans.addDataFlavor('text/unicode'); 
		var str = new Object(); 
		var len = new Object(); 
		var str = Components.classes["@mozilla.org/supports-string;1"].createInstance(Components.interfaces.nsISupportsString); 
		var copytext = text; 
		str.data = copytext; 
		trans.setTransferData("text/unicode",str,copytext.length*3); 
		var clipid = Components.interfaces.nsIClipboard; 
		if (!clip) 
			return false; 
		clip.setData(trans,null,clipid.kGlobalClipboard);

		document.getElementById('sr_message').innerHTML = '<?php _e('Successfully copied to clipboard.', TEXTDOMAIN) ?>';
	}
	else
	{
		document.getElementById('copybutton').disabled = true;
		document.getElementById('sr_message').innerHTML = '<?php _e('Cut and paste not supported yet on this browser. Please select and copy the code below manually.', TEXTDOMAIN) ?>';
		return false;
	}

}
// -->
</script>
<body>
<div id="sr_message"></div>
<button id="copybutton" onclick="return CopyClipboard();"><?php _e('Copy to Clipboard', TEXTDOMAIN) ?></button><br />
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
</body>
</html>
