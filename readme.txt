=== ScoreRender ===
Contributors: abelcheung
Tags: music, music notation, music typesetting, score, abc, mup, lilypond, guido, pmw
Requires at least: 2.2
Tested up to: 2.9.1
Stable tag: scorerender-0-3-2

Renders inline sheet music fragments in excerpts, posts, pages and comments.

== Description ==

ScoreRender is a Wordpress plugin for rendering sheet music fragments into images.  It supports converting fragments in excerpts, posts, pages and (optionally) comments.  Currently it supports 5 music notations: ABC, Guido, Lilypond, Mup and Philip's Music Writer.

For latest version, detailed usage instructions and demo cases, please visit [ScoreRender official site](http://scorerender.abelcheung.org/). Requires PHP5, ImageMagick and various programs to generate music score (except Guido notation).

== Installation ==

###Prerequisite###
1. Starting from ScoreRender 0.2, PHP4 compatibility is dropped, and PHP5 is strictly needed.
2. Starting from ScoreRender 0.2, ImageMagick >= 6.3.6-2 is needed, due to usage of `-flatten` option.
3. Music rendering programs must also be installed on the same machine web server is running. For example, to support Lilypond fragments, Lilypond >= 2.8.1 must be installed in web server. Refer to [installation page](http://scorerender.abelcheung.org/installation/) for more detail.

###New install###
1. Install any prerequisite programs as noted above.
2. Extract archive, and copy 'scorerender' folder to `wp-content/plugins/`, keeping the folder structure intact.
3. Login to WordPress and enable the plugin in admin interface.
4. Configure ScoreRender under the ScoreRender tab of the Options page.
5. In Option -> Writing, check if this option is turned on:

       "WordPress should correct invalidly nested XHTML automatically"

   It must be turned off if you intent to render Lilypond and Mup fragments,
   since this option will convert "<<" and ">>" into "< <" and "> >"
   correspondingly, thus destroying the music content and cause render error.

###Upgrade###
1. Deactivate the plugin in WordPress admin page.
2. Remove the whole plugin folder.
3. Upload new plugin and activate again.

== Frequently Asked Questions ==

= It just complains about some obscure error! =

The error code indicates the kind of error in some degree. There are comments inside wp-scorerender.php indicating what kind of error it is. If you can't make any heads and tails out of PHP code, feel free to ask me [through email](http://me.abelcheung.org/aboutme/).

= Why music score fragments are not rendered at all? =

* Check if the beginning tag and ending tag of your music score fragment are correct.
* It will only be rendered if logged in user has 'unfiltered_html' capability, i.e. the user has 'Administrator' or 'Editor' role in WordPress. User can ask blog admin to boost their capabilities if needed.

= Is any ABC notation compatible program also supported? =

Since 0.2, [abcm2ps](http://moinejf.free.fr/) will be the only one supported. This is a design decision. If you REALLY want to use other similar programs, you are on your own, though modifying the code to support others is not very hard. Take a look at `is_notation_usable()` method in class.abc.inc.php.

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

Please visit [ScoreRender official site](http://scorerender.abelcheung.org/) for screenshots.

== License ==
This plugin is released under GNU AGPL v3.
IE Alpha Fix is released under LGPL v2.1 or later.
Zero Clipboard is released under LGPL.

== Changelog ==

**Version 0.3.2**

* Fix invocation for LilyPond 2.12.x

**Version 0.3.1**

* Incorporate certain fixes from trunk:
* Show image dimension in output
* Fix line break when showing score source code under Windows
* Better autodetection of program
* Bug fix in program availability checkinng

**Version 0.3.0**

* Philip's Music Writer notation support.
* IE Alpha fix has been incorporated, which provides translucent PNG support for IE 5.5 / 6.x. Thus drop IE PNG transparency warning altogether. 
* Zero Clipboard has been incorporated, which provides cross platform copy and paste via flash. Warning about non-IE browser during copy and paste is removed.
* Better support of installation on web hosting, where disabling certain PHP functions is common practise.
* Rendering or not also depends on 'unfiltered_html' WordPress capability.
* Refactor functions and files, so admin page is only included when needed, and PHP class no longer access global variables.

**Version 0.2.1**

* Fix turning on/off IE PNG transparency warning option.

**Version 0.2.0**

* Revamp admin page and simplify options.
* Allows limiting number of score fragments and length of fragment.
* Allows showing music source code when image is clicked.
* Better Windows support.
* Mandates abcm2ps must be used for ABC notation support.

**Version 0.1.3**

* Add WordPress nonce protection.

**Version 0.1.2**

* Fix image rendering during ImageMagick conversion process.

**Version 0.1.1**

* Fix transparency of images generated by Lilypond.
* Issue warning if 'correct invalidly nested XHTML automatically' option is checked, instead of turning the option off.

**Version 0.1.0**

* Initial release.
