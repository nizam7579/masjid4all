# Masjid4All — Claude Code Project Instructions

## Environment
- **Site**: staging.masjid4all.com (WordPress, staging environment)
- **Connection**: Novamira MCP plugin + `@automattic/mcp-wordpress-remote`
- **Purpose of this connection**: plugin development, WordPress design/theme work
- **Production**: masjid4all.com — never push directly to production from here. All changes go through staging first and get reviewed before promotion.

## Site Context
Masjid4All is an Islamic digital ecosystem: mosque directory, prayer times, Qibla
direction, halal business listings, Islamic knowledge resources, and an AI WhatsApp
assistant. (The old `enaizi_wa` assistant was branded "Sofia" — that persona wasn't
carried into the current `niz-wa` plugin; its AI prompts are generic/unbranded.)
Content and UX should respect Islamic terminology conventions and JAKIM-aligned
halal compliance where relevant. Default language is English; Bahasa Melayu content
may appear alongside it — don't auto-translate without asking.

## Plugin Architecture
Current custom plugins:
- `enaizi-identity`
- `enaizi-mfa`
- `enaizi-user`
- `enaizi_wa` — **retired, inactive** (see cutover status below)
- `niz-wa` — **active**, the WhatsApp plugin (renamed from a separate `nemkad-wa`
  codebase, not built from scratch — see naming note below). Mirrored into this repo
  at `plugins/niz-wa/` as of 2026-08-04; the copy on staging is authoritative — pull
  fresh from staging via Novamira before assuming this local copy is current.

**Target state (still open)**: consolidate `enaizi-identity`, `enaizi-user`,
`enaizi-mfa` into a single Identity/User/MFA plugin. Not started yet — treat as a
separate future effort, unrelated to the `niz-wa` work below.

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
- **User resolution is wired to `enaizi-user`.** `includes/site-integration.php`
  hooks `nwa_resolve_user_id` to call `niz_user_check()` / `niz_user_create_prospect()`
  from `enaizi-user`, so unknown WhatsApp numbers become `prospect` users (not full
  members) via the existing identity system — this is the "don't duplicate
  user-creation logic" coordination the original plan called for; `enaizi-user` owns
  user creation, `niz-wa` calls into it.
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

## Design / Frontend Work
- Theme: **Kadence Pro**
- Preferred pattern for homepage/section work: structured **per-section HTML +
  separate CSS/JS files**, not inline styles or monolithic stylesheets.
- Watch for render-blocking CSS — consolidate stylesheets where possible rather
  than adding new ones piecemeal.
- Match the existing modernized header/footer/hamburger menu/members-page style
  when building new components — check those first for conventions (spacing,
  breakpoints, class naming) before introducing new patterns.
- Mobile-first: a large share of traffic (WhatsApp shares, prayer time lookups)
  is mobile. Test responsive behavior by default.

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
- **Sending messages** — ✅ done. Text via `nwa_send_message()`, templates via
  `nwa_send_template()`, both in `includes/class-nwa-sender.php`. AI-generated
  replies are passed through `NWA_Sender::format_for_whatsapp()` to convert
  Markdown (`**bold**`, `### headers`) into WhatsApp's own formatting before sending.
- **Template management** — ❌ not built. Sending a template message works, but
  there's no UI/API flow to create templates or submit them to Meta for approval.
  Still a gap against the original scope if that's needed.
- **User/contact management** — ✅ done, via the `enaizi-user` integration described
  above (`nwa_resolve_user_id` → `niz_user_check()`/`niz_user_create_prospect()`).
  New WhatsApp numbers become `prospect` users in the existing identity system.
- **Receiving/webhook handling** — ✅ done, with the synchronous-processing caveat
  noted above. Signature verification (`x-hub-signature-256` HMAC), dedupe, and a
  "typing…" indicator (`NWA_Sender::mark_read_with_typing()`) shown while a reply is
  generated are all implemented. No opt-out/unsubscribe flow has been built.
- **Action registry** — a data-driven system not in the original scope doc:
  `wp_nwa_actions` holds keyword-triggered and AI-intent-classified actions
  (`start`, `register`, `reset_password`, `claim_business`, `membership_price`,
  `advertise`), each backed by a global PHP callback registered in
  `includes/site-integration.php`. `advertise`'s reply is still placeholder
  copy/URLs — real content was intentionally left for later (KIV).
- **Knowledge base + AI Q&A fallback** — done. `wp_nwa_knowledge_base` grounds
  open-ended questions; populated with real Masjid4All content (see the portability
  note above on why this isn't reusable as-is on another site).
- **User profile memory** — done. `NWA_AI::maybe_update_profile()` summarizes new
  facts into `wp_nwa_user_profiles` every 8 messages, merging rather than
  overwriting, and feeds that summary back into future AI replies.

Given "create new user" is in scope, this plugin touches the same territory as the
identity/user plugin above — user creation is *not* duplicated: `enaizi-user` owns
it, `niz-wa` calls into it via the `nwa_resolve_user_id` filter.
- Don't push changes straight to production (masjid4all.com).
- Don't reintroduce hardcoded secrets.
- Don't rewrite auth/session code without flagging it first.
- Follow the existing `enaizi-*` prefix for anything in the identity/user/MFA
  consolidation, and the existing `NWA_*`/`nwa_*` prefix for `niz-wa` — don't
  introduce a third naming scheme.
- `enaizi_wa` is already deactivated; don't reactivate it or resume dual-running it
  as part of routine `niz-wa` work.
