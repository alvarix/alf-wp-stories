# ALF WP Stories — LLM Response Spec (001)

Response to: `docs/001--usr--wp-stories-plugin-spec.md`

This document maps the implementation to the user specification and tracks the
decisions made during the Step-Back analysis.

---

## Architecture

Port-and-adapter around a single canonical `Story` model.

```
includes/
  class-plugin.php      bootstrap + service container (singleton)
  class-options.php     settings page + defaults + sanitization
  class-post-type.php   configurable CPT
  class-taxonomy.php     optional grouping taxonomy
  class-story-meta.php   admin frame editor (cover, caption, frames)
  class-story.php        canonical story model (the "core")
  class-templates.php    template overrides + rewrite/flush + feed short-circuit
  class-viewer.php       viewer/launcher asset registration (lazy)
  class-launcher.php     Gutenberg block + shortcode (dynamic render)
  class-rss.php          dedicated configurable feed
  class-opengraph.php    Open Graph head tags
  class-rest.php         REST API
  helpers.php            frame normalization, attachment URL helpers
assets/css|js            viewer, launcher, admin, block editor
templates/              archive-story.php, single-story.php (overridable)
```

Every output (archive, viewer, launcher, shortcode, REST, RSS, OG) reads
through `ALF_WP_Stories\Story::get_story()`, so the content model is decoupled
from presentation.

---

## Spec → Implementation Map

| Spec § | Implementation |
|--------|----------------|
| 1 Purpose | Full plugin; no Google Web Stories dependency; independent content model |
| 2 Configuration | `Options::build_defaults()` + Settings page sections |
| 3 Options Page | `Settings → Visual Stories`, sections 3.1–3.4, feed preview/test tool |
| 4 Custom Post Type | `Post_Type::register_post_type()` (public, archive, non-hierarchical, title/thumbnail/revisions) |
| 5 URL Structure | rewrite slug = configured; `/{slug}/`, `/{slug}/{story-slug}/`, `/{slug}/feed/` |
| 6 Optional Taxonomy | `Taxonomy::register_taxonomy()`; plugin works without it |
| 7 Story Data Model | `Story` model: one post, post-meta cover/caption/frames |
| 8 Cover Image | dedicated `_alf_wp_stories_cover` meta + featured-image + first-frame fallback |
| 9 Frame Editor | `Story_Meta` meta box + `admin-frames.js` (add/remove/reorder/media) |
| 10 Frontend Viewer | `viewer.js` + `viewer.css` (9:16, fullscreen, tap/swipe/keyboard, pause-on-hold) |
| 11 Launcher Block | `Launcher::register_block()` (server-side render) |
| 12 Launcher Settings | block attributes + inspector controls |
| 13 Latest Story Mode | default `mode=latest`; resolved server-side each render |
| 14 Story Ring | `ring` attribute + `.has-ring` decorative ring |
| 15 Launcher Accessibility | real `<button>`, `aria-label`, alt text, focus outline |
| 16 Launcher Responsive | fluid CSS, circular at all sizes, theme-overridable |
| 17 Block Rendering | dynamic server-side render; stores query params, not resolved data |
| 18 Shortcode | `[visual_story_launcher]` with `story/limit/taxonomy/term/size/show_title/ring` |
| 19 Multiple Launcher | `limit` attribute renders a row of circles |
| 20 Viewer Launch | opens at selected story frame 1; multi-story advancement + loop |
| 21 RSS Feed | `Rss::render()` at `/{slug}/feed/` (short-circuit) |
| 22 RSS Options | all configurable fields in Options section 3.4 |
| 23 RSS Image | cover / first frame / custom field; absolute HTTPS by default |
| 24 Open Graph | `OpenGraph::print_tags()` on single story only |
| 25 REST API | `Rest` dedicated route + `rest_prepare_{cpt}` enrichment |
| 26 Performance | assets registered, enqueued only on render; lazy images; preload next frame |
| 27 Security | capability checks, nonces, sanitization, escaping throughout |
| 28 Accessibility | keyboard, focus, escape, reduced-motion, alt text, visible focus |
| 29 Template Overrides | `locate_template_file()` checks `{theme}/alf-wp-stories/` |
| 30 Hooks & Filters | filters for query, launcher markup, cover, frames, viewer settings, RSS, OG |
| 31 Non-Goals | none of the listed items implemented |
| 32 Acceptance Test | see checklist below |
| 33 Core Architecture | matches the diagram: Story → Archive/Viewer/Launcher/Shortcode/REST/RSS |

---

## Acceptance Test Checklist (mirrors §32)

- [ ] 1. Plugin configured via Settings → Visual Stories.
- [ ] 2. Story created via new admin menu.
- [ ] 3. Cover image selected in the Story meta box.
- [ ] 4. Multiple frames added and reordered.
- [ ] 5. Story published.
- [ ] 6. Story appears in the archive (`/{slug}/`).
- [ ] 7. Story opens in the viewer on its single page.
- [ ] 8. Frames navigable by tap, swipe, and keyboard.
- [ ] 9. Latest-story block displays the new story.
- [ ] 10. Block shows cover image as a small circle.
- [ ] 11. Title appears underneath the circle.
- [ ] 12. Clicking the circle opens the viewer.
- [ ] 13. Publishing a newer story updates the block automatically.
- [ ] 14. Multi-story launcher displays multiple circles.
- [ ] 15. Each circle opens the corresponding story.
- [ ] 16. `/{slug}/feed/` contains the published story.
- [ ] 17. RSS output is customizable via the options page.
- [ ] 18. Story page exposes the cover image as `og:image`.
- [ ] 19. Existing site RSS feeds remain unchanged.
- [ ] 20. Plugin works when reconfigured with a different story type/name.

---

## Decisions & Tradeoffs

1. **Feed short-circuit via `template_include`** rather than a custom rewrite
   feed endpoint. This avoids touching the global rewrite feed list and keeps
   the existing site feeds untouched (§19). The feed URL is matched against
   `REQUEST_URI`.

2. **Rewrite flush only on slug change** (`Templates::maybe_flush_rewrites`),
   not on every save — avoids unnecessary flushes.

3. **Story registry printed in footer as JSON** so the viewer JS can open any
   rendered story without an extra request; the single-story page also embeds
   its story inline via `data-story-json`.

4. **Cover image resolution chain**: explicit cover meta → featured image →
   first frame. Keeps OG/RSS/launcher consistent with one accessor.

5. **Block is fully dynamic** (server-side render) so the latest-story
   resolution always reflects current content and cached pages can be
   invalidated by the cache layer without storing stale IDs in post content.

---

## Open Questions

None blocking. If the deployment uses a persistent object cache, verify that
flushing rewrite rules on slug change runs on all environments.
