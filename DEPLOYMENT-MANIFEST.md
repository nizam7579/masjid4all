# Staging → Live Deployment Manifest

**Status: PUSHED TO LIVE on 2026-08-06, and again on 2026-08-07** (both via
Hostinger Full Sync). See "Post-push status (2026-08-07)" below for what the
second push covered — the summary immediately below is the original
2026-08-06 push record, left as-is for history.

Original 2026-08-06 push: after a fresh live backup, with live having only 3
new users and no new listings since the 2026-07-30 clone, so the risk was
accepted rather than doing the selective-replay approach originally planned
below. Post-push verification (browsing masjid4all.com directly) confirmed:
homepage, `/member/`, `/register/`, `/add-mosque/`, `/add-business/`,
`/add-website/`, `/test/`, `/member/premium/`, live Business/Mosque/Website
listings (Home tab content, Review/Claim tabs), and the Sofia button removal
all landed correctly. A batch of console errors on the Knowledge Hub page was
checked against staging and confirmed pre-existing, not a regression from the
push.

The sections below are now a historical record of what was pushed, not a
still-pending replay plan.

---

Originally: tracks exactly what changed on staging.masjid4all.com during the
"refactor all public pages" phase, so it could be safely replayed on live
(masjid4all.com) without touching Hostinger's Database/Full publish (which
would overwrite live's real users, listings, and WhatsApp history — see the
staging/live sync discussion in this project's chat history for why). In the
end, a Full Sync was used instead, once the live-data risk was confirmed low.

Staging was cloned from live on **2026-07-30**.

Last updated: 2026-08-06 (after live push + verification).

## Phase 2: login/register module removal (complete, pushed to live)

Separate from the page-refactor content in Sections B/C below — this batch
hides the login/register UI across the pages that have it, ahead of the
live push. `users_can_register` was also flipped to `0` on staging
(Settings → General → Membership) — a plain option value, replay the same
setting on live, not a content/code change.

| Page | Staging post ID | What changed |
|------|-----------------|---------------|
| `/member/` | 70180 | "Not LoggedIn" (logged-out) Kadence section rebuilt off Kadence via new `[mfa_member_logged_out]` shortcode ([member-logged-out.php](plugins/mfa-core/includes/widgets/member-logged-out.php)); `[niz_login]` form removed, "Registration and login will be available soon." notice added. Logged-in member dashboard untouched. |
| `/register/` | 37828 | `[niz_register]` + `[mfa_member_register]` replaced with `[mfa_coming_soon message="Registration will be available soon."]`. |
| `/add-mosque/` | 225511 | "LogedOut" column's heading + `[niz_login]` replaced with `[mfa_coming_soon]`. Note: logged-in view already showed "Coming Soon" with the real form (`[niz_mfa_add_mosque]`) already hidden — pre-existing, not something this round changed. |
| `/add-business/` | 225426 | Same "LogedOut" column treatment as add-mosque. |
| `/add-website/` | 223630 | Same "LogedOut" column treatment as add-mosque. |
| Mosque post Review tab | 875 | "LoggedOut" column (heading + `[niz_login]`) replaced with `[mfa_coming_soon]`. Sibling "LoggedIn" column (`[niz_review]`) untouched. |
| Business post Review + Claim tabs | 9151 | Both "LoggedOut" (Review) and "Not LoggedIn" (Claim) columns replaced with `[mfa_coming_soon]`. |
| Website post Review + Claim tabs | 220902 | Same as Business — both Review and Claim login columns replaced with `[mfa_coming_soon]`. |

New shared shortcode: [`[mfa_coming_soon]`](plugins/mfa-core/includes/widgets/coming-soon.php) (+ [coming-soon-v1.css](plugins/mfa-core/assets/css/coming-soon-v1.css)), enqueued sitewide since it appears inside Theme Builder template content that `has_shortcode()` can't detect from the front-end post being viewed.

**Found by a sitewide sweep (not on the original list), also fixed:**

| Page | Staging post ID | What changed |
|------|-----------------|---------------|
| `/test/` | 68195 | "Not LoggedIn" section (near-duplicate of the /member/ dashboard, ~111KB) — `[niz_login]` + marketing panel replaced with `[mfa_coming_soon]`. `publish` status, not linked from any menu, reachable by direct URL only. |
| `/member/premium/` | 229729 | Same treatment as /test/ — same near-duplicate structure. `publish` status, not linked from any menu. |

⚠ Two **draft** Theme Builder duplicates also still contain the old `[niz_login]` pattern — `230538` "Mosque (Copy)" and `230539` "Business (Copy)". **Deleted** on 2026-08-06 (permanently — `kadence_element` doesn't support WP's trash status, so `wp_delete_post()` hard-deleted them despite requesting trash).

## Sitewide footer: Sofia chat button removed (2026-08-06), then restored (2026-08-07)

| Page | Staging post ID | What changed |
|------|-----------------|---------------|
| Sitewide footer ("Footer Menu" template) | 83 | Removed the "Sofia" column entirely (floating chat-launcher image + its `#chatbot` modal, ~34KB of markup). Note: `blockVisibility:{"hideBlock":true}` was tried first and did NOT take effect here — footer/Theme-Builder rendering apparently doesn't run the same visibility filter that normal page content does (confirmed via fresh `x-litespeed-cache: miss` server response, so it wasn't a caching issue). Had to remove the block markup outright instead. Worth remembering for any other footer/header-level `blockVisibility` hides. |

**Restored 2026-08-07** now that niz-wa is confirmed standalone: rebuilt as a plain
`[mfa_sofia_button]` shortcode (`plugins/mfa-core/includes/widgets/sofia-button.php`)
instead of a Kadence block/modal, matching `[mfa_share_button]`'s pattern. Same
WhatsApp click-to-chat link and copy as the original (recovered from post 83
revision 230563: `https://api.whatsapp.com/send?phone=60189897579&text=I+have+a+question`,
"Hi, I'm Sofia, your friendly AI assistant..."). Embedded in post 83 right next to
`[mfa_share_button]`. Floating buttons now stack bottom-to-top: sofia (24px/90px
mobile) → share (88px/154px, moved up from its old 24px/90px) → Kadence's native
`#kt-scroll-up` (160px/210px, unchanged).

## How to use this doc

1. **Code changes** (plugin files under `plugins/mfa-core/`, etc.) — safe to
   deploy via Hostinger's "Files Only" / "specific files and folders" push.
   No manual replay needed, just make sure the file list below matches what's
   actually changed before pushing.
2. **Content changes** (post_content/postmeta edited directly in staging's
   DB) — do NOT push via any database sync option. Replay each post's final
   content by hand directly on live (same `wp_update_post()` technique used
   throughout this project), once write access to live is available.
3. **Options** — see the explicit safe/unsafe split below before copying
   anything.
4. Keep this file updated after every further page/round of the refactor.

---

## A. Code (plugin files — safe via Files Only sync)

- `plugins/mfa-core/` — entire plugin, newly created this project:
  - `mfa-core.php`
  - `includes/identity-email.php`, `identity-core.php`, `niz-wa-integration.php`, `whatsapp-verify.php`
  - `includes/widgets/` — prayer-times, qibla, daily-quran, set-cookies, share-button, qr-code, user-logout, homepage-stats, quran-page, quran-single, tool-pages, brand-pages, legal-pages, directory-pages, business-single, mosque-single, website-single
  - `includes/widgets-enqueue.php`
  - `assets/css/`, `assets/js/` — versioned filenames (e.g. `business-single-v4.css`, `share-button-v12.css`) — remember LiteSpeed strips `?ver=`, so live needs the same filenames, not just content, if cache-busting matters.
- `plugins/niz-wa/` — mirrored copy, not built fresh here (see project CLAUDE.md). **Verify state on live separately — see Section F, this is likely NOT part of the current public-pages push.**

Not part of this phase's code changes: `enaizi-identity`, `enaizi-mfa`,
`enaizi-user` (untouched so far), `enaizi_wa` (retired on staging only).

---

## B. Content — Theme Builder template posts (kadence_element)

These are the small, fixed set of *template* posts that control layout for
every listing of a given type — NOT the listings themselves. Editing these
on live is safe as long as only these specific post IDs are touched.

| ID | Title | post_name | Display rule | What changed |
|----|-------|-----------|--------------|---------------|
| 9151 | Business | `business` | `singular\|business` | Home tab → `[mfa_business_home_tab]`; Share modal removed; featured-image fallback → production CDN; Nearby Mosques tab rebuilt off Kadence; right-column nearby-mosque list added |
| 875 | Mosque | `mosque-2-2` ⚠ | `singular\|masjid` | Featured-image fallback → production CDN; Home tab rebuilt off Kadence + Share button removed; Local Business tab rebuilt off Kadence |
| 220902 | Web | `web` | `singular\|web` | Featured-image fallback + dropped update-content prompt in `mfa_website_info`; Home tab → `[mfa_website_home_tab]` |
| 193845 | Knowledge | `knowledge` | `singular\|knowledge` | Share modal + trigger removed; rowlayout resized to 71.1/28.9 (matches Business/Mosque); `[niz_mfa_knowledge_directory]` columns 2→1 |

⚠ Mosque template's `post_name` is `mosque-2-2`, not `mosque` — likely a
leftover from a Kadence duplicate-and-replace at some point. Confirm this is
still the correct live template post (matched via the
`_kad_element_show_conditionals` = `singular|masjid` postmeta rule, not the
slug) before replaying edits on live — don't assume ID 875 exists/means the
same thing on live without checking.

---

## C. Content — standalone pages

| ID | Title | post_name | post_type | What changed |
|----|-------|-----------|-----------|---------------|
| 230457 | Home | `homepage` | `page` | New homepage content built from scratch |
| 224708 | Prayer Times | `prayer-times` | `page` | Rebuilt page |
| 224712 | Qibla Finder | `qibla-finder` | `page` | Rebuilt page |
| 116 | About Us | `about-us` | `page` | Rebuilt page |
| 118 | Contact Us | `contact-us` | `page` | Rebuilt page |
| 120 | Privacy Policy | `privacy-policy` | `page` | Rebuilt page |
| 122 | Terms of Service | `terms-of-service` | `page` | Rebuilt page |

⚠ Post ID 224083 is a **different, unrelated post** also titled "Prayer
Times" but with `post_type = web` (i.e. a real Website-listing CPT entry
that happens to share the title) — not the tool page. Don't confuse it with
224708 when replaying content on live.

---

## D. Options (`wp_options`)

Queried live options with `mfa_`, `nwa_`, `niz_` prefixes on staging:

**Config/settings — review case-by-case, don't blanket-copy:**
- `nwa_settings` — niz-wa AI provider/model/key (per CLAUDE.md, wp-admin-editable; live should likely have its own value, not staging's test config)
- `niz_wa_ai_settings`, `niz_wa_whatsapp_settings`
- `niz_wapi_ai_settings`, `niz_wapi_ai_provider`, `niz_wapi_ai_max_tokens`, `niz_wapi_ai_temp`, `niz_wapi_whatsapp_settings`
- `niz_wapi_db_version`, `niz_wapi_version`
- `niz_identity_options`

**Look like secrets — do NOT copy to live under any circumstances, live must have its own:**
- `niz_cms_deepseek_api_key`
- `niz_cms_openai_api_key`
- `niz_wapi_encryption_key`
- `niz_wapi_webhook_secret`

None of the above were knowingly changed as part of *this* page-refactor
phase — they predate it (niz-wa/identity build-out). Listed here so nothing
gets swept up accidentally if a DB-table push is ever considered. No option
changes are currently known to be required for the page-refactor content
itself (Sections B/C don't depend on any option value).

---

## E. Database schema — auto-synced by our plugins (no DB push needed)

**As of the 2026-08-12 "last DB-inclusive push" cutover:** after this push,
live is updated by uploading plugin files only (no Hostinger DB sync — there's
no option to exclude the DB, so a DB sync would clobber live's real data). Our
plugins therefore self-manage their own schema: on every load they compare a
stored version option against a code constant and, if it differs, run
`dbDelta()`. `dbDelta()` is **non-destructive** — it creates missing tables and
adds missing columns/indexes but never drops columns, indexes, or data — so it
is safe to run against live data. This runs on `plugins_loaded` (not just
`register_activation_hook`, which only fires on a fresh activation, not when
files change under an already-active plugin), so a plain plugin-file upload
syncs the schema automatically.

**Custom tables we own (all `dbDelta`, version-gated on `plugins_loaded`):**

| Table | Owner plugin | Defined in | Version const → option |
|-------|--------------|------------|------------------------|
| `wp_mfa_commission_ledger` | mfa-core | `includes/commission.php` (`mfa_commission_maybe_create_table`) | `MFA_COMMISSION_TABLE_VERSION` → `mfa_commission_table_version` |
| `wp_mfa_member_activity` | mfa-core | `includes/activity-log.php` (`mfa_activity_maybe_create_table`) | `MFA_ACTIVITY_TABLE_VERSION` → `mfa_activity_table_version` |
| `wp_mfa_geohash` | mfa-core | `includes/geohash.php` (`mfa_geohash_maybe_create_table`) | `MFA_GEOHASH_TABLE_VERSION` → `mfa_geohash_table_version` |
| `wp_nwa_conversations`, `wp_nwa_messages`, `wp_nwa_actions`, `wp_nwa_knowledge_base`, `wp_nwa_user_profiles`, `wp_nwa_contacts` | niz-wa | `includes/class-nwa-db.php` (`NWA_DB::create_tables`) | `NWA_DB_VERSION` → `nwa_db_version` |

(Older Section-E text said "mfa-core creates no custom tables" and that niz-wa
synced on activation only — both now out of date; corrected here. niz-wa's
`plugins_loaded` version-gated sync was added 2026-08-12.)

**Recipe — adding a table / column / index so it auto-syncs to live on the
next plugin upload (without touching live data):**

1. Edit the `dbDelta()` `CREATE TABLE` definition for the table (add the new
   column, or a new `KEY name (col)` index, or add a whole new
   `CREATE TABLE`). Follow dbDelta's strict formatting (two spaces after
   `PRIMARY KEY`, `KEY`/`UNIQUE KEY name (col)`, one field per line).
2. **Bump the matching version constant** (`*_TABLE_VERSION` / `NWA_DB_VERSION`).
3. Upload the plugin files to live. On the next admin/page load the stored
   option ≠ the new constant, so `dbDelta()` runs once and ALTERs the table to
   match — adding the new column/index, keeping all existing rows.
4. For genuinely new persistent data, always add **our own** `dbDelta` table
   (or a column on one of the tables above) — never a new JetEngine CCT and
   never a new column on a `jet_cct_*` table (JetEngine owns those; per
   CLAUDE.md we read/write them but don't extend their schema).

**Not ours to sync:** `jet_cct_*` (JetEngine), and all third-party tables
(BuddyPress `bp_*`, FluentCRM/Cart/Community `fc_*`/`fct_*`/`fcom_*`, RankMath,
Wordfence `wf*`, Kadence, LiteSpeed, WP All Import `pmxi_*`, Novamira, etc.) —
each managed by its own plugin's upgrade routine. The final DB-inclusive push
carries all of these to live in a consistent state; from then on our concern is
only the tables listed above.

---

## F. Explicitly out of scope / needs a separate decision

- **niz-wa / WhatsApp AI system** — CLAUDE.md describes its cutover as
  complete "on staging"; live's current state (still running old
  `enaizi_wa`? niz-wa not deployed at all? already migrated?) is unknown
  from here and untouched by this project's public-pages work. Don't assume
  it should move with this push — confirm with the user first.
- **Login/registration hiding** — done, see Phase 2 above. Pushed to live.
- **enaizi-identity / enaizi-user / enaizi-mfa consolidation** — explicitly
  marked "not started" in CLAUDE.md, unrelated to this push.

---

## Post-push status (2026-08-06)

- [x] Hide login/registration UI (Phase 2) — done, verified live.
- [x] Take a fresh Hostinger backup of live before pushing — done.
- [x] Live push executed (Full Sync) and verified — homepage, all
      login/register pages, live Business/Mosque/Website listings, and
      Sofia removal all confirmed correct on masjid4all.com.
- [x] **niz-wa's live-side state — resolved 2026-08-06.** Confirmed via
      Meta's App Dashboard that the WhatsApp webhook is pointed at
      **staging only**, never live — so real WhatsApp traffic has only ever
      reached staging. Separately, `niz_wa_resolve_user_id()`
      ([niz-wa-integration.php](plugins/mfa-core/includes/niz-wa-integration.php))
      no longer auto-creates WordPress `prospect` users for unrecognized
      numbers — it does a read-only lookup for already-existing members only;
      new numbers now fall through to niz-wa's own standalone
      `wp_nwa_contacts` table. Verified on staging: an unrecognized test
      number resolved with zero new WP users created (94 before/after).
      This fix is staging-only so far (not yet pushed to live) since it
      doesn't affect live's behavior while the webhook points elsewhere.
- [x] Mosque template post 875/`mosque-2-2` — confirmed still correctly
      matched and rendering on live (`/masjid/masjid-samakom-islam-.../`
      verified with Review tab hidden as expected).

---

## Post-push status (2026-08-07)

Second live push (Hostinger Full Sync), covering everything built on staging
since the 2026-08-06 push:

- Header rebuild follow-ups: desktop "Tools" trigger opening the mobile
  popup, mobile logo/padding/Login-Register-pill redesign, richer popup
  content (icons, 2-column TOOLS/INFORMATION grid, location widget), and the
  popup/overlay z-index + bottom-padding fixes so it correctly covers the
  floating button stack.
- Sofia floating WhatsApp button restored (as `[mfa_sofia_button]`, a plain
  shortcode + custom modal rather than the old Kadence block), repositioned
  into a 3-button stack with Share and Kadence's native scroll-to-top.
- Sitewide bottom clearance for the floating button stack (`body`-level
  padding, replacing the old per-template version that missed several
  pages).
- Gutter/spacing fixes: Prayer Times (desktop column width + mobile FAQ
  gutter), Qibla Finder (desktop compass size + mobile FAQ gutter),
  Knowledge Hub single-post mobile overflow, Contact Us form mobile
  padding, `/member/` logged-out panel mobile gutter.
- Mosque/Business sidebar "nearby" listings no longer include the listing
  being viewed.
- Homepage: invisible "Add a Mosque" button text fixed (was a CSS
  specificity bug rendering teal-on-teal), button font size increased,
  and the 4 small impact cards' icon+title moved onto one line.

**Not independently verified by me post-push this time** — the user executed
the Hostinger sync directly and confirmed completion, but I haven't browsed
masjid4all.com myself to re-check these landed correctly (unlike the
2026-08-06 push above). Worth a spot-check on live if anything looks off.
