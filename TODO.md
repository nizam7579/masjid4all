# Masjid4All — TODO

Open, actionable work. Decisions already made and things already shipped live in
`CLAUDE.md` and the session memory, not here. An item leaves this list when it is
done **or** when it is explicitly decided against — in the second case say so in
`CLAUDE.md` rather than deleting it silently, so it does not get re-proposed.

Started 2026-08-21.

---

## SEO / content

### 1. City hub intros — the other 484 `/places/` pages
Countries and states (82) are done and live. City hubs were deliberately
excluded: a model asked about the Muslim community of a small district invents
founding dates, population figures and mosque histories, whereas state-level
knowledge of Kelantan or Aceh is well attested.

**This is not a matter of removing the depth guard.** Doing it properly means a
different grounding strategy — build the copy from what we actually hold for
that city (real mosque and business names, categories, counts, the parent
state) rather than from the model's recollection of the place. The existing
`mfa_place_content_forbidden_claim()` guard carries over as-is.

- Code: `plugins/mfa-core/includes/place-content.php`, runner at
  `/admin/crawler/places/`
- Cost at the current shape: ~4s and well under a cent per hub, so ~484 hubs is
  trivial money and about 35 minutes of tab-watching.
- Decide first: is a city-level intro worth having at all, or is a data-driven
  template (no model) the better answer at that granularity?

### 2. The 302,333 noindexed listing pages
The real thin-page problem, and untouched by anything done so far.
**305,612 published listing pages, 302,333 hidden (98.9%), 3,279 indexable.**

| Type | Published | Hidden | Indexable |
|---|---|---|---|
| masjid | 158,903 | 156,984 | 1,919 |
| business | 116,022 | 115,777 | 245 |
| web | 30,687 | 29,572 | 1,115 |

They become indexable one at a time as the generator fills them past 500 visible
characters (`mfa_seo_min_content_chars()`). At the current rate this is the
single largest lever on the site's search presence — worth a plan of its own
rather than incremental grinding.

### 3. Schema markup — Task 8, audited and NOT fixed
Every mosque and business page currently claims the **site's** KL address via
RankMath's local-seo Place. Three purpose-built templates exist and have never
worked. Ratings are scraped Google data — do not emit `aggregateRating` without
a human decision on whether that is legitimate.

### 4. `homepage-stats.php` GMT year floor — **decided against 2026-08-21**
Hardcodes `user_registered >= '2026-01-01 00:00:00'` (GMT) on a public counter.
Same class as the admin day-boundary bug, but eight hours once a year on a
running total in the tens of thousands. Left deliberately. Listed so it is not
"found" again.

---

## Testing

### 5. Google sign-in — REGISTRATION proven, LINK branch still untested
**The registration half is done.** User **123923** (`admin@pewarisan.com`,
login `admin`) carries `mfa_registration_route = google` and
`niz_google_connected = Yes`, registered 2026-08-21 07:54 GMT. Production
counters moved with it: `route='google'` 0 → 1, members 29 → 30,
`niz_google_connected='Yes'` 11 → 12.

What is still unproven is the **other branch**. `Niz_Google_Provider` resolves
by `get_user_by( 'email', … )`:

- **no user with that address** → `mfa_register()`, route `google`, new member
- **user exists** → links only: sets `niz_google_connected` / `niz_google_id`
  and signs them in. Per `CLAUDE.md` this is **not** activation — such a member
  keeps an empty `mfa_registration_route` and no `mfa_original_registered`.

`nemkad7579@gmail.com` (user **14553**, Member since 8 Aug, CCT row 228) is
already in exactly the right state to test that: `niz_google_connected = "No"`,
no WhatsApp history, nothing authored. Signing in with it needs **no reset and
no deletion** — the account existing is precisely what makes it a link test.

**⚠ PRODUCTION IS CURRENTLY IN A TEMPORARY STATE (2026-08-21).** User 14553's
address was moved to `nemkad7579+kept@gmail.com` — on `wp_users` **and** on CCT
row 228, so the two do not disagree — so that a Google sign-in with the real
`nemkad7579@gmail.com` takes the registration branch instead of the link one.
`get_user_by( 'email', 'nemkad7579@gmail.com' )` now returns nothing; verified.

**This must be undone after the test.** The original values are stored in the
production option **`mfa_nemkad_email_swap`**. Restore = delete the account the
test created, then put both emails back from that option.

Expected on a successful test: a new user above ID 142919 with login
`nemkad7579_xxxxx` (the plain login is taken, so `mfa_person_upsert()` appends a
random suffix), `mfa_registration_route = google`, `niz_google_connected = Yes`,
`niz_email_verified = Yes`. Counters move members 30 → 31 and route `google`
1 → 2.

Deleting user 14553 outright was the alternative and was rejected as
irreversible — `mfa_cleanup_records_for_deleted_user` drops the CCT row and
barakah rows, and FluentCRM cleans up its contact.

Side note worth tidying: user 123923's login is literally `admin`, derived from
the email local part by `mfa_register()`. Harmless but confusing in support
screens.

### 6. Karen (user 14461) — was the STOP accidental?
Her opt-out was cleared 2026-08-21 on your instruction and a reply was sent. She
is the only person ever un-opted-out; zero users are opted out sitewide. Reverse
with `NWA_OptOut::opt_out( 14461 )` if she confirms it was intentional.

---

## Code hygiene / drift

### 7. Repo-vs-production splits, three files
Known to differ and not yet reconciled. Diff before touching any of them:
- `plugins/mfa-core/includes/admin-template.php`
- `plugins/mfa-core/assets/css/admin-website-list-v2.css`
- `plugins/mfa-core/includes/widgets/place-links.php`

### 8. `enaizi-mfa` three-way divergence — parked on purpose
Repo, staging and production all differ. Deliberately left alone 2026-08-21;
recorded so nobody syncs it blindly. Reconcile only with a reason to.

### 9. Knowledge placeholder image
Currently borrowing `/business/business-owner.webp`. Needs its own image
uploaded to R2, then a one-line change in `knowledge.php`.

---

## Blocked / needs a decision

### 10. WhatsApp template creation — blocked on the WABA id
Sending approved templates works. **Creating** them from our side needs the WABA
id plus `whatsapp_business_management` permission. The id is stored nowhere — not
`wp-config.php`, not `nwa_settings`, not in any retained webhook payload — and
the access token is a SYSTEM_USER token scoped to sending, so it cannot be
discovered from the API. Adding an `NWA_WABA_ID` constant unblocks it.

### 11. FluentCRM follow-up funnels
CRM is activated and the read API is wired into Member Info, but the actual
follow-up sequences are not built. Gated on a real constraint, not effort:
**18 of 29 members carry a placeholder email** and can never exist in FluentCRM
at all. Capturing real addresses is the prerequisite for every email sequence.

### 12. `advertise` action reply — still placeholder copy
`niz-wa`'s `advertise` action returns placeholder text and URLs. Left as KIV on
purpose; needs real content.

### 13. `niz-pwa` — planned, not started
Notifications and PWA-specific features to be split back out of `mfa-core`. No
urgency. `plugins/enaizi-mfa/includes/pwa.php` holds a dormant, fully
commented-out earlier attempt worth reading first — not necessarily reviving.

### 14. Account flow — the name step has no resume path
A lapsed session can be resumed from a Google Maps link or a bare email address,
because each answer carries its own confirmation. A **name** is just text and
cannot be pattern-matched, so resuming it needs a recently-expired-session
signal instead. Known gap, no fix designed.
