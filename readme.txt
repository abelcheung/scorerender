=== ScoreRender ===
Contributors: abelcheung
Tags: music, music notation, music typesetting, score, abc, mup, lilypond, guido, pmw
Requires at least: 2.2
Tested up to: 2.7
Stable tag: scorerender-0-2-1

Renders inline sheet music fragments in post, pages and comments.

== Description ==

ScoreRender is a Wordpress plugin for rendering sheet music fragments into images.  It supports converting fragments in excerpts, posts, pages and (optionally) comments.  Currently it supports 5 music notations: ABC, Guido, Lilypond, Mup and Philip's Music Writer.

ScoreRender started its life from Chris Lamb’s FigureRender plugin, which is a Wordpress plugin for rendering LaTeX and Lilypond music fragments into images. Its maintainership changed later. While continue enhancing FigureRender, all LaTeX related functionalities are submitted to [LatexRender](http://sixthform.info/steve/wordpress/), thus preserving this plugin for music rendering only and the rename.

For latest version, detailed usage instructions and demo cases, please visit [ScoreRender official site](http://scorerender.abelcheung.org/).

== Installation ==

= Prerequisite =
1. Starting from ScoreRender 0.2, PHP4 compatibility is dropped, and PHP5 is strictly needed.
2. Starting from ScoreRender 0.2, ImageMagick >= 6.3.6-2 is needed, due to usage of -flatten option.
3. Music rendering programs must also be installed on the same machine web server is running. For example, to support Lilypond fragments, Lilypond >= 2.8.1 must be installed in web server. Refer to [installation page](http://scorerender.abelcheung.org/installation/) for more detail.

= New install =
1. Install any prerequisite programs as noted above.
2. Extract archive, and copy 'scorerender' folder to wp-content/plugins/.
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

Since 0.2, [abcm2ps](http://moinejf.free.fr/) will be the only one supported. This is a design decision. If you REALLY want to use other similar programs, you are on your own, though modifying the code to support others is not very hard. Take a look at is_notation_usable() method in class.abc.inc.php.

= I want to remove cache for 1 image and re-render, but how can I determine which is which? =

Right now you have to view HTML source to find out cache image file name. Management of cache is planned in future, but can't say when.

= Images using Guido notation seems blurred. =

This may not be fully fixable, because setting font attributes may not be possible for all text. After image resizing, they can be rendered smaller / larger than desired.

= How to debug my fragment when posting? =

Simply put, don't do that now if possible. There is no viable method for debugging a fragment yet. The best way is render it privately in your computer first, then post the content, rather than needlessly spending lots of time on trial and error.

= How can I install Philip's Music Writer? =

Only by downloading source from its official website and compile the program yourself. The author failed to notice any binary package for Windows or Linux as of Feb 2009.

Compiling and using PMW on Windows may only be possible through code changes.

== Screenshots ==

http://scorerender.abelcheung.org/screenshot/

== License ==
This plugin is released under GPL.

