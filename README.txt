Plugin Name: ScoreRender
Plugin URI: http://scorerender.abelcheung.org/
Description: Renders inline sheet music fragments in post, pages and comments.
Author: Abel Cheung
Version: 0.1.2
Author URI: http://me.abelcheung.org/

=== REQUIREMENT ===
1. Wordpress 2.0.x or later
2. PHP 4.x (I don't know the exact requirement yet, it works fine with
   PHP 4.4.2 on one of the wordpress installations)
3. ImageMagick, specifically the 'convert' utility

=== OPTIONAL PROGRAM ===
1. For lilypond notation, lilypond >= 2.8.1 must be installed. Doesn't
   work with older versions.
2. For mup notation, mup must be installed. The magic file (only available
   after paying registration fee) can be utilized if present.
3. For ABC notation, any program compatible with abc2ps command line options
   is fine. However abcm2ps is preferred, due to its ability to handle multiple
   voices within single staff.

=== INSTALLATION ===
1. extract archive, and copy this folder to wp-content/plugins/.
2. Login to WordPress and enable the plugin in admin interface.
3. Configure ScoreRender under the ScoreRender tab of the Options page.
4. In Option -> Writing, check if this option is turned on:

       "WordPress should correct invalidly nested XHTML automatically"

   It must be turned off if you intent to render Lilypond and Mup fragments,
   since this option will convert "<<" and ">>" into "< <" and "> >"
   correspondingly, thus destroying the music content and cause render error.
