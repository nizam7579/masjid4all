# Masjid4All — Claude Code Project Instructions

## Environment
- **Site**: staging.masjid4all.com (WordPress, staging environment)
- **Connection**: Novamira MCP plugin + `@automattic/mcp-wordpress-remote`
- **Purpose of this connection**: plugin development, WordPress design/theme work
- **Production**: masjid4all.com — never push directly to production from here. All changes go through staging first and get reviewed before promotion.

## Site Context
Masjid4All is an Islamic digital ecosystem: mosque directory, prayer times, Qibla
direction, halal business listings, Islamic knowledge resources, and an AI WhatsApp
assistant ("Sofia"). Content and UX should respect Islamic terminology conventions
and JAKIM-aligned halal compliance where relevant. Default language is English;
Bahasa Melayu content may appear alongside it — don't auto-translate without asking.

## Plugin Architecture
Current custom plugins (in consolidation):
- `enaizi-identity`
- `enaizi-mfa`
- `enaizi-user`
- `enaizi_wa` — **being replaced**, see below

**Target state**: consolidate into two plugins:
1. **Identity/User/MFA plugin** — merges `enaizi-identity`, `enaizi-user`, `enaizi-mfa`
2. **`niz-wa`** — new standalone WhatsApp plugin, built on Meta Cloud API, designed to
   be **portable/multi-site installable** (not masjid4all-specific — also targets
   nemkad.com). This **replaces** the currently active `enaizi_wa` plugin.

### `niz-wa` replacement notes
- `niz-wa` is a clean rebuild, not a renamed copy of `enaizi_wa` — new plugin slug,
  new namespace/prefix (`niz_wa_*` / `NizWa`, not `enaizi_wa_*`), fresh option keys.
- `enaizi_wa` stays **active in production** until `niz-wa` is verified working on
  staging (webhook receive/send, message templates, Sofia integration if applicable).
  Don't deactivate `enaizi_wa` as a side effect of `niz-wa` work.
- Cutover plan: build and test `niz-wa` fully on staging → confirm feature parity
  with `enaizi_wa` (message send/receive, any WA-triggered automations) → only then
  plan deactivation of `enaizi_wa` and activation of `niz-wa` on production. Flag
  this cutover explicitly when it's ready — don't do it as part of routine dev work.
- If `enaizi_wa` stores data (message logs, opted-in numbers, templates) that
  `niz-wa` needs to inherit, call out a migration step rather than assuming a
  fresh start is fine.
- Until cutover, treat `enaizi_wa` as read-reference only — look at it to understand
  current behavior, but new work goes into `niz-wa`.

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
- Being built as a **portable WordPress plugin** — no masjid4all-specific hardcoding.
  Initial scope/testing ground is nemkad.com, but assume it will be installed on
  other sites (including masjid4all.com for the Sofia assistant) later.
- **Replaces `enaizi_wa`** — see cutover notes above. Don't touch `enaizi_wa` unless
  explicitly asked to reference its current behavior or migrate its data.
- Keep Meta API credentials, webhook verification tokens, and phone number IDs
  configurable per-install, not hardcoded.
- Design with multi-site reuse in mind: settings page, not constants, for
  anything that varies by deployment.
- Use the `niz_wa_*` / `NizWa` naming convention consistently across functions,
  classes, hooks, and option keys — don't mix in the old `enaizi_wa` prefix.

## SEO
- RankMath + Google Site Kit are configured on masjid4all.com.
- Preferred method for featured images from Cloudflare R2: REST API sideload
  (for RankMath og:image compatibility) — not direct URL reference.
- Flag any change that affects meta titles, schema markup, or URL structure —
  these have SEO implications on a live-indexed site.

### `niz-wa` feature scope
Full WhatsApp management plugin, not just a messaging shim. Core capabilities:
- **Sending messages** — outbound text/media messages via Meta Cloud API
- **Template management** — create/manage WhatsApp message templates (and submit
  for Meta approval where the API requires it), send template-based messages
- **User/contact management** — create new users/contacts from WhatsApp numbers
  (e.g. new inbound number → new user record), not just message logging
- **Receiving/webhook handling** — inbound message + status webhook processing
  (delivery, read receipts, opt-in/opt-out events)

Treat this as the feature-parity checklist against `enaizi_wa` for the eventual
cutover — confirm each of these works on staging before `enaizi_wa` is retired.
If `enaizi_wa` does more than this (e.g. Sofia-specific hooks, automations), flag
that explicitly rather than assuming this list is exhaustive — check `enaizi_wa`'s
current code for anything not captured here before calling parity "done."

Given "create new user" is in scope, this plugin touches the same territory as the
identity/user plugin above — coordinate on user-creation logic (don't duplicate
user-creation code paths between `niz-wa` and the identity/user/MFA plugin; decide
which one owns "create WP user" and have the other call into it).
- Don't push changes straight to production (masjid4all.com).
- Don't reintroduce hardcoded secrets.
- Don't rewrite auth/session code without flagging it first.
- Don't invent new plugin naming conventions — follow the `enaizi-*` prefix for
  anything in the identity/user/MFA consolidation, and `niz-wa`/`niz_wa_*` for the
  new WhatsApp plugin.
- Don't deactivate `enaizi_wa` or touch its data as a side effect of `niz-wa` work —
  cutover is a separate, explicit step.
