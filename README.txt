Plugin Name: ScoreRender
Plugin URI: http://scorerender.abelcheung.org/
Description: Renders inline sheet music fragments in post, pages and comments.
Author: Abel Cheung
Version: 0.1
Author URI: http://me.abelcheung.org/

=== REQUIREMENT ===
1. Wordpress 2.0.x or later (does not work with 1.5.x)
2. PHP 4.3.x
3. ImageMagick, specifically the 'convert' utility

=== OPTIONAL PROGRAM ===
1. For rendering lilypond, lilypond >= 2.8.1 must be installed. Doesn't
   work with older versions.
2. For rendering mup, mup must be installed. The magic file (only available
   after paying registration fee) can be utilized if present.
3. For rendering ABC notation, either use abcm2ps or abc2ps (can be
   changed in program path in option page).

=== INSTALLATION ===
1. extract archive, and copy this folder to wp-content/plugins/.
2. Login to WordPress and enable the plugin in admin interface.
3. Configure ScoreRender under the ScoreRender tab of the Options page.

