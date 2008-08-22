=== ScoreRender ===
Contributors: abelcheung
Tags: music, score, abc, mup, lilypond, guido, scorerender, figurerender
Requires at least: 2.2
Tested up to: 2.6
Stable tag: scorerender-0-1-3

Renders inline sheet music fragments in post, pages and comments.

== Description ==

ScoreRender is a Wordpress plugin for rendering sheet music fragments into images.  It supports converting fragments in excerpts, posts, pages and (optionally) comments.  Currently it supports 4 music notations: ABC, Guido, Lilypond, Mup.

ScoreRender started its life from Chris Lamb’s FigureRender plugin, which is a Wordpress plugin for rendering LaTeX and Lilypond music fragments into images. Its maintainer changed later. While continue enhancing FigureRender, all LaTeX related functionalities are submitted to [LatexRender](http://sixthform.info/steve/wordpress/), thus preserving this plugin for music rendering only and the rename.

For latest version, detailed usage instructions and demo cases, please visit [ScoreRender official site](http://scorerender.abelcheung.org/).

== Installation ==

= New install =
1. Please make sure ImageMagick and other music rendering programs are installed in web server. For example, to support Lilypond fragments, Lilypond must be installed in web server. Refer to [this page](http://scorerender.abelcheung.org/installation/) for more detail.
2. extract archive, and copy this folder to wp-content/plugins/.
3. Login to WordPress and enable the plugin in admin interface.
4. Configure ScoreRender under the ScoreRender tab of the Options page.
5. In Option -> Writing, check if this option is turned on:

       "WordPress should correct invalidly nested XHTML automatically"

   It must be turned off if you intent to render Lilypond and Mup fragments,
   since this option will convert "<<" and ">>" into "< <" and "> >"
   correspondingly, thus destroying the music content and cause render error.

= Upgrade =
1. Deactivate the plugin in WordPress admin page.
2. Remove the whole plugin folder.
3. Upload new plugin and activate again.

== Frequently Asked Questions ==

= It just complains about some obscure error! =

The error code indicates the kind of error in some degree. There are comments inside wp-scorerender.php indicating what kind of error it is. If you can't make any heads and tails out of PHP code, feel free to ask me [through email](http://me.abelcheung.org/aboutme/).

= Is any ABC notation compatible program also supported? =

Though they MIGHT work, only [abcm2ps](http://moinejf.free.fr/) is tested to a great extent. All others might work or might not work well, especially regarding image sizing and transparency issues.

== Screenshots ==

http://scorerender.abelcheung.org/screenshot/

== License ==
This plugin is released under GPL.
