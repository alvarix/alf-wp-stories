# 002 — Settings Page & Batch Auto-Post Creator

**Response to:** verbal request (no `002--usr` doc provided).
**Author:** LLM
**Status:** Draft — open questions flagged inline.

This spec extends `001` (the ALF WP Stories plugin) with two things the user
asked for before testing:

1. A consolidated **plugin settings page** linked from the Plugins list,
   covering CPT name, RSS feed output, filename variable extraction, and
   story-side settings (slide duration, block/shortcode, theming).
2. A **batch auto-post creator** that turns Media Library images into story
   posts in bulk, reusing the proven pattern from two existing plugins:
   - `/Users/alvarsirlin/Sites/WP/wp_plugins/bulk-pp-gallery`
   - `/Users/alvarsirlin/Sites/WP/wp_plugins/bulk-cpt-pay`

---

## Phase 1 — Step-Back Analysis

### Problem Classification

Two distinct problems:

- **Configuration UX** — consolidating scattered options into one discoverable
  surface and adding plugin-row linkage (admin UI / information architecture).
- **Batch ingestion** — a Media Library → CPT pipeline: a bulk action, a
  confirmation modal, an AJAX creator, and a **filename → story-fields
  parser**. The parser is the hard part; the rest is a known pattern.

### Governing Principles

- **Single source of truth for configuration.** The filename grammar must be
  data (stored options), not code. The two reference plugins hardcode the
  grammar (`__` delimiter, `.` for tags); we make it configurable so the same
  plugin serves any content type without code changes.
- **Idempotency.** Re-running the bulk action on the same images must not
  create duplicate stories. Track source attachment IDs.
- **Content model independence** (carried over from `001`). The batch creator
  writes through the existing `Story` model and `Story_Meta` save path — it
  does not bypass them.
- **Separation of concerns.** Parser, ingester, and UI are separate classes so
  the parser can be unit-tested in isolation and reused by WP-CLI later.

### Data Structures & Complexity

- Filename grammar: a small ordered list of segments, each with a **target**
  (title | taxonomy:<key> | tag | frame_order | ignore) and a **transform**
  (title-case | raw | slugify). Parsing is O(segments) per file, O(n) over
  selected files.
- Grouping: a config-driven key function. Group selected attachments by a
  configurable segment (default: the `title` segment) so multi-frame stories
  can be assembled from `storyname__01.jpg`, `storyname__02.jpg`, etc. Bucket
  sort → O(n).
- De-duplication: a post-meta index `_alf_wp_stories_source_ids` (array of
  attachment IDs) plus a lookup by the group key. O(n) with an associative
  array.

---

## Phase 2 — Edge Cases & Architecture

### Edge Cases

1. **Same image selected twice in one batch** — must not produce two frames
   from one attachment; dedupe by attachment ID within a group.
2. **Re-running the bulk action on images already turned into a story** —
   must update the existing story (add missing frames) rather than create a
   duplicate. Match on group key + source IDs.
3. **Filename grammar misconfigured** (e.g. two segments mapped to `title`) —
   parser must validate config on save and refuse ambiguous mappings; at
   runtime, fall back to the first matching segment.
4. **Image selected that has no frames after grouping** (single image, no
   group key) — must still produce a valid single-frame story, not be
   skipped.
5. **Taxonomy disabled in `001` options but filename grammar assigns a
   taxonomy segment** — parser must skip taxonomy assignment gracefully and
   log a warning in the batch result.

### Architectural Pattern

**Pipeline / middleware** for ingestion, **strategy** for the parser.

```
Media selection
  → GroupingStrategy.bucket(attachments)        // group by config key
  → FilenameParser.parse(filename)              // grammar config → fields
  → StoryIngester.ingest(group, fields)         // create/update story via Story model
  → BatchResult                                  // per-story outcome
```

Each stage is a class with a single method; the AJAX handler orchestrates.

---

## Phase 3 — Implementation Plan

### A. Settings page restructure

Current state (`001`): options live under **Settings → Visual Stories** with
sections post_type / taxonomy / viewer / rss.

Target state:

1. **Keep** the Settings API storage (`alf_wp_stories_options`) — no schema
   migration.
2. **Relocate** the menu to a submenu under the story CPT admin menu (matches
   the two reference plugins and is more discoverable than Settings):
   `edit.php?post_type={cpt}&page=alf-wp-stories`.
3. **Add a Settings link on the Plugins list row** via
   `plugin_action_links_alf-wp-stories/alf-wp-stories.php` — pointing to the
   same page. This is the explicit "linked from plugins page" request.
4. **Tabbed UI** (like both reference plugins): Settings | Auto-Post | Help.
   Keeps the long form usable.

### B. New settings sections

Add these sections to `Options::build_defaults()` and the settings page:

#### B.1 Filename variable extraction (NEW — the configurable grammar)

A repeater-style config that describes how a filename maps to story fields.
Stored as an ordered array of segment definitions:

```
filename_grammar:
  delimiter: "__"              # segment separator (default "__")
  tag_delimiter: "."           # sub-separator within a segment for tags
  segments:
    - { target: "title",      transform: "title_case" }
    - { target: "taxonomy:clients", transform: "title_case" }
    - { target: "tag",        transform: "raw", repeat: true }
    - { target: "frame_order", transform: "integer" }
```

Configurable per-segment fields:
- **target** — one of: `title`, `caption`, `taxonomy:<key>`, `tag`,
  `frame_order`, `ignore`.
- **transform** — `raw`, `title_case`, `slugify`, `integer`.
- **repeat** (bool) — for `tag` segments, consume all remaining sub-segments
  (the `.` split).

Plus a **group key** selector: which segment identifies "this image belongs
to story X" (default: `title`). Images sharing the same group-key value become
frames of one story, ordered by the `frame_order` segment if present, else by
attachment ID.

Plus a **live preview** widget on the settings page: type a sample filename,
see the parsed result. This is the "filename variable extraction
configuration" surface.

#### B.2 Story defaults (consolidate + extend)

Move the existing `viewer` section under a clearer "Story Defaults" umbrella
and add:

- `frame_duration` (exists)
- `default_cover_source` — `first_frame` | `last_frame` | `largest` (NEW)
- `default_post_status` — `publish` | `draft` (NEW; default `publish` to
  match reference plugins, but `draft` is safer for first runs)
- `default_shortcode` — a text field pre-populated with the rendered
  `[visual_story_launcher]` snippet using current defaults (read-only
  convenience copy-paste field; not stored behavior).
- `auto_open_on_single` — whether the viewer auto-opens on the single story
  page (currently hardcoded `true` in the template; make it a setting).

#### B.3 Theming (NEW)

Lightweight theming knobs that emit CSS variables on the viewer/launcher
containers, so themes can still override:

- `theme_accent` — color (ring + progress bars). Default `#c8ccd2`.
- `theme_ring_width` — px. Default `4`.
- `theme_ring_style` — `solid` | `gradient`. Default `solid`.
- `theme_overlay` — overlay background (near-black default
  `rgba(20,22,26,0.96)`).
- `theme_launcher_background` — circle placeholder bg.

Emitted as `:root { --alf-wp-stories-* }` only when non-default, to avoid
global CSS noise. This satisfies "theming" without a full theme system.

#### B.4 RSS feed output details (already exists — reorganize)

No schema change. Just ensure the existing `rss` section is presented under
the same tabbed page for one-stop configuration, and the feed preview/test
tool from `001` stays.

### C. Batch auto-post creator

Mirrors the two reference plugins exactly in mechanics; differs in
grouping.

#### C.1 Bulk action registration

```
add_filter( 'bulk_actions-upload', fn( $a ) => $a['alf_wp_stories_create'] = 'Create Stories' );
```

#### C.2 Modal

`admin_footer-upload.php` renders a modal (same shape as
`bcpay_bulk_action_modal`) showing:
- count of selected images
- the resolved grouping preview (how many stories will be created, with how
  many frames each) — computed client-side from selected filenames + grammar
  config localized to JS
- optional taxonomy-term assignment checkboxes (if taxonomy enabled)
- post-status override (publish/draft) — defaults from settings
- Create / Cancel buttons + progress spinner

`assets/js/admin-bulk.js` intercepts the bulk-action form submit (both
`#doaction` and `#doaction2`), collects `media[]` checked IDs, opens the
modal, posts to `admin-ajax.php`.

#### C.3 AJAX handler

`wp_ajax_alf_wp_stories_bulk_create`:

1. `check_ajax_referer`.
2. `current_user_can( 'edit_posts' )`.
3. Inputs: `attachment_ids[]`, optional `term_ids[]`, optional
   `post_status` override.
4. For each attachment: resize per `max_image_size` (reuse
   `ppgal2_resize_attachment` logic), parse filename via the configured
   grammar, bucket by group key.
5. **Compliance gate (doc `003`):** before creating/publishing, run each
   group's resolved cover attachment through
   `alf_wp_media_ratio_check` (if that plugin is active). Collect
   non-compliant cover IDs. If any are non-compliant AND the requested
   status is `publish`, **do not publish those stories**: create them as
   `draft` instead and return them in `needs_attention[]` so the modal can
   offer the "Fix all" crop path. Stories with compliant covers publish as
   requested. This prevents Metricool from ingesting a non-compliant item.
6. For each group: create or update the story post (idempotent match on
   group key meta `_alf_wp_stories_group_key`), set cover, set frames
   (ordered), assign taxonomy terms.
7. Return `{ created, updated, frames, needs_attention[], errors[] }` and a
   human message.

#### C.4 New classes

- `includes/class-filename-parser.php` — pure PHP, grammar config in, fields
  out. Unit-testable without WordPress.
- `includes/class-bulk-ingest.php` — orchestrates grouping + ingestion,
  registers the bulk action, modal, AJAX handler, admin assets.

#### C.5 Idempotency meta

- `_alf_wp_stories_source_ids` — array of attachment IDs (for "is this image
  already in a story?" checks).
- `_alf_wp_stories_group_key` — the group key string (for "does this story
  already exist?" checks).

Both written by the ingester and read on every batch run.

### D. Help tab

A Help tab (like both reference plugins) documenting:
- the filename grammar with a live examples table generated from the current
  config
- the bulk-create workflow
- the shortcode and block usage

---

## Decisions (locked from user answers)

1. **Grouping default** — **group by filename title segment.**
   `mabel__01.jpg`, `mabel__02.jpg` → one story "Mabel" with 2 frames. Single
   images with no group still become single-frame stories.
2. **Default post status** — **`publish`.** User workflow is feed-driven
   (Metricool picks up the RSS item and auto-publishes to social, one-and-done).
   Drafts do not appear in RSS, so `publish` is the correct default for this
   workflow. **Caveat (interaction with doc `003`):** story covers are 9:16
   and Metricool requires 3:4–1.91:1, so the aspect-ratio compliance check
   (doc `003`) MUST run inside the batch-create flow **before** the publish,
   and non-compliant covers must be flagged (and optionally cropped to a 3:4
   `social_cover` derivative) before the story goes live — otherwise Metricool
   may ingest a non-compliant item before the user fixes it. The batch modal
   surfaces this as a blocking warning with a "Fix all" path.
3. **Cover selection** — **first frame** by default, configurable via
   `default_cover_source`. Optional `cover` segment override (a frame whose
   filename contains a `cover` token wins).
4. **Menu** — **single canonical page under the CPT submenu**, linked from the
   Plugins row. The existing Settings → Visual Stories menu from `001` is
   removed to avoid two edit surfaces.
5. **Filename delimiter** — **fixed to `__`.** Not configurable. Matches the
   two reference plugins and sidesteps WordPress `sanitize_file_name()`
   pitfalls. The grammar's per-segment targets/transforms remain
   configurable; only the delimiter is locked.

---

## Acceptance Test (for this feature)

- [ ] Plugins list shows a "Settings" link on the ALF WP Stories row.
- [ ] Settings page is tabbed: Settings | Auto-Post | Help.
- [ ] Filename grammar is configurable and has a live preview.
- [ ] Selecting media in the library, choosing "Create Stories", opens a modal showing the resolved grouping (N stories, M frames).
- [ ] Creating publishes/ drafts stories per the status override.
- [ ] Re-running on the same images updates existing stories instead of duplicating.
- [ ] Filename grammar changes (e.g. delimiter, segment targets) take effect on the next batch without code changes.
- [ ] Theming settings (accent, ring, overlay) visibly affect the launcher and viewer.
- [ ] Default shortcode snippet is copy-pasteable from the settings page.
- [ ] Existing `001` features still work after the restructure (archive, viewer, launcher, RSS, REST, OG).
- [ ] When `wp-alf-img-ratio-check` (doc `003`) is active, non-compliant covers block publish and route through the crop/fix path.

---

## Implementation status (v1.1.0)

Implemented in `alf_wp_stories` v1.1.0:

- `includes/class-options.php` — rewritten: CPT submenu, tabbed UI, new `story`/`theming`/`filename_grammar` sections, plugin-row Settings link, `theming_css()` emitter, grammar repeater + live-preview AJAX.
- `includes/class-filename-parser.php` — pure PHP parser (grammar in, fields out).
- `includes/class-bulk-ingest.php` — Media Library bulk action, modal, AJAX creator, grouping by group key, idempotent create/update via `_alf_wp_stories_group_key` + `_alf_wp_stories_source_ids`, compliance gate (downgrades non-compliant covers to draft before publish).
- `includes/class-integration.php` — registers the `alf_wp_stories_cover` context with `wp_alf-img-ratio-check`; stores compliant derivatives as `_alf_wp_stories_social_cover`; exposes `attachment_is_compliant()` for the bulk flow.
- `includes/class-story.php` — payload now includes `social_cover` / `social_cover_id`.
- `includes/class-rss.php` — image source prefers the social cover (new `social_cover` option); cover fallback also prefers social cover.
- `includes/class-opengraph.php` — `og:image` prefers the social cover.
- `includes/class-viewer.php` / `class-launcher.php` — emit scoped theming CSS variables.
- `templates/single-story.php` — respects `story.auto_open_on_single`.
- `assets/css/bulk.css`, `assets/js/admin-bulk.js`, `assets/js/admin-grammar.js`.

Locked decisions reflected: group by title segment; `publish` default (with compliance safety net); first-frame cover; single CPT-submenu page; fixed `__` delimiter.

Unverified in a live WordPress environment — see acceptance checklist.

---

## Summary (Phase 3 close)

This design isolates the filename grammar as configurable data and routes all batch ingestion through the existing `Story` model, so the new auto-post creator is a presentation/ingestion adapter rather than a parallel content path. A compliance gate (doc `003`) downgrades non-compliant covers to draft before publish, so the `publish`-by-default workflow remains safe for Metricool without exposing bad-ratio items. Honors idempotency and content-model-independence from Phase 1.