# Masjid4All — Claude Code Project Instructions

## Environment
- **Site**: staging.masjid4all.com (WordPress, staging environment)
- **Connection**: Novamira MCP plugin + `@automattic/mcp-wordpress-remote`
- **Purpose of this connection**: plugin development, WordPress design/theme work
- **Production**: masjid4all.com — never push directly to production from here. All changes go through staging first and get reviewed before promotion.

## Working Model — Claude Desktop vs Claude CLI
Two Claude surfaces are used on this project, with deliberately different jobs.
Respect the boundary.

- **Claude Desktop = discuss & scope only. No coding.** The user works in
  Desktop to talk through and scope a module — it's easier to read there and to
  attach documents/images. Desktop's job is to help think through requirements,
  agree on scope, and then **produce a written implementation brief for Claude
  CLI to execute.** If you are Claude on Desktop: do not write code and do not
  edit the codebase. Finish by handing off a clear, self-contained
  implementation instruction for the CLI (what to build, where, constraints,
  acceptance criteria).

- **Claude CLI (Claude Code) = implement, but propose first — never jump
  straight to coding.** The CLI does the actual coding and staging work, but
  the user has repeatedly seen Claude start coding before they finished
  explaining, producing work that's off-target. So:
  - **Let the user finish.** If they're still describing the requirement, do
    not start editing files. Assume more explanation is coming unless they say
    otherwise.
  - **Restate + propose before acting.** Reflect back what you understood and
    show what you plan to do (the approach, files, and any flow/copy), then
    **wait for an explicit go-ahead** before writing code or changing staging.
  - **Investigation is fine; implementation waits.** Reading code, inspecting
    the DB/schema, and asking clarifying questions to form the proposal are
    encouraged. Creating/editing code or mutating staging is what waits for
    confirmation.

  Order of operations for the CLI: **understand → propose → confirm →
  implement.** When in doubt, show the plan and ask rather than assume. When the
  CLI is handed a Desktop-authored brief, still restate your understanding of it
  and confirm before coding, rather than executing it blind.

## Site Context
Masjid4All is an Islamic digital ecosystem: mosque directory, prayer times, Qibla
direction, halal business listings, Islamic knowledge resources, and an AI WhatsApp
assistant. (The old `enaizi_wa` assistant was branded "Sofia" — that persona wasn't
carried into the current `niz-wa` plugin; its AI prompts are generic/unbranded.)
Content and UX should respect Islamic terminology conventions and JAKIM-aligned
halal compliance where relevant. Default language is English; Bahasa Melayu content
may appear alongside it — don't auto-translate without asking.

## Plugin Architecture
**Target end state (agreed 2026-08-07): three custom plugins, no more.**
Everything else either folds into one of these or gets deleted once no longer
needed for reference:
- `niz-wa` — standalone, WhatsApp/AI backend. Stays separate; don't fold it
  into `mfa-core`.
- `mfa-core` — the one consolidated plugin for everything else site-facing:
  identity/user/auth (absorbing `enaizi-identity` and `enaizi-user`) *and*
  general site functionality (absorbing `enaizi-mfa` — its mosque/business/
  website/knowledge directory shortcodes, not just identity/auth despite the
  name). This consolidation is **already underway, not a separate future
  effort** — most of this project's actual page-rebuild work this year has
  been building directly into `mfa-core` (see `plugins/mfa-core/mfa-core.php`'s
  own docblock: "Replaces enaizi-identity and enaizi-user (phased)").
  Treat any further identity/user/directory work as *continuing* this
  consolidation, not a separate initiative to schedule later.
  **The full absorption list is five plugins, not three** (confirmed
  2026-08-10): `enaizi`, `enaizi-ads`, `enaizi-identity`, `enaizi-mfa`, and
  `enaizi-user` are all initial/legacy plugins being folded into `mfa-core`.
  `enaizi` in particular (folder `enaizi/`, plugin file `enaizi.php`,
  "Enaizi - Masjid4all Plugin" v2.0.1) is an older, more monolithic plugin
  with its own registration flow, admin member panel (`admin-member.php`),
  and WhatsApp API helpers (`wapi-functions.php`, `wapi-member.php`,
  including a `whatsapp_send_message()` function — check for overlap with
  `niz-wa`'s sender before assuming it's redundant). Several of its files
  (e.g. `member.php`, ~69KB) have large stretches of dead code sitting
  inside `/* */` block comments — verify liveness with a tokenizer or
  careful reading, not plain text search, before assuming a function
  definition or call site is actually executing. Its live code is still
  depended on elsewhere (e.g. `member_cct_data()`/`get_cct_member_data()`
  in `enaizi-user/includes/member.php`, despite being marked "OLD", have
  live callers in `enaizi/admin-member.php`, `user.php`,
  `wapi-functions.php`, `wapi-member.php`, and `affiliate.php`) — don't
  delete anything in the `enaizi-*` plugins as an "unused duplicate" without
  checking for callers across *all five* plugins in this list, not just the
  plugin the code happens to live in. `enaizi-ads` has not yet been
  investigated.
- `niz-pwa` — **planned, not started.** Will eventually be split back out of
  `mfa-core` to own notifications and other PWA-specific features. No urgency
  yet. Note: `plugins/enaizi-mfa/includes/pwa.php` has a dormant (fully
  commented-out) "PWA global bar + GA analytics" concept from an earlier
  attempt — worth a look when this actually starts, not necessarily worth
  reviving as-is.
- `enaizi_wa` — **retired, inactive**, historical reference only (see cutover
  status below). Delete once its `wp_niz_wa_*` tables/FAQ history are no
  longer needed for reference — not urgent.

**No new third-party plugins** going forward except RankMath, JetEngine, and
other exceptions explicitly approved as critical on a case-by-case basis.
Prefer building functionality into our own plugins over adding a dependency.
One concrete existing exception to phase out over time: a few `mfa-core`
shortcodes (business-single, mosque-single) still lean on **Kadence Blocks
Pro's modal JS** for popups — replace with our own custom modal pattern (the
Sofia popup and header mobile menu are the proven template) as those areas
get touched, rather than leaving the dependency in place indefinitely.

**JetEngine's status is more specific than "approved exception" (refined
2026-08-07/2026-08-10): keep the tables, stop expanding the usage.** Its
existing MySQL tables (`wp_jet_cct_*` — member, business, mosque, activity,
whatsapp, etc.) and PHP runtime stay; they're the underlying data layer for
these records and aren't going away, and reading/writing them via `$wpdb`
directly remains correct (see the member CCT rule below, which now applies
to all JetEngine tables generally, not just that one). But *using more* of
JetEngine going forward is being phased out, mirroring what's already been
done on the user's other site: no new JetEngine Custom Content Types, no
JetEngine Listing Grid/Query Builder blocks, no JetEngine filters for new
admin or front-end screens. Build against the existing tables directly with
hand-written PHP + `$wpdb` instead — `/admin/member/`'s
`[mfa_admin_member_list]` is the reference pattern. Any genuinely new
data-storage need should be a plain custom WordPress table (the standard
`dbDelta()`-on-activation pattern, like `niz-wa`'s own `wp_nwa_*` tables),
never a new JetEngine CCT.

**FluentForm, same direction:** existing FluentForm-based forms keep
working, but build new forms as hand-written HTML/PHP/JS rather than adding
more FluentForm forms.

Current custom plugins on disk, for reference:
- `enaizi`, `enaizi-ads`, `enaizi-identity`, `enaizi-user`, `enaizi-mfa` —
  all being absorbed into `mfa-core` per above. Don't add new functionality
  to these; add it to `mfa-core` instead, even if it means duplicating a
  small amount of code temporarily during the transition.
- `mfa-core` — **active**, the consolidation target. Most current work should
  land here.
- `enaizi_wa` — retired, inactive (see above).
- `niz-wa` — **active**, the WhatsApp plugin (renamed from a separate `nemkad-wa`
  codebase, not built from scratch — see naming note below). Mirrored into this repo
  at `plugins/niz-wa/` as of 2026-08-04; the copy on staging is authoritative — pull
  fresh from staging via Novamira before assuming this local copy is current.

### `niz-wa` — actual status as of 2026-08-04
- **Cutover is complete.** `enaizi_wa` has been deactivated on staging (not deleted —
  its plugin files and DB tables, `wp_niz_wa_*`, are still present for reference/
  rollback). `niz-wa` is now the sole handler of WhatsApp traffic on staging. Don't
  reactivate `enaizi_wa` or treat it as a live code path; it's historical reference
  only from here on.
- **Naming convention differs from the original plan.** `niz-wa` was not built from
  scratch — it started as a separate, more advanced codebase (`nemkad-wa`) that was
  renamed to `niz-wa` (folder + plugin header only). Internally it still uses the
  `NWA_*` class prefix and `nwa_*` function/hook prefix (`NWA_DB`, `NWA_Router`,
  `NWA_AI`, `nwa_send_message()`, `wp_nwa_*` tables, `nwa_resolve_user_id` filter),
  **not** `niz_wa_*`/`NizWa` as originally planned. Follow the existing `NWA_*`/`nwa_*`
  convention for any further work on this plugin — don't introduce a mixed prefix.
- The REST webhook namespace is also still `nemkad-wa/v1` (e.g.
  `/wp-json/nemkad-wa/v1/webhook`) — this is what's registered with Meta and is live;
  don't rename it without a coordinated webhook-URL update in Meta's dashboard.
- **Not yet portable/multi-site.** Despite the original goal, the current build has
  masjid4all-specific content: hardcoded `staging.masjid4all.com` URLs in the
  `claim_business`/`membership_price`/`advertise` action replies
  (`includes/site-integration.php`), and Knowledge Base entries specific to
  Masjid4All. If nemkad.com portability is still wanted, that's unstarted work, not
  a completed design goal.
- **No "Sofia" branding.** The AI system prompts in `niz-wa` are generic/unbranded —
  the "Sofia" persona only existed in the old `enaizi_wa` code and was not carried
  over.
- **User resolution is wired to `mfa-core`, read-only.** `includes/site-integration.php`
  hooks `nwa_resolve_user_id` to call `niz_user_check()` (moved from `enaizi-user`
  into `mfa-core` — see Plugin Architecture above) to look up already-existing
  members. It does **not** auto-create `prospect` WordPress users anymore — that
  `niz_user_create_prospect()` call was intentionally removed so `niz-wa` is fully
  standalone; unrecognized numbers are tracked in `niz-wa`'s own `wp_nwa_contacts`
  table instead. See the feature-scope status further down for the full detail.
- **Hosting-specific gotcha, important for future changes:** this host (Hostinger,
  LiteSpeed) kills fire-and-forget non-blocking loopback HTTP requests before slow
  outbound calls (e.g. an AI API call) can finish — `wp_remote_post(..., 'blocking'
  => false)` used for background processing is unreliable here. Because of this,
  `NWA_Webhook::handle()` processes each inbound message **synchronously** (including
  the AI call) before acking Meta, instead of the async hand-off pattern the original
  `nemkad-wa` code used. Don't reintroduce a background/async hop for message
  processing on this host without solving that reliability problem first.

When working across the identity/user/MFA plugins, treat the consolidation as a
refactor, not a rewrite: preserve existing hooks, option names, and DB schema where
practical, and flag any breaking change to option/table names before making it.

## Identity model — prospect vs member (agreed 2026-08-19)
Everyone starts a **prospect**: imported contacts, and accounts auto-created the
first time an unknown number messages Sofia. A prospect is a contact record, not
a conversion. They become a **member** only by completing a registration via one
of three routes — Sofia (WhatsApp), Google, or the web form — all of which funnel
through `niz_user_complete_registration()` in `mfa-core/includes/identity-registration.php`.
That function is the single chokepoint: it sets `user_status`, promotes the
`jet_cct_member` row, **resets `user_registered` to the activation moment (in GMT
— `wp_insert_user` writes GMT and the site is UTC+8)**, records
`mfa_registration_route`, and fires `mfa_user_activated`. Its "already a member"
guard makes all of that idempotent.

- **The member list's status filter carries meaning, not just a whitelist.**
  `[mfa_admin_member_list]` takes **`statuses`** (which filters the page
  OFFERS) and **`default_status`** (which one it OPENS on). Conflating them
  broke the Members page four ways in one afternoon, so treat any change here
  as behavioural rather than cosmetic.

  **Adding a status to `statuses` changes more than the dropdown.** Three
  flags used to key on "does the allowed set include Prospect", so the
  Members page silently gained the directory import panel, switched to the
  prospect column layout and built its country dropdown from 109K rows. They
  now key on a page-level **`$prospect_view`** — is this page ABOUT prospects
  — not on what it allows.

  **On a members-oriented page the filter values mean:** `Prospect` =
  `mfa_admin_member_reached_out_sql()` (prospects who messaged Sofia, ~2), not
  all 109,405 — a bare `status='Prospect'` made choosing the filter *widen*
  the view by four orders of magnitude. The empty option = members + those
  active prospects, labelled **"Members + active prospects"**; it says "All
  statuses" only on a prospect page, where that is true. The count noun
  follows the active filter.

  **Do not blank imported users' `status` to tidy this up** (considered and
  rejected 2026-08-20). Of 109,405 prospects, 34,582 carry
  `lead_source = directory:%` and **74,811 carry nothing** — blanking is
  irreversible for those, manufactures the missing-value ambiguity this file
  warns about above, and removes the denominator the fb-ads campaign measures
  conversion against. "Has this person contacted us?" is derived and already
  computed; storing it as a status duplicates a fact that can drift.
- **Do not measure growth from `user_registered` on old rows.** The imports wrote
  synthetic dates in bulk — every month May–Oct 2026 spans the same full ID range,
  user ID 2 carries an October date, two months are in the future. `/admin/reports/`
  still charts this and is internal-only by decision. The trustworthy view is the
  `/admin/` signups panel, which counts `user_status` + the reset date.
- **74,812 users have no `user_status` meta at all.** Any status gate must test for
  an explicit `prospect`, never a missing value, or it locks them out.
- **Three completion flags** decide what a member still owes:
  `niz_email_verified`, `niz_whatsapp_verified`, `mfa_password_set` — read together
  by `mfa_user_completion()`. Follow-ups key off *what is missing*, not which route
  they came through, so one set of nudges serves all three.
- **Two placeholder email domains exist**, `<phone>@mfa.com` and the older
  `<phone>@noemail.com`. Use `mfa_is_placeholder_email()`; never hand-roll the check.
  18 of 29 members carry one, so **most members are unreachable by email** — sending
  to them hard-bounces on a domain with no sending history. It also means they
  **cannot exist in FluentCRM at all**, which is keyed on email: on production
  the split is 7 members in the CRM, 4 with a real address but no record, and
  18 who can never have one until an address is captured. Those are three
  different situations and UI must not collapse them — "no tags" reads as "no
  automation is reaching them", which is only actionable for the middle group.
  Capturing real addresses is the gate on every email sequence.
- **Several `jet_cct_member` columns look purpose-built and are never written.**
  `last_contact` is empty for all 29 members, `chk_share` and `business_owner`
  for every one, and `partner_id` too. Anything presented to staff must be
  DERIVED from the table that records the event —
  `mfa-core/includes/member-snapshot.php` does this for last contact (newest of
  `wp_nwa_messages`, admin-send activity rows and inquiries), the milestone
  checklist and the affiliate downline. A field left blank because nobody
  populates it is worse than no field: it reads as "this never happened".
  When joining those sources, note **`wp_nwa_*` is GMT while the activity log
  and `jet_cct_contact_us` are site-local** — compared raw, a WhatsApp message
  lands eight hours out and wrongly wins "most recent".
- **Test accounts are excluded from campaigns via the explicit `mfa_test_account`
  meta** (`mfa_user_is_test_account()`), never by matching a login or email pattern —
  a pattern would eventually catch a real member and drop them from every follow-up
  silently. They stay real members for UAT; never delete them.
- **WhatsApp verification is already built** — `niz_wa_generate_verify_link()` plus
  the `VERIFY-XXXX` handler at router priority 10, with the button on the member
  dashboard at `#mfa-dash-whatsapp`. It must route through a `wa.me` link because
  Sofia cannot message anyone outside the 24-hour window; a code cannot be sent out.
- **The change-password flow no longer requires the current password** (user's
  explicit decision, 2026-08-19) so Google and Sofia members — who never had one —
  can set one. Compensating control is a notification email on every change; keep it.

## Security — Non-Negotiable
- **No hardcoded credentials, API keys, or secrets in plugin code.** This was already
  cleaned up across all four `enaizi-*` plugins — do not reintroduce this pattern.
- All secrets go through `wp-config.php` constants or an env-based config layer —
  never committed to the plugin repo.
- When touching MFA or identity/auth code, be conservative: flag any change that
  affects session handling, token generation, or auth flow explicitly, and explain
  the security implication before applying it.
- Sanitize/escape all user input and output per WordPress standards
  (`sanitize_text_field`, `esc_html`, `esc_attr`, `wpdb::prepare`, etc.) — no raw
  `$_POST`/`$_GET` usage, no unescaped output.

## Git Workflow
This project is tracked in git, pushed to `github.com/nizam7579/masjid4all`. Local
working copy: `C:\projects\masjid4all`.
- **Commit after each logical change**, not one giant commit at the end — e.g. one
  commit for the `niz-wa` webhook handler, a separate one for template management,
  not all of `niz-wa` in a single commit.
- Write clear, specific commit messages (what changed and why), not generic ones
  like "update plugin."
- **Never force-push** (`git push --force` / `-f`). If a push is rejected, pull and
  resolve rather than overwriting history.
- **Never commit secrets** — `.gitignore` already excludes `wp-config.php` and
  similar files; don't add credentials, API keys, or tokens to any tracked file.
- Before committing changes to auth, session, or MFA-related code, flag it
  explicitly and explain what changed and why — don't just commit silently.
- Don't rewrite git history (`rebase`, `reset --hard`, amending pushed commits)
  without asking first.
- After committing, push to `origin main` so changes are backed up — don't leave
  work sitting only in local commits.
- Code changes to `niz-wa` (and any other plugin) are typically made **directly on
  staging** via Novamira MCP, not in this local working copy first — the local copy
  under `plugins/` is a periodic mirror pulled down for version control, not the
  live editing surface. When staging code changes, re-sync the affected plugin
  folder here and commit, rather than assuming local and staging are already in sync.

## Development Workflow
- Work happens on **staging.masjid4all.com** via Novamira MCP.
- Before editing a plugin, read the existing file(s) first — don't assume structure.
- Prefer small, reviewable diffs over large rewrites, especially in auth-adjacent code.
- After changes: summarize what changed and why, and call out anything that needs
  a manual test on staging (login flow, MFA, WhatsApp message send/receive, etc.)
  before it's considered done.
- Don't activate/deactivate plugins or run destructive DB operations without
  confirming first.

### Deploying files (learned the hard way, 2026-08-19)
- `create-upload-link` **refuses PHP outside the sandbox**. Don't fight it, and
  don't base64 whole files through the conversation — it burns context fast.
  Upload **one bundle as `.txt`** and split it server-side with `execute-php`,
  using `===MFAFILE:<path>:<bytes>===` / `===MFAEND===` delimiters. **Verify
  every payload's byte count against its header before writing anything, and
  abort the whole batch on a mismatch** — this has caught truncated transfers,
  and it makes a batch all-or-nothing instead of half-applied.
- **Always diff before overwriting.** The local repo is a periodic mirror, not
  the source of truth, and is regularly behind. Compare production's
  `md5(str_replace("\r",'',$c))` against `git show HEAD~N:<path> | tr -d '\r'`.
  Strip CR on both sides — several server files are CRLF while git holds LF.
  Use `wc -c`, never `$(...)`, which eats the trailing newline.
- **Never push these whole to production** — apply anchored, single-line edits
  against the server's own bytes instead:
  - `mfa-core/mfa-core.php` — production's include list has **no** `knowledge-ai`
    entries (staging-only).
  - `mfa-core/includes/widgets/admin-shell.php` — production still reads
    **"Knowledge Base"**, local says "Knowledge Hub".
  - **all `enaizi-identity` files** — CRLF on the server and genuinely drifted
    from the mirror.
  Dry-run every anchor for uniqueness first, abort if a count isn't exactly 1,
  then syntax-check server-side (`php -l` via `proc_open` works on this host).
- A **Cloudflare 520 tells you nothing about whether the write landed** — check
  state before retrying, never retry blind.
- Bash eats `$var` inside double-quoted `node -e` scripts (`$atts`, `$phone`
  silently vanish, once causing a PHP fatal). Use single-quoted node scripts or
  a heredoc file. `/tmp/x` in Bash is `C:\tmp\x` to node — pass through
  `cygpath -w`.


## Design / Frontend Work
- Theme: **Kadence Pro currently**, but this is now a full phase-out, not
  just moving off block-based page building. Long-term goal (stated
  2026-08-10, already executed on the user's other website): replace
  Kadence entirely with our own lightweight custom theme. Near-term, the
  standing rule below (every page = one shortcode, zero Kadence blocks)
  is the concrete step already underway; don't assume the Kadence theme
  framework itself is permanent infrastructure to design around.
- **Standing rule (agreed 2026-08-07): every WordPress page renders through
  exactly one shortcode, with zero Kadence blocks.** The shortcode's PHP owns
  the full HTML output, and its own CSS/JS files own all styling/behavior —
  no inline Kadence block styles, no relying on Kadence Theme Builder markup
  for anything new. This isn't just a style preference: the Kadence-block
  removal work done so far has measurably sped up the site, confirmed via
  this session's page-by-page rebuilds — keep going, don't backslide into
  adding new Kadence blocks for convenience.
- Preferred pattern for section work: structured **per-section HTML +
  separate CSS/JS files**, not inline styles or monolithic stylesheets.
- Watch for render-blocking CSS — consolidate stylesheets where possible rather
  than adding new ones piecemeal.
- Match the existing modernized header/footer/hamburger menu/members-page style
  when building new components — check those first for conventions (spacing,
  breakpoints, class naming) before introducing new patterns.
- Mobile-first: a large share of traffic (WhatsApp shares, prayer time lookups)
  is mobile. Test responsive behavior by default.
- **Global CSS design system — DONE 2026-08-19, and now the standing rule.**
  The recurring bug pattern it was built to fix: broad, catch-all selectors (e.g. a rule
  like `.card a` meant for one kind of link) accidentally styling other
  elements that happen to be descendants, requiring several rounds of
  back-and-forth to track down (two real examples from this project: an
  invisible CTA button and a too-small button font, both caused by a plain
  `.mfa-impact-card a` rule outranking the button's own intended styling).
  The fix landed as `global-v3.css`: CSS custom properties for the brand
  palette, spacing scale and type scale, referenced from every page-specific
  file instead of repeating raw hex/px values — and selectors scoped to the
  specific element/class being styled rather than broad "any link/any child
  inside this container" rules. Every public page type now runs on it:
  prayer-times, qibla-finder, the four directory pages, About, Contact,
  Privacy, Terms, Quran (page and single), and the `/places/` hubs.

  **`/places/` is the cautionary tale for finding stragglers.** It was missed
  in the first sweep because it never used the legacy class names — the hubs
  live entirely in their own `.mfa-place-*` namespace, so grepping for the old
  classes found nothing. Check by *page type*, not by grepping for what the
  converted pages happened to be called.

  The trigger was that every newly built page ended up fighting its own
  formatting. Both halves of the plan are in place:

  1. **`global-v3.css` is the one global stylesheet**, loaded on every page.
     It owns the tokens *and* the canonical components — a page sheet carries
     only what is genuinely unique to that page. Reach for one of these before
     writing anything new; if none fits, add a variant to the global sheet
     rather than a private rule in a page stylesheet:
       - Layout: `.mfa-shell`, `.mfa-hero` (+ `--brand`, `--bleed`,
         `.mfa-hero-inner`, `.mfa-hero-split`, `.mfa-hero-title`,
         `.mfa-hero-tagline`), `.mfa-row` + `.mfa-row-main`/`.mfa-row-side`,
         `.mfa-inner` (+ `--flush`)
       - Utilities: `.mfa-stack` (vertical rhythm — the shell and hero carry no
         margin of their own, the gaps live here), `.mfa-measure` (720px
         *reading* width), `.mfa-section-label`
       - Buttons: `.mfa-btn` + `--primary` (pill), `--secondary` (light
         backgrounds), `--on-dark`, `--block`, `--ghost`, `--solid-dark`
       - Cards: `.mfa-card` + `--tinted`, `--flush`, and `--tool`/`--tool-sm`
         (a tool widget is sized to the widget, not to a line of text)
       - Bands: `.mfa-band` + `--tinted`, `.mfa-band-title`, `.mfa-band-text`.
         The CTA boxes are bands, not their own component.
       - `.mfa-faq-list` — one definition, used by every page with an FAQ.
     The version is bumped in **one** place: `includes/widgets-enqueue.php`
     (`$get_version()` and the `mfa-core-global` registration, with a
     LiteSpeed exclusion for the same file further down that file).
  2. **`/ui/` is live** — administrator-only, noindex, `[mfa_ui_library]`
     (production page 317892). It shows the chosen set marked **chosen**, with
     the rejected candidates kept beside them so the decision can be re-read.
     Candidates live in `ui-library-v1.css`, never the global sheet, so a
     reject can never become something a page depends on. Its audit section
     counts the sprawl live from the CSS folder, so the numbers cannot rot.

  **Asset loading — the standing architecture (2026-08-19).** One global
  stylesheet everywhere, one module stylesheet per area, nothing else:
  - `global-v3.css` + `site-chrome-v1.css` (and the matching
    `site-chrome-v1.js`) on every public page. The chrome is header, mobile
    menu, floating share and Sofia buttons, mobile footer nav — a *module*,
    not global, because `/member/` and `/admin/` deliberately skip it and use
    their own shells. Do not fold it into the global sheet.
  - Everything else loads only for its own area, keyed in
    `widgets-enqueue.php`.
  Nine files were retired getting here, and a page went from 8–9 stylesheets
  to 2–3. Adding a new sitewide stylesheet or script should be treated as a
  design smell: it almost always belongs in global (if truly shared) or in a
  module (if not).

  Two traps worth knowing before touching this:
  - **Bottom clearance for the fixed button stack** lives in
    `site-chrome-v1.css`, keyed on `body:has(.mfa-footer-nav)` so it applies
    only where that UI exists. Never set it again from a theme or page — the
    theme's `footer-nav.css` once did, and being loaded later it silently won,
    leaving mobile content under the buttons on every page.
  - **The `margin` shorthand defeats `.mfa-stack`.** A page sheet loading after
    the global one that sets `margin: 0 auto` resets the `margin-top` the stack
    applies, and the gap collapses to nothing. Use `margin-left`/`margin-right`.

  **Cache-busting note, because it shaped the above.** A CSS fix was deployed
  twice in one day and reached nobody: LiteSpeed's "Remove Query Strings"
  (`litespeed.conf.optm-qs_rm`) was stripping WordPress's `?ver=`, and CSS is
  served with `max-age=3155760` (~36 days), so an edited stylesheet at a
  stable URL stays invisible to returning visitors for over a month. Verified
  in a real browser — the page held 5,764 bytes while the server had 7,464.
  **That setting was turned off on both environments on 2026-08-19**, so
  `filemtime` versioning works again. This is why every stylesheet here
  carries a hand-applied `-vN` suffix; don't reintroduce that habit during
  the design-system pass, and when checking whether a CSS change is live,
  look at what the browser actually loaded rather than fetching the file with
  a cache-busting query string, which always succeeds.

## WhatsApp Business Plugin — `niz-wa` (Meta Cloud API)
- **Currently masjid4all-specific, not portable** — see the "Not yet portable" note
  above. Portability to nemkad.com/other sites is an unstarted goal, not a completed
  design property. Don't assume config is deployment-agnostic without checking.
- **Has already replaced `enaizi_wa`** — cutover is done, `enaizi_wa` is inactive.
  Only touch `enaizi_wa` to reference historical behavior or migrate remaining data
  (e.g. its FAQ table, old conversation history) — never reactivate it.
- Meta API credentials (`NWA_PHONE_NUMBER_ID`, `NWA_ACCESS_TOKEN`, `NWA_APP_SECRET`,
  `NWA_VERIFY_TOKEN`) are `wp-config.php` constants — read/set via
  `includes/class-nwa-config.php` (`NWA_Config::get()`), falling back to the
  `nwa_settings` DB option if a constant isn't defined.
- **AI provider/model is the one thing already made UI-configurable**, specifically
  so it doesn't require `wp-config.php` edits: **wp-admin → Niz WA → Settings** has
  an editable form for AI Provider (Anthropic / DeepSeek / OpenRouter), Model, and
  API Key, backed by the `nwa_settings` option. This only takes effect if
  `NWA_AI_PROVIDER` / `NWA_AI_MODEL` / `NWA_AI_API_KEY` are **not** defined as
  constants — constants always win over the form. OpenRouter is the preferred way
  to test multiple underlying models: one key, switch models by editing the Model
  field to an OpenRouter `provider/model` string (e.g. `anthropic/claude-3.5-sonnet`).
- **All `wp_nwa_*` datetimes are GMT — do not write local time into them.**
  This is load-bearing, not a style choice: `touch_inbound()` derives
  `window_expires_at` from the inbound `created_at` via `gmdate()`, and
  `is_within_window()` compares it against `current_time('timestamp', true)`,
  so the entire 24-hour customer-service window is GMT. The site is UTC+8, so
  a local timestamp here makes that window look 32 hours long and the sender
  will attempt messages Meta rejects. Convert at display time only, with
  `get_date_from_gmt()`.
  Fixed 2026-08-19: outbound was the one path writing `current_time('mysql')`,
  which put replies 8 hours after the messages they answered in the inbox —
  and, worse, meant outbound rows never aged out of `get_recent_context()`'s
  GMT cutoff, crowding recent inbound messages out of Sofia's AI context.
- Use the existing `NWA_*` / `nwa_*` naming convention consistently — see the naming
  note above. Do not introduce `niz_wa_*`/`NizWa`.

## SEO
- RankMath + Google Site Kit are configured on masjid4all.com.
- Preferred method for featured images from Cloudflare R2: REST API sideload
  (for RankMath og:image compatibility) — not direct URL reference.
- Flag any change that affects meta titles, schema markup, or URL structure —
  these have SEO implications on a live-indexed site.

### `niz-wa` feature scope — status
Full WhatsApp management plugin, not just a messaging shim. Core capabilities and
their actual status as of the 2026-08-04 cutover:
- **Sending messages** — ✅ done, all six WhatsApp types, every one in
  `includes/class-nwa-sender.php`:
  `nwa_send_message()` (text), `nwa_send_buttons()` (max 3 reply buttons),
  `nwa_send_list()` (up to 10 rows across sections), `nwa_send_media()`
  (image/video/document/audio), `nwa_send_flow()` (native in-chat form) and
  `nwa_send_template()`. AI-generated replies pass through
  `NWA_Sender::format_for_whatsapp()`, which converts Markdown (`**bold**`,
  `### headers`) into WhatsApp's own formatting first.
  **Only `nwa_send_template()` works outside the 24-hour window** — the other
  five check `NWA_DB::is_within_window()` themselves and return
  `error => 'outside_window'` rather than letting Meta reject it.
  **Watch the argument order:** `nwa_send_template()` takes the phone number
  first (`$to, $template, $lang, $components, $user_id`), unlike every other
  sender, which takes `$user_id` first.
  Meta's limits are enforced locally by truncation (button title 20 chars,
  list row title 24, description 72, caption 1024) because Meta rejects the
  whole message rather than trimming. `nwa_send_media()` takes a public URL
  **or** a Meta media id and tells them apart itself; uploading a private
  file to `/media` for an id is not implemented.
- **Template management** — ⚠️ partial. **Sending** an approved template is
  done, and `/admin/whatsapp/` offers a template picker in place of the reply
  box once the window closes (names come from `mfa_admin_member_templates()`
  when mfa-core is present, so the inbox and the member admin screen cannot
  disagree; filterable as `nwa_message_templates`). **`mfa_welcome` and
  `mfa_followup` are approved as of 2026-08-20** and send successfully.

  **They are approved in Meta as plain "English", language code `en`, NOT
  `en_US`.** Meta matches a template by name *and* translation, so an
  `en_US` send fails with error 132001 even though the template is approved
  and correct — which is what every send path did until this was fixed. The
  language now resolves in ONE place: `send_template()` defaults
  `$lang_code` to `''` and fills it from the `nwa_template_language` filter
  (default `en`); call sites pass `''`. An explicit code still wins, so a
  Malay template can pass `ms` per-send. Neither template takes a `{{1}}`
  variable, by decision — components are sent empty.

  **Every quick-reply button label on a template must be a keyword on an
  enabled action.** A tap arrives as an ordinary inbound message whose text
  is the button's label, and `NWA_DB::get_action_by_keyword()` matches the
  **whole message, exactly** — no substring, no fuzzy. A label that is not a
  keyword falls through to AI intent classification, which on production sent
  "Find a mosque" into the *Add* Mosque flow and answered "Prayer times" by
  inventing a Masjid4All phone app that does not exist. Tell Claude the exact
  labels whenever a template is created or edited in Meta. The same rule
  applies to list **row titles**, which also come back as their title.

  What is still **not** built is creating templates or submitting them to
  Meta from our side, and there is a concrete blocker: it needs the WABA id
  plus `whatsapp_business_management` permission. The WABA id is stored
  **nowhere** — not in `wp-config.php`, not in `nwa_settings`, not in the
  webhook payloads we retain — and the access token is a SYSTEM_USER token
  scoped to sending only, so it cannot be discovered from the API either.
  Adding an `NWA_WABA_ID` constant is the unblock.
- **User/contact management** — ✅ done, via `mfa-core`'s identity functions
  (moved from `enaizi-user` — see Plugin Architecture above), hooked through
  `nwa_resolve_user_id`. **Note:** `niz-wa` is intentionally standalone now —
  the resolver only does a read-only `niz_user_check()` lookup for already-
  existing members; it no longer auto-creates `prospect` WordPress users for
  unrecognized numbers (`niz_user_create_prospect()` call removed). New
  numbers fall through to `niz-wa`'s own `wp_nwa_contacts` table instead.
- **Receiving/webhook handling** — ✅ done, with the synchronous-processing caveat
  noted above. Signature verification (`x-hub-signature-256` HMAC), dedupe, and a
  "typing…" indicator (`NWA_Sender::mark_read_with_typing()`) shown while a reply is
  generated are all implemented.
- **Opt-out (`STOP`)** — ✅ done 2026-08-20. `NWA_OptOut` hooks
  `nwa_route_message_override` at **priority 30** — after every flow, before
  the AI. Meta key `nwa_opted_out`; wrapper `nwa_is_opted_out()`. Opting out
  blocks **templates only**, enforced inside `send_template()` so it holds
  for every caller; replies inside the 24-hour window are deliberately still
  answered, because that window only exists because the person wrote in.
  **Never advertise `stop` in a flow prompt.** It is also each flow's cancel
  word, but a flow only claims it while the flow is *live* — once the TTL
  passes the message falls through to priority 30 and unsubscribes them, so a
  prompt saying "type *stop* to cancel" was instructing people into exactly
  that. Prompts advertise `cancel` instead; `stop` still cancels a live flow.
- **Inbound media** — ✅ downloaded into the WordPress media library at receipt
  (`includes/class-nwa-media.php`, 2026-08-20), with the attachment id on
  `wp_nwa_messages.media_attachment_id` and the file rendered inline in the
  inbox. **It has to happen at receipt**: an inbound media id stops resolving
  after ~30 days, so fetching lazily would lose anything nobody opened in a
  month. Because the webhook is synchronous on this host, the download is
  bounded — size checked from Meta's metadata *before* the bytes are fetched,
  capped at 8MB (`nwa_media_max_bytes`), short timeouts, and any failure is
  logged and ignored so a reply still goes out. The file extension comes from
  Meta's reported MIME through an allow-list, never a sender-supplied
  filename. A media message's `content` is now its **caption**, or a marker
  like `[image]` / `[document: invoice.pdf]` — it used to be the bare media
  id, which reached the router and the AI as an opaque string while the
  caption was discarded.
- **`wp_nwa_*` schema changes** — bump `NWA_DB_VERSION` in `niz-wa.php` and
  edit `NWA_DB::create_tables()`. The plugin compares the constant against
  the `nwa_db_version` option on load and re-runs `dbDelta()` itself, so a
  plain file update applies the migration with no activation step. Adding
  `media_attachment_id` this way left all 371 production rows untouched.
- **Action registry** — a data-driven system not in the original scope doc:
  `wp_nwa_actions` holds keyword-triggered and AI-intent-classified actions
  (`start`, `register`, `reset_password`, `claim_business`, `membership_price`,
  `advertise`), each backed by a global PHP callback registered in
  `includes/site-integration.php`. `advertise`'s reply is still placeholder
  copy/URLs — real content was intentionally left for later (KIV).
  **Seeding only ever INSERTs a missing `intent_key`** — changing an existing
  row needs an explicit idempotent `UPDATE` beside the others at the foot of
  `niz_wa_seed_actions()`, and because that function is already loaded when
  you deploy, new UPDATEs run on the NEXT request, not the one that wrote the
  file. **`get_action_by_keyword()` has no `ORDER BY`**, so with duplicate
  keywords across two actions the lower id silently wins — that is how
  `claim_business`, pointing at a callback that never existed in any plugin,
  beat `directory` and answered every "claim business" with "Sorry, something
  went wrong on our end" until 2026-08-20. After any action work, re-check
  that every enabled action's `callback_function` actually exists.
- **The `/admin/whatsapp/` inbox** — the conversation list marks anyone still
  inside the 24-hour window with a green ✓ and a left border (the tick
  carries the meaning; colour alone is not an accessible signal), read from
  the row `get_conversations()` already returns. The reply box is **always
  rendered and disabled outside the window, never hidden** — a control that
  vanishes leaves staff guessing, an inert one explains itself.

  mfa-core's action bar renders under the profile, limited to Send WhatsApp +
  Send Template, so the prepared messages work from the inbox and not only
  from Member Info. **The window rule lives in
  `mfa_admin_member_contact_state()` alone** — the inbox does not restate it.
  The inline template picker is now only a fallback for when mfa-core is
  absent, since niz-wa running standalone is a supported setup that would
  otherwise lose its only way to send a template.

  `mfa_admin_member_actions_render( $row, $user_id, $only )` gates the **modal
  markup as well as the buttons** — a hidden overlay nobody can open is still
  markup, and the edit modal costs a `DISTINCT country` query. Any new caller
  also needs the action CSS/JS enqueued for its page: `widgets-enqueue.php`
  keys them by `post_name`.
- **Admin-sent button messages** — `/admin/member/info/` → Send WhatsApp can
  send interactive messages, so a member is activated or verifies an email
  inside WhatsApp rather than being sent to `/member/`. Options: Free-form,
  Activate Account, Verify Email, Invite to Add Mosque/Business/Website,
  Invite to Founding Member waitlist.

  **Buttons come from the server-side catalogue keyed by the posted preset,
  never from the request.** A tap sends the button's *title* back as the
  message and that title is matched against `wp_nwa_actions`, so a client
  able to name its own buttons could route a member into any flow. Three
  buttons is WhatsApp's hard cap.

  **The `when` rule is re-checked in the AJAX handler, not only at render** —
  an admin page left open goes stale and would otherwise tell somebody who
  verified ten minutes ago that their email is unverified. Same reason the
  24-hour window is re-validated on send. Each rule is deliberately not the
  obvious test: `prospect` means **not yet a member** (~74,800 accounts have
  no `user_status` at all and are exactly who needs activating) and is
  **case-insensitive** (production has one `Prospect`, staging more);
  `email_unverified` **excludes a placeholder address**, which has nothing at
  the other end; `not_on_waitlist` distinguishes a **signal** (interest,
  carries `count`) from a **capture** (finished the flow), so somebody who
  once asked and never finished still gets invited.
- **Account flow starts with a NAME step.** `niz_wa_account_known_name()`
  rejects generated names (`user_353…`, the bare number) so nobody is asked
  to confirm a phone number as their name. Only `display_name` is written —
  the `jet_cct_member` row is promoted by `niz_user_complete_registration()`
  at the end, so writing it early desyncs if they abandon halfway.
- **Flow language (English / Spanish / Malay)** — 2026-08-20. Scripted flow
  copy answers in the writer's language across all five flows (directory,
  account, travel, leads, contact): 96 phrases per language, identical key
  sets. `niz_wa_detect_lang()` scores space-padded function words, stores the
  result on user meta `nwa_lang`, and `niz_wa_translate_outbound()` applies
  the table with `strtr()` on the **`nwa_outbound_text`** filter in
  `NWA_Sender` — one hook covering plain text and interactive **body** text.

  **Language is stored, never re-detected per message.** A flow is a run of
  very short replies ("Mezquita", "Si", a pasted URL) carrying no language
  signal; per-message detection flips back to English mid-flow.

  **Two categories must stay English, and both fail silently if translated:**
  (1) **button and list row titles** — they are routing keywords, which is why
  the filter never touches them, and why the place-confirm step also accepts
  `si`/`sí`/`claro`/`vale`; (2) **command words inside sentences** —
  `*register*`, `*travel*`, `*advertise*`, `*done*`, `*REGISTER*`, `*cancel*`,
  `*directory*`, `*flight*`/`*car*`/`*bus*`/`*train*`, `*OK*`, `*Send*`,
  `*Cancel*`, `*contact*`, plus `*Share*` (Google Maps' own button label).
  There is an assertion that every command token in a key survives into both
  translations — run it after editing the table. Adding a language is a word
  list plus a map entry; nothing else changes.

  **Two authoring rules, both learned from a key silently failing to match:**
  never put `{{site}}` (or any placeholder) inside a translatable sentence —
  it expands to the real site name, so keys containing "Masjid4All" matched
  on production and failed on staging, where it is
  "staging.masjid4all.com"; and a dynamic value must sit on its own line
  rather than mid-clause, since "Your email *{$addr}* is already verified"
  cannot be keyed at all. **Verify by rendering each message and looking for
  lines that come through unchanged** — counting keys would have passed while
  three sentences shipped in English.
- **Flow TTLs need a resume path, not a longer TTL.** Twice the same failure:
  a session lapses, the person answers anyway, and the AI fields it instead of
  the flow. **Recognise the ANSWER, not the session** — a Google Maps link with
  no live session goes to `niz_wa_place_add_from_link()`, and a message that is
  *just an email address* resumes the account flow at its code step
  (`niz_wa_account_wants_email()`). Both are safe without asking because the
  expected answer carries its own confirmation: the link ends in a Yes/No, the
  address ends in a 6-digit code, so nothing is committed on a guess.

  Every such catch is guarded on **no pending action of ANY kind** — these
  routes run early (account 15, directory 20) and would otherwise steal input
  from travel (22), leads (23) or contact (25). The email catch additionally
  demands the **whole message** be the address, refuses both placeholder
  domains, and only fires for accounts still lacking a real email. It is
  self-limiting: the first address sets `await_code`, so a second arrives with
  a session and reads as a wrong code.

  **Still open:** the account flow's **name step has no resume path** — a name
  or "OK" is just text and cannot be pattern-matched, so it would need a
  recently-expired-session signal instead.
- **Directory place links** — a Google Maps link is accepted **even after the
  flow's 30-minute TTL has expired**, because fetching a link out of Maps
  routinely takes longer; with no live session it goes straight to
  `niz_wa_place_add_from_link()`. That catch is guarded on **no pending action
  of any kind**, not merely "no directory flow": this route runs at priority
  20, ahead of travel (22), leads (23) and contact (25), so a looser check
  would steal a Maps link from one of those.
  `niz_wa_place_is_infrastructure()` refuses bus stops, stations and car parks
  **before** the confirm step, reading Google's place `type` and **never the
  name** — genuine halal businesses are called things like "Hamza Doner —
  Railway Station Skopje".
- **Knowledge base + AI Q&A fallback** — done. `wp_nwa_knowledge_base` grounds
  open-ended questions; populated with real Masjid4All content (see the portability
  note above on why this isn't reusable as-is on another site).
- **User profile memory** — done. `NWA_AI::maybe_update_profile()` summarizes new
  facts into `wp_nwa_user_profiles` every 8 messages, merging rather than
  overwriting, and feeds that summary back into future AI replies.

Given "create new user" is in scope, this plugin touches the same territory as
`mfa-core`'s identity functions — but per the standalone note above, `niz-wa`
no longer calls into user-creation at all for unrecognized numbers; it only
does a read-only lookup and otherwise manages its own contacts table.
- Don't push changes straight to production (masjid4all.com).
- Don't reintroduce hardcoded secrets.
- Don't rewrite auth/session code without flagging it first.
- Naming: `mfa-core` mixes `niz_user_*` (identity/auth functions, prefix
  preserved from the original `enaizi-user` code for continuity) with
  `mfa_*`/`mfa_core_*` (shortcodes, page widgets, plugin-level hooks) — match
  whichever pattern the surrounding file already uses, don't introduce a
  third scheme. `niz-wa` keeps its own separate `NWA_*`/`nwa_*` prefix.
- `enaizi_wa` is already deactivated; don't reactivate it or resume dual-running it
  as part of routine `niz-wa` work.
