=== ALF WP Stories ===

Contributors: alvarsirlin
Tags: stories, instagram-stories, cpt, rss, gutenberg
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A configurable, multi-frame visual story content type with an Instagram-style
viewer, launcher block, shortcode, REST API, Open Graph, and a dedicated
configurable RSS feed.

== Description ==

ALF WP Stories lets an administrator create a configurable, multi-frame visual
story content type, similar to Google Web Stories.

Features:

* Configurable Custom Post Type (name, slug, archive, REST API).
* Optional grouping taxonomy (hierarchical or flat).
* Admin frame editor with drag-and-drop ordering, alt text, and duration.
* Lightweight Instagram-style fullscreen story viewer.
  * 9:16 presentation, tap, swipe, keyboard navigation.
  * Progress indicators, pause-on-hold, escape-to-close.
  * Reduced-motion aware.
* Visual story archive.
* Dedicated, configurable RSS feed at `/{slug}/feed/`.
  * Title, description, count, ordering, taxonomy filtering.
  * Item title/description/link/GUID/image sources.
  * Custom fields and custom templates.
* Reliable Open Graph metadata for story pages.
* REST API support (stories + frames + cover + terms).
* Reusable dynamic `Story Launcher` Gutenberg block.
  * Latest story, specific story, or latest from taxonomy term.
  * Circular Cover Image with optional decorative ring.
  * Title visibility, size, number of items.
* Equivalent `[visual_story_launcher]` shortcode.
* Theme template overrides (`alf-wp-stories/` folder).
* Plugin-specific hooks and filters for every output.

The core content model is independent from the viewer, RSS, and embedding
components.

== Installation ==

1. Upload the `alf-wp-stories` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Visit Settings → Visual Stories to configure the content type.
4. Add stories via the new admin menu.
5. Add the Story Launcher block to any post or page.

== Frequently Asked Questions ==

= Does this modify my existing RSS feeds? =

No. The plugin provides a dedicated feed at `/{slug}/feed/` and leaves all
existing site feeds untouched.

= Can themes override templates? =

Yes. Copy `archive-story.php` or `single-story.php` from the plugin's
`templates/` folder into `{theme}/alf-wp-stories/` to override them.

== Changelog ==

= 1.0.0 =
* Initial release.
