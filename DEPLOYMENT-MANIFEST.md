# Staging → Live Deployment Manifest

**Status: PUSHED TO LIVE on 2026-08-06** via Hostinger Full Sync, after a
fresh live backup. Live had only 3 new users and no new listings since the
2026-07-30 clone, so the risk was accepted rather than doing the
selective-replay approach originally planned below. Post-push verification
(browsing masjid4all.com directly) confirmed: homepage, `/member/`,
`/register/`, `/add-mosque/`, `/add-business/`, `/add-website/`, `/test/`,
`/member/premium/`, live Business/Mosque/Website listings (Home tab content,
Review/Claim tabs), and the Sofia button removal all landed correctly. A
batch of console errors on the Knowledge Hub page was checked against
staging and confirmed pre-existing, not a regression from the push.

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

## Sitewide footer: Sofia chat button removed

| Page | Staging post ID | What changed |
|------|-----------------|---------------|
| Sitewide footer ("Footer Menu" template) | 83 | Removed the "Sofia" column entirely (floating chat-launcher image + its `#chatbot` modal, ~34KB of markup). Note: `blockVisibility:{"hideBlock":true}` was tried first and did NOT take effect here — footer/Theme-Builder rendering apparently doesn't run the same visibility filter that normal page content does (confirmed via fresh `x-litespeed-cache: miss` server response, so it wasn't a caching issue). Had to remove the block markup outright instead. Worth remembering for any other footer/header-level `blockVisibility` hides. |

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

## E. New database tables

`niz-wa` creates 6 tables (`wp_nwa_conversations`, `wp_nwa_messages`,
`wp_nwa_actions`, `wp_nwa_knowledge_base`, `wp_nwa_user_profiles`,
`wp_nwa_contacts`) via `dbDelta()` on `register_activation_hook`
([class-nwa-db.php](plugins/niz-wa/includes/class-nwa-db.php)). No manual
migration needed — activating the plugin on live creates them automatically,
idempotently. `mfa-core` creates no custom tables at all.

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
- [ ] **niz-wa's live-side state is still unresolved.** Full Sync would have
      deployed `niz-wa`'s code to live, but whether the plugin is *activated*
      there, and which domain's webhook URL is registered in Meta's App
      Dashboard (staging vs. live), was never confirmed. niz-wa auto-creates
      `prospect` WordPress users for any unrecognized WhatsApp number via
      `nwa_resolve_user_id` → `niz_user_create_prospect()`
      ([niz-wa-integration.php:15-35](plugins/mfa-core/includes/niz-wa-integration.php)) —
      worth checking Meta's dashboard before assuming this is inactive on live.
- [x] Mosque template post 875/`mosque-2-2` — confirmed still correctly
      matched and rendering on live (`/masjid/masjid-samakom-islam-.../`
      verified with Review tab hidden as expected).
