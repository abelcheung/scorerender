Plugin Name: FigureRender
Plugin URI: http://chris-lamb.co.uk/code/figurerender/
Description: Renders inline LaTeX, Lilypond and Mup figures in posts and comments. 
Author: Chris Lamb
Version: 1.0
Author URI: http://chris-lamb.co.uk/

NOTE: This version is modified by Abel Cheung to render mup score, in addition
to LaTeX and lilypond snipplets. Other additional features and fixes include:
- Use tempnam() instead of creating temp file with random number
- Add 'transparency' option, figures can have transparent background
- Allows each type of image be converted diffrently with convertimg()
- XHTML 1.0 Transitional compliance
- Use safe mode for lilypond rendering

=== REQUIREMENT ===
1. Wordpress 1.5 or later
2. PHP 5.x (doesn't work with 4.x)
3. ImageMagick, specifically the 'convert' utility
4. For rendering LaTeX, the programs 'latex' and 'dvips' must be
   available
5. For rendering lilypond, lilypond >= 2.8.1 must be installed. Doesn't
   work with older versions.
6. For rendering mup, mup must be installed. The magic file (only available
   after paying registering fee) can be utilized if present.

=== INSTALLATION ===
1. extract all files and put them inside wp-content/plugins, preferably
   under a sub-folder inside wp-content/plugins/ (a bit more tidy):
   # mkdir wp-content/plugins/FigureRender/
   # cp *.php wp-content/plugins/FigureRender/

   You can also choose not to create a subdirectory, however.

2. Login to your WordPress installation and enable the plugin on the
   Plugin Management tab.

3. Configure FigureRender under the FigureRender tab of the Options page.

