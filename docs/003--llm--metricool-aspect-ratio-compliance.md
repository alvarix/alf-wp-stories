# 003 — Media Aspect-Ratio Compliance Check (Metricool auto-publish)

**Standalone plugin spec:** `wp-alf-img-ratio-check` (PHP/filter prefix `wp_alf_img_ratio_check`)
**Applies to:**
- `alf_wp_stories` (story covers, 9:16 → non-compliant by default)
- `bulk-pp-gallery` (PP gallery thumbnails)
- `bulk-cpt-pay` (adoption thumbnails)

**Why standalone:** three CPT plugins all need it; a drop-in plugin that each
CPT registers with via a filter is cleaner than copying a library into each.

---

## Phase 1 — Step-Back Analysis

### Problem Classification

Two coupled problems:

1. **Validation** — determine whether an image's aspect ratio falls inside a
   configurable acceptable range, at attachment time and at batch-create time.
2. **Remediation** — offer a crop UI that constrains to a compliant ratio and
   re-validates after the crop is saved.

Metricool's auto-publish requirement is the driver: images outside
**3:4 (0.75) – 1.91:1 (1.91)** are rejected. Story covers are 9:16 (0.5625),
so they fail by default and **must** be cropped before publish.

### Governing Principles

- **Data-driven ratio rules.** The min/max ratio and the per-plugin context
  (which image field to check) are configuration, not hardcoded. Metricool's
  3:4–1.91:1 is the default, but the same plugin must serve any future tool
  with different requirements (e.g. Instagram feed 4:5, Twitter 16:9).
- **Non-destructive.** Never overwrite the original attachment. Cropping
  produces a new attachment (a derivative) or updates the attachment in place
  only when the user explicitly confirms. Prefer producing a compliant
  derivative and pointing the consuming plugin at it, leaving the source
  untouched.
- **Pluggable integration.** The compliance plugin knows nothing about CPTs
  or stories. Each CPT plugin declares "check this attachment field for posts
  of my type" via a filter; the compliance plugin does the rest.
- **Fail loud, fail early.** Non-compliant images are flagged in the admin
  list, the media library, and the batch-create modal **before** publish — so
  Metricool never sees a bad item.

### Data Structures & Complexity

- Ratio check: O(1) per image — `width / height` compared against `[min, max]`.
  For N selected images: O(N).
- Compliance state: stored as attachment post-meta
  `_alf_ratio_status = { compliant: bool, ratio: float, checked_at: int }`.
  Lookup is O(1) per image.
- Integration registry: a static array built from the
  `alf_wp_media_ratio_check_contexts` filter. Each context declares
  `{ plugin, cpt, field, label }`. Evaluated lazily when rendering badges.

---

## Phase 2 — Edge Cases & Architecture

### Edge Cases

1. **Image with no stored dimensions** (upload in progress, or broken file) —
  the checker must return "unknown" rather than "non-compliant", and skip
  flagging. Re-check on next attachment-metadata write.
2. **Cropped derivative vs. original** — if a non-compliant original is
  cropped into a compliant derivative, the consuming plugin must be repointed
  at the derivative. The compliance plugin emits the new attachment ID via a
  filter; the CPT plugin decides whether to swap its stored cover.
3. **Ratio exactly on the boundary** (3:4 or 1.91:1) — inclusive: a ratio of
  exactly 0.75 or 1.91 passes.
4. **Multiple contexts for one attachment** — one image might be a story cover
  (needs ≥0.75) AND a gallery thumb (no constraint). The badge aggregates:
  "non-compliant for Stories" but "ok for Gallery".
5. **Re-crop after a previous crop** — each crop produces a fresh derivative
  (or replaces in-place per user choice); the recheck re-evaluates the new
  dimensions. Avoid stacking derivatives infinitely by tracking lineage.

### Architectural Pattern

**Registry + strategy.** A central `Compliance_Checker` holds the ratio
config; `Context` objects (one per registered CPT plugin) describe what to
check. A `Crop_Controller` handles the remediation modal. The admin UI is a
thin observer that reads compliance state and renders badges.

```
CPT plugin  ──filter──▶  Context Registry
                              │
                              ▼
                       Compliance_Checker  ──▶  attachment meta
                              │
                              ▼
                       Admin badges / Media Library column / Batch modal
                              │
                              ▼ (user clicks "Fix")
                       Crop_Controller  ──▶  WP image editor  ──▶  recheck
```

---

## Phase 3 — Implementation Plan

### A. Plugin scaffold

- `wp-alf-img-ratio-check.php` — bootstrap, constants.
- `includes/class-options.php` — min/max ratio, per-context overrides,
  crop behavior (derivative vs in-place).
- `includes/class-checker.php` — pure ratio logic, reads dimensions, writes
  `_alf_ratio_status` meta. Unit-testable.
- `includes/class-contexts.php` — registry built from the
  `alf_wp_media_ratio_check_contexts` filter.
- `includes/class-admin-ui.php` — Media Library column badge, admin notice
  on the CPT edit screen, integration with the batch-create modals (via JS
  event the CPT plugins emit).
- `includes/class-crop-controller.php` — AJAX crop using `wp_get_image_editor`,
  constrained to a compliant ratio preset, re-check after save.
- `assets/css/admin.css`, `assets/js/admin-ratio.js`.

### B. Configuration

Defaults (Metricool):

```
min_ratio: 0.75     // 3:4
max_ratio: 1.91     // 1.91:1
crop_mode: derivative   // derivative | in_place
```

Per-context overrides: a context may narrow the range (e.g. a future tool
that wants 4:5–1:1) but cannot widen beyond the global bounds.

### C. Integration contract (the filter other plugins use)

```php
add_filter( 'wp_alf_img_ratio_check_contexts', function ( $contexts ) {
    $contexts[] = array(
        'id'      => 'alf_wp_stories_cover',
        'plugin'   => 'alf_wp_stories',
        'cpt'      => $options->post_type_key(),
        'field'    => 'cover',           // logical field name the CPT plugin resolves
        'label'    => __( 'Story cover', 'alf-wp-stories' ),
        'min'      => 0.75,              // optional override
        'max'      => 1.91,
        'resolver' => function ( $post_id ) {
            return (int) get_post_meta( $post_id, '_alf_wp_stories_cover', true );
        },
    );
    return $contexts;
} );
```

Each CPT plugin (`alf_wp_stories`, `bulk-pp-gallery`, `bulk-cpt-pay`)
registers one context. The compliance plugin handles the rest.

### D. Compliance check lifecycle

1. **On attachment metadata generation** (`wp_generate_attachment_metadata`):
  run the checker, write `_alf_ratio_status`. Cheap, runs once per upload.
2. **On batch-create** (in each CPT plugin's AJAX handler): before
  creating/publishing, query the checker for each attachment; collect
  non-compliant IDs; return them to the modal so the user sees a warning list
  and can either (a) proceed, (b) fix via the crop modal, or (c) cancel.
3. **On the CPT edit screen**: if the post's resolved attachment (cover/thumb)
  is non-compliant, show an admin notice with a "Fix aspect ratio" button
  that opens the crop modal.
4. **After crop**: re-run the checker on the new/edited attachment, update
  meta, and fire `wp_alf_img_ratio_check_passed` so the CPT plugin can
  re-point its cover/thumb to the compliant derivative if it wants.

### E. Crop modal

Built on `wp.media` + `wp.ajax`:
- Loads the attachment.
- Offers ratio presets inside the compliant range: **3:4**, **1:1**, **4:5**,
  **1.91:1**, plus a "custom within range" mode that clamps the crop rect.
- On confirm: server-side `Crop_Controller` calls `wp_get_image_editor` →
  `crop()` → `save()`. Per `crop_mode`, either creates a new attachment
  (derivative, lineage meta `_alf_ratio_parent`) or overwrites.
- Re-check writes fresh `_alf_ratio_status` and returns it to the modal.

### F. Admin surfaces

- **Media Library list view**: a "Ratio" column showing the ratio and a
  green/red dot.
- **Media Library grid view**: a small badge overlay on non-compliant items.
- **CPT edit screen**: admin notice when the post's cover is non-compliant.
- **Batch modal**: a "N images non-compliant for Metricool" warning block
  with a "Fix all" button that opens the crop modal sequentially. (The CPT
  batch plugins — `alf_wp_stories`, `bulk-pp-gallery`, `bulk-cpt-pay` —
  emit a `wpAlfImgRatioCheck` JS event with the selected attachment IDs so
  the compliance plugin can decorate their modals without coupling.)

### G. Story-specific integration note (cross-ref to `002`)

For `alf_wp_stories` specifically:
- The 9:16 cover is non-compliant. The recommended workflow becomes:
  batch-create stories → compliance check flags covers → crop covers to 3:4
  (or keep a 9:16 frame as the viewer image and use a **separate 3:4
  derivative as the social/cover image**).
- This raises a design question (see Open Questions): does the story keep its
  9:16 frame for the viewer and expose a separate compliant "social cover"
  for RSS/Metricool? Or does the cover itself get cropped to 3:4 and the
  viewer shows the cropped version?

---

## Acceptance Test

- [ ] Installing the plugin with no CPT plugins active does nothing visible.
- [ ] `alf_wp_stories` registers a context; a 9:16 cover shows a red badge.
- [ ] A 1:1 image shows a green badge.
- [ ] Media Library list has a Ratio column.
- [ ] Crop modal offers 3:4, 1:1, 4:5, 1.91:1 presets.
- [ ] Cropping a 9:16 image to 3:4 produces a derivative and turns the badge
      green without destroying the original (in `derivative` mode).
- [ ] `wp_alf_img_ratio_check_passed` fires after a successful crop.
- [ ] Batch-create modal in `alf_wp_stories` warns when covers are
      non-compliant, with a "Fix all" path.
- [ ] Same integration works for `bulk-pp-gallery` and `bulk-cpt-pay` after
      they each add one `add_filter` call.

---

## Decisions (locked from user answers)

1. **Crop mode default** — **`derivative`**. Preserves the original 9:16 frame
   for the story viewer; a 3:4 derivative serves RSS/OG/Metricool. Lineage is
   tracked via `_wp_alf_img_ratio_parent` so the derivative can be traced back
   to its source.

2. **Story cover strategy** — **Option A (separate `social_cover`).** Stories
   keep their 9:16 cover for the viewer. A separate
   `_alf_wp_stories_social_cover` (3:4 derivative) is used by RSS/OG/Metricool.
   This preserves the immersive viewer from `001` while satisfying Metricool.
   The `alf_wp_stories` plugin adds this meta field and a small RSS/OG override
   in doc `002`'s implementation.

3. **Auto-crop on upload** — **reactive flagging only.** No silent cropping.
   Bad ratios are flagged in the Media Library, the CPT edit screen, and the
   batch modal; the user chooses to fix via the crop modal.

4. **Plugin location** —
   `/Users/alvarsirlin/Sites/WP/wp_plugins/wp-alf-img-ratio-check/`
   (repo: `github.com/alvarix/wp-alf-img-ratio-check`). Sibling to the three
   CPT plugins; declared as a suggested companion, never bundled.

5. **Default ratio bounds** — Metricool's **3:4 (0.75) – 1.91:1 (1.91)**,
   inclusive on both ends. Per-context overrides may narrow but not widen.

---

## Summary

This plugin isolates ratio compliance as a standalone, data-driven checker
with a registry-based integration contract, so each CPT plugin opts in with a
single filter and gets flagging, a crop modal, and a re-check lifecycle
without coupling to the others. Defaulting to `derivative` mode and a separate
`social_cover` field for stories preserves the 9:16 viewer experience while
satisfying Metricool's 3:4–1.91:1 auto-publish requirement.
