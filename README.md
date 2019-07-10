ScoreRender is a Wordpress plugin for rendering sheet music fragments into images.  It supports converting fragments in excerpts, posts, pages and (optionally) comments.  Currently it supports 5 music notations: ABC, Guido, Lilypond, Mup and Philip's Music Writer.

# Current status: UNMAINTAINED

Plugin is not updated since 2011. I'm not a WordPress user anymore, and neither do I delve into any music rendering project now. People are welcome to fork it and poke around, but note that:

- It used to work against PHP 5.4 and WordPress 2.8, which are way outdated. Whether it works on current generation of PHP and WordPress is very questionable, I'm willing to bet it doesn't.
- Even the music notation programs and auxiliary utilities ([ImageMagick](https://imagemagick.org/index.php), used for image post processing) see lots of change over the years. They may not be usable without substantial change in code.
- There is a [known vulnerability against clipboard component](https://wpvulndb.com/vulnerabilities/6755). It is adviced to replace or remove it altogether if one is keen on checking this project out.

[Older readme is available](readme.txt), which is in text format parseable by wordpress plugin database.

## License

It used to be in [Affero General Public License](https://en.wikipedia.org/wiki/Affero_General_Public_License) v3 (see readme.txt); but later realized the license being too restrictive. Therefore I declare this project is now using [MIT license](https://opensource.org/licenses/MIT) (note that I'm the sole author after rewrite), so that others investigating this project can be at ease. Multiple components inside were in LGPL license.

## Diagram

The following diagram shows approximately how ScoreRender generates MIDI and PNG image.

![ScoreRender](https://user-images.githubusercontent.com/83110/60996758-e0445d80-a387-11e9-9da4-9055b13d6d5b.png)
