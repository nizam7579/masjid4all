# Spec — Demand-Driven Content Generation Queue

**Status:** agreed 2026-08-16, not yet built.
**Target plugin:** `mfa-core` (new `includes/generation-queue.php` + an admin widget).
**Replaces:** the per-page "Click to Update" button in
`mfa-core/includes/widgets/mosque-single.php` and its business equivalent.

---

## 1. The model

Crawling is cheap (~$0.0015/cell, ~$1,600 for the entire world grid). AI content
generation is not (~$700–1,500 for ~137K listings; five figures at 1M). So the
platform indexes everything cheaply and spends generation budget **only where
real visitors show up**.

A visitor arriving at a `New` listing should cause that listing to get real
content, without the visitor doing anything and without being told the page is
incomplete.

**The visit enqueues. A worker generates.** A page request must never make a
paid API call.

### Why not generate on the request

Two blockers, both specific to this stack:

1. **No background work on this host.** LiteSpeed/Hostinger kills fire-and-forget
   loopback requests, so `wp_remote_post(..., 'blocking' => false)` is unreliable
   here — the same constraint that forces `niz-wa` to process webhooks
   synchronously and the crawler to process cells synchronously. Generating
   inline instead would block the page load for the length of a Perplexity call,
   damaging both the visitor experience and Core Web Vitals on exactly the pages
   we are trying to rank.

2. **Bot traffic scales with SEO success.** The `/places/` hubs exist to send
   Googlebot deep into ~185K listing pages. If a page view could trigger
   generation, a successful crawl becomes a bill, and the better the SEO works
   the larger it gets. Enqueueing makes cost a function of our own daily cap
   rather than of crawler volume.

---

## 2. Prerequisite: pull `enaizi/` into the mirror

**Do this before writing any code.** The generation engine this queue must call
is not in the local repo:

- `mosques_perplexity()` — called at `enaizi-mfa/shortcodes/mosque.php:119`,
  **defined nowhere in `plugins/` or `themes/`**
- `m4a_is_bot_request()` — called at `enaizi-mfa/shortcodes/mosque.php:91`,
  same situation
- `mfa_business_perplexity()` — this one *is* mirrored, in
  `enaizi-mfa/shortcodes/add-business.php:395`

Both missing functions live in the original `enaizi` plugin, which has never
been mirrored. Two consequences:

1. The worker needs a **callable** generation path. The existing entry point,
   `niz_mfa_mosques_callback()`, is an AJAX handler that reads `$_POST` and
   emits `wp_send_json_*` — unusable from WP-CLI or a cron worker. It needs a
   plain function extracted from it (`( $post_id, $name ) → result array`), with
   the AJAX handler becoming a thin wrapper. That refactor cannot be written
   safely against code we cannot read.

2. `enaizi/mosque.php` contains a **hardcoded Perplexity API key**
   (`define('PERPLEXITY_API_KEY', 'pplx-...')`). CLAUDE.md records that secrets
   were cleaned out of the four `enaizi-*` plugins; `enaizi` is the fifth and was
   missed. Since this work touches that exact call path, move the key to a
   `wp-config.php` constant as part of it. Do not build on top of a hardcoded
   key.

---

## 3. Data

New plain custom table (not a JetEngine CCT), `dbDelta()` on a version-gated
option, following `wp_mfa_geohash`'s pattern in `includes/geohash.php`.

**`wp_mfa_gen_queue`**

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT AUTO_INCREMENT | PK |
| `listing_type` | VARCHAR(10) | `mosque` \| `business` |
| `listing_id` | BIGINT | `jet_cct_*._ID` |
| `post_id` | BIGINT | the CPT post |
| `hits` | INT | human visits since queued — the ranking key |
| `status` | VARCHAR(10) | `Queued` \| `Done` \| `Failed` \| `Skipped` |
| `attempts` | TINYINT | give up after 3 |
| `last_error` | VARCHAR(255) | |
| `first_seen` / `last_seen` | DATETIME | |
| `generated_at` | DATETIME NULL | |

- **UNIQUE KEY on (`listing_type`, `listing_id`)** — this is what makes the
  enqueue idempotent.
- Index on (`status`, `hits`) for the drain query.

---

## 4. Enqueue (the request path)

Fires on `template_redirect` for a singular `masjid`/`business` whose
`listing_status` is `New`.

```
INSERT INTO wp_mfa_gen_queue (...) VALUES (...)
ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW()
```

Rules:

- **One write, no reads of consequence, never an API call.**
- **Skip bots** using the existing guard once it is visible (`m4a_is_bot_request()`),
  so `hits` reflects human demand. A stray bot row costs nothing, but inflated
  counts would mis-rank the queue.
- **Skip** if the row is already `Done` (the listing is no longer `New` anyway).
- **No UI change on the page.** No banner, no "come back in a minute". The page
  already carries name, address, phone, website and (once built) per-location
  prayer times — telling a visitor it is not ready converts a usable page into a
  bounce, on precisely the page we are trying to rank.
- If the visitor is a **logged-in member**, award Barakah via
  `mfa_award_points()` — this is also the first fix for the "contributing earns
  nothing" gap. Description must be unique per listing, since
  `mfa_award_points()` dedupes on `(user_id, description)`.

---

## 5. Drain (the worker)

Mirror `geohash-crawl.php` — it is the proven pattern on this host and the team
already knows how to operate it.

- `mfa_gen_queue_run_batch( $limit )` — claims `Queued` rows ordered by
  `hits DESC, first_seen ASC`, generates, writes content + RankMath meta, flips
  `listing_status`, marks `Done`.
- **Claim with `SELECT ... FOR UPDATE`** and a `Claimed` status, not a bare
  `status='Queued'` select — the crawler hit exactly this race with two
  concurrent tabs.
- **Concurrency cap via MySQL `GET_LOCK`**, not a counter (a counter leaks a
  slot forever if a request dies mid-way).
- **Daily cap + budget guard + pause state** as `mfa_gen_*` options, matching
  `mfa_crawl_*`. Proactive pause before the provider's own error.
- **Stop on first API error**, leaving rows `Queued` so a run is resumable.
- Triggers: WP-CLI (`wp mfa gen-run`) for bulk, plus the admin panel. Given the
  crawler's history — Hostinger's scheduler never fired, and the REST trigger
  silently under-performed on live — **prefer a real system crontab entry over
  SSH** for automation, and instrument cells-claimed vs. cells-completed per tick
  from the start so a repeat of that failure is visible rather than silent.

---

## 6. Admin panel

`/admin/generate/` — `[mfa_admin_generate]`, matching the crawler panel's
plain-form, no-JS style, and slug-matched (not page-ID-matched) so the plugin
folder copies to live with no manual edits.

Shows: queued / done / failed counts, credits or spend used against budget,
pause state and Resume, daily cap control, and the **top queued listings by
hits** — which doubles as the report of *which cities are earning traffic worth
promoting*.

---

## 7. Acceptance

1. A logged-out human visit to a `New` mosque page inserts one row and makes no
   outbound API call (verify: no provider request in the log, page TTFB
   unchanged).
2. A second visit to the same page increments `hits` to 2 and does not insert a
   second row.
3. A declared-bot visit inserts nothing.
4. `wp mfa gen-run --limit=2` generates the two highest-`hits` listings, writes
   content and RankMath meta, flips `listing_status`, and marks them `Done`.
5. Two concurrent runs never generate the same listing twice.
6. With the daily cap reached, a run stops cleanly and reports why.
7. A logged-in member's visit awards Barakah exactly once for that listing.

---

## 8. Open

- **Daily cap and per-city budget** — the real cost decision, still unset.
- **Bot guard quality** — unknown until `enaizi/` is pulled. A user-agent check
  catches declared crawlers, not headless traffic; consider a hit threshold
  (generate on the 2nd or 3rd visit, not the 1st) as a cheap second gate.
- **Websites (`jet_cct_web`)** — same treatment later, deliberately out of scope
  for v1.
