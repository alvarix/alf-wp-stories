# Generic WordPress Visual Stories Plugin — Technical Specification

## 1. Purpose

Build a standalone WordPress plugin that allows an administrator to create a configurable, multi-frame visual story content type.

The plugin provides:

* A configurable Custom Post Type.
* A configurable taxonomy for grouping stories.
* A simple admin editor for multi-frame stories.
* A lightweight Instagram-style frontend viewer.
* A visual story archive.
* A dedicated, configurable RSS feed.
* Reliable Open Graph metadata.
* REST API support.
* A reusable story-launcher block/component for embedding stories throughout the site.
* No dependency on Google Web Stories or another story plugin.
* No modification of the site's existing RSS feeds.

The core content model must remain independent from the frontend viewer, RSS implementation, and embedding component.

---

# 2. Configuration

On activation, the plugin provides a setup/options screen.

Required user inputs:

### Content Type Name

Example:

`Client Stories`

The plugin derives:

* Singular: `Client Story`
* Plural: `Client Stories`
* Internal post type key: `client_story`

The user may override the singular/plural labels.

### URL Slug

Example:

`client-stories`

Result:

`/client-stories/`

### Grouping Taxonomy

Optional.

Example:

`Clients`

The user may disable the taxonomy.

### Taxonomy URL Slug

Example:

`clients`

---

# 3. Options Page

Create:

`Settings → Visual Stories`

Organize settings into sections.

## 3.1 Content Type

Settings:

* Plural name
* Singular name
* URL slug
* Enable archive
* Enable REST API

## 3.2 Taxonomy

Settings:

* Enable taxonomy
* Taxonomy name
* Taxonomy slug
* Hierarchical/non-hierarchical

## 3.3 Viewer

Settings:

* Default frame duration
* Autoplay
* Loop
* Show progress indicators
* Show close button
* Enable keyboard navigation
* Enable swipe navigation

## 3.4 RSS Feed

Provide complete configuration of the dedicated story RSS feed.

Settings include:

* Feed title
* Feed description
* Number of items
* Ordering
* Taxonomy filtering
* Item title source
* Item description source
* Item link source
* GUID source
* Image source
* Image size
* Image URL format
* Custom fields
* Custom text/templates
* Feed enable/disable

Include feed preview/testing tools.

---

# 4. Custom Post Type

Register the configured story content type as a public WordPress Custom Post Type.

Default properties:

* public
* publicly queryable
* show UI
* show in REST
* archive enabled
* non-hierarchical

Supports:

* title
* thumbnail
* revisions

The standard WordPress editor is not required for the story itself.

---

# 5. URL Structure

Using the configured slug:

Single:

`/{slug}/{story-slug}/`

Archive:

`/{slug}/`

RSS:

`/{slug}/feed/`

The plugin must flush rewrite rules only when appropriate.

---

# 6. Optional Taxonomy

Register one optional taxonomy associated with the configured story post type.

The taxonomy can be used to:

* categorize stories
* filter archive displays
* filter embedded story launchers
* filter RSS output

The plugin must function without a taxonomy.

---

# 7. Story Data Model

Each story is one WordPress post.

Story-level metadata:

* Cover Image
* Caption
* Frames
* Optional settings

Each frame contains:

* Image ID
* Alt text
* Duration

Do not create one WordPress post per frame.

One story produces one RSS item.

---

# 8. Cover Image

Each story must have a dedicated Cover Image.

The Cover Image is used for:

* archive cards
* launcher component
* story preview
* Open Graph metadata
* RSS image
* external social publishing

Recommended dimensions:

`1080 × 1920`

Recommended aspect ratio:

`9:16`

---

# 9. Frame Editor

Provide a simple admin editor with:

* Add frame
* Remove frame
* Reorder frame
* Select image
* Edit alt text
* Set duration

Frames should support drag-and-drop ordering.

The editor should focus on arranging already-created visual assets rather than providing graphic-design functionality.

---

# 10. Frontend Story Viewer

Provide a lightweight Instagram-style viewer.

Required:

* 9:16 presentation
* Full-screen mobile presentation
* Centered desktop presentation
* Progress indicators
* Tap navigation
* Swipe navigation
* Automatic advancement
* Pause on hold
* Close button
* Keyboard navigation
* Escape-to-close

The story image should dominate the interface.

Avoid unnecessary text and decorative UI.

---

# 11. Story Launcher Block / Component

Provide a reusable WordPress Block named according to the configured story type.

For example, if configured as `Client Stories`:

`Client Story Launcher`

The block should be insertable into any Gutenberg page or post.

Its primary purpose is to provide an Instagram-style story entry point.

### Default appearance

A small circular Cover Image from the latest published story:

```text id="u7o5kt"
       ╭─────╮
      ╱       ╲
     │  image  │
      ╲       ╱
       ╰─────╯
       Mabel
```

The title appears directly underneath.

The circular image should use the latest published story's Cover Image.

### Behavior

Clicking/tapping the circular image or title opens the story viewer.

The viewer should begin with the selected/latest story.

---

# 12. Story Launcher Block Settings

The block should have Gutenberg sidebar controls.

### Story selection

Options:

* Latest story
* Specific story
* Latest story from taxonomy term

Default:

`Latest story`

### Taxonomy filter

If taxonomy is enabled:

* All
* Select term

### Display

Controls:

* Image size
* Circle diameter
* Title visibility
* Title alignment
* Title font size
* Spacing
* Number of items

### Number of items

Although the default is one latest story, support multiple story launchers.

For example:

```text id="gd9z4v"
 ○       ○       ○
Mabel   Otis    Luna
```

Each circle opens its respective story.

This allows the component to evolve into an Instagram-style story row.

---

# 13. Latest Story Mode

The default block configuration should be:

`Latest Story`

The block should dynamically resolve the latest published story.

This means that a page containing the block does not need to be edited when a new story is published.

Example:

```text id="x0j7i1"
Today:
     ○
    Mabel

Tomorrow:
     ○
    Otis
```

The same page automatically displays the newest story.

---

# 14. Story Ring

Optionally provide an Instagram-style ring around the circular image.

Default:

Enabled.

Settings:

* Ring enabled/disabled
* Ring width
* Ring style
* Ring color

The default styling should remain visually restrained.

The ring is purely decorative and should not communicate read/unread state unless that functionality is explicitly implemented.

---

# 15. Launcher Accessibility

The launcher must:

* use a real interactive element
* have an accessible label
* expose the story title
* remain keyboard accessible
* provide visible focus state
* work without hover
* maintain sufficient text contrast

The image's alt text should be meaningful.

---

# 16. Launcher Responsive Behavior

The launcher should work on:

* desktop
* tablet
* mobile

The image should remain circular at all sizes.

The component should not require a fixed global site layout.

It should inherit or provide sensible typography while allowing theme CSS to override it.

---

# 17. Block Rendering

Prefer dynamic server-side rendering for the default latest-story mode.

This ensures that:

* a newly published story appears automatically
* cached page content can be invalidated appropriately
* no stale story ID is permanently stored in post content

The block configuration should store the query parameters, not the resolved story data.

---

# 18. Shortcode Equivalent

Provide an equivalent shortcode for non-Gutenberg contexts:

`[visual_story_launcher]`

Optional parameters:

* `story`
* `limit`
* `taxonomy`
* `term`
* `size`
* `show_title`
* `ring`

Example:

`[visual_story_launcher limit="1"]`

This should use the same rendering logic as the Gutenberg block.

---

# 19. Multiple Story Launcher

The component should support an optional row/list mode.

Example:

```text id="7s4h9f"
 ○       ○       ○       ○
Mabel   Otis     Luna    Max
```

Each item opens the viewer at its selected story.

Default mode remains a single latest story.

---

# 20. Story Viewer Launch Behavior

When a launcher opens a story:

* open the story viewer
* start at frame 1
* display progress indicators
* enable normal viewer navigation
* allow closing back to the underlying page

If multiple stories are displayed in a launcher row, the viewer may optionally allow advancing from one story to the next.

This should be configurable.

---

# 21. RSS Feed

Provide a dedicated RSS feed for the configured story content type.

Example:

`/client-stories/feed/`

The feed should include only published stories.

One story = one RSS item.

The feed must be fully configurable through the options page.

---

# 22. RSS Options

The options page should allow configuration of:

* Feed title
* Feed description
* Item count
* Ordering
* Taxonomy inclusion/exclusion
* Item title source
* Item description source
* Item link
* GUID
* Image source
* Image size
* Image URL
* Custom fields
* Custom templates
* Feed enable/disable

Provide a feed preview and test-item preview.

---

# 23. RSS Image

Allow selection of:

* Cover Image
* First Frame
* Custom image field

Default:

`Cover Image`

The image should be exposed using a reliable absolute HTTPS URL.

---

# 24. Open Graph

Every story page must expose:

* `og:title`
* `og:description`
* `og:url`
* `og:type`
* `og:image`
* `og:image:width`
* `og:image:height`

The default `og:image` is the Cover Image.

Avoid conflicts with existing SEO plugins.

---

# 25. REST API

Expose story content through the WordPress REST API.

Include:

* story ID
* title
* URL
* cover image
* caption
* taxonomy terms
* frames

Frames include:

* image ID
* image URLs
* alt text
* duration

This allows a future frontend or application to consume the stories without changing the content model.

---

# 26. Performance

Load viewer assets only when needed.

The launcher should be lightweight.

Requirements:

* no global viewer JavaScript
* responsive images
* lazy loading where appropriate
* preload next frame
* minimal dependencies
* no large frontend framework solely for the viewer

The latest-story block should not load every story or every image just to determine the latest story.

---

# 27. Security

All admin operations must use:

* WordPress capability checks
* nonces
* sanitization
* escaping

No arbitrary HTML should be accepted in frame metadata or RSS templates without appropriate sanitization.

---

# 28. Accessibility

The story viewer and launcher must support:

* keyboard navigation
* focus management
* Escape-to-close
* accessible controls
* alt text
* reduced-motion preferences
* visible keyboard focus

---

# 29. Template Overrides

Themes should be able to override:

* archive template
* single-story template
* launcher markup
* viewer markup

The plugin must not require direct modification of plugin files.

---

# 30. Hooks and Filters

Provide plugin-specific filters/actions for:

* story query
* launcher query
* launcher markup
* cover image
* frame data
* viewer settings
* RSS query
* RSS item output
* RSS image
* Open Graph metadata

This allows site-specific customization without modifying plugin code.

---

# 31. Version 1 Non-Goals

Do not initially implement:

* Google Web Stories
* AMP
* video frames
* image editing
* animated text
* stickers
* polls
* quizzes
* social API integrations
* direct Metricool API
* analytics
* ecommerce
* user-generated stories
* story expiration
* reactions
* comments

---

# 32. Acceptance Test

Using an arbitrary configured content type such as `Client Stories`:

1. Configure the plugin.
2. Create a story.
3. Select a Cover Image.
4. Add multiple frames.
5. Publish.
6. Story appears in the archive.
7. Story opens in the story viewer.
8. Frames can be navigated by tap, swipe, and keyboard.
9. The latest-story Gutenberg block automatically displays the new story.
10. The block shows the Cover Image as a small circle.
11. The story title appears underneath.
12. Clicking the circle opens the story viewer.
13. Publishing a newer story automatically causes the block to display the newer story.
14. A multi-story launcher can display multiple circles.
15. Each circle opens the corresponding story.
16. `/client-stories/feed/` contains the published story.
17. RSS output can be customized through the options page.
18. The story page exposes the correct Cover Image as `og:image`.
19. Existing site RSS feeds remain unchanged.
20. The same plugin works when configured with a completely different story type/name.

---

# 33. Core Architecture

The plugin should treat the configured story type as the canonical content object:

```text id="q7kz1d"
Story
├── Metadata
├── Cover Image
├── Frames[]
├── Optional Taxonomy
└── Caption
```

The same content powers several independent outputs:

```text id="3t6zpu"
                    ┌── Archive
                    │
Story Content ──────┼── Story Viewer
                    │
                    ├── Launcher Block
                    │
                    ├── Shortcode
                    │
                    ├── REST API
                    │
                    └── Configurable RSS
                              ↓
                     External Automation
```

The launcher is a presentation component, not a separate content type.

The latest-story launcher must resolve its content dynamically so that publishing a new story automatically updates every page containing the launcher.
