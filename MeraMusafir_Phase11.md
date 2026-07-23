# Phase 11 — Admin Panel

**Goal (master plan):** the operator can run the platform from a dedicated console — see the numbers, work the moderation and verification queues, curate destinations, and reach everyone at once.

**What shipped:** a role-gated admin console at `/admin` with six surfaces — Dashboard, Users, Agencies, Reports, Destinations, Broadcast — in its own dark-purple operator layout, distinct from the traveller app.

---

## Access

- **API:** every admin route sits behind `auth:sanctum` **and** a new `admin` middleware (`EnsureAdmin`) that checks the spatie `admin` role in one place, so no admin controller repeats the check. A non-admin gets `403 Admin access required.`
- **UI:** `is_admin` now rides on the user resource (from login), the router guard bounces non-admins off any `/admin` route, and the "Admin console" sidebar link only renders for admins. Three layers, so a missing one isn't the only thing standing between a normal user and the console.

---

## The six surfaces

**Dashboard.** Travellers (with new-today), agencies, active trips, and **commission this month** with gross processed underneath — revenue is only settled payments, and only the platform's cut, not the gross agencies keep. Two work-queue cards (open reports, agencies awaiting verification) that deep-link to their pages, plus a six-month revenue bar chart built without a charting dependency.

**Users.** Search by name/email, filter by type and by active/suspended, and **suspend / reinstate**. Suspension reuses `is_blocked`, which already hides someone's trips and gates their messaging platform-wide — so a suspended account genuinely stops operating rather than needing a new mechanism. Admins can't be suspended.

**Agencies.** The verification queue. Awaiting-review by default, with the licence number, owner, contacts and package count on each card, and a one-tap **Verify / Unverify** that grants the trusted badge. Verifying drops the row from the pending tab and refreshes the nav badge.

**Reports.** The moderation queue, styled by what was flagged — a coloured reason chip, the subject kind (user / post / package / message) with its own left-edge colour, an **Open** link to the actual content, and the reporter. **Dismiss** or **Mark actioned**, each capturing an optional note. Actioning records that something was done; the suspend or takedown itself happens on the relevant page, so the two aren't wired into one irreversible button.

**Destinations.** Every place, active or not, with two toggles: **Featured** (homepage) and **Visible** (drops out of discovery without deleting — trips still reference it). Featured and hidden states are called out on the card.

**Broadcast.** An announcement to everyone, travellers, or agencies — delivered as a real notification through the existing pipeline, so it lands in the bell and lights the badge like any other alert rather than inventing a second delivery path. Live preview of the notification card, and a confirm step because it can't be unsent. Chunked send, so a broadcast to thousands doesn't hold the table in memory.

---

## Decisions worth knowing

**Revenue is the platform's, not the gross.** The headline number is commission earned this month. Gross processed sits beneath it for context, but the platform's money is the 5% cut, and that's what leads.

**Broadcasts reuse the notification system.** Rather than a separate announcements table and delivery, a broadcast is N notifications of type `announcement`. It inherits the bell, the live socket push, and read-tracking for free. The type maps to the `other` category, so it counts toward the bell total without claiming a sidebar dot it has no home for.

**Work-queue badges stay honest.** The nav badges (pending verifications, open reports) come from the dashboard endpoint, and every mutation that changes a count — verifying an agency, closing a report — re-emits the queue counts so the badge matches the page without a reload.

**Suspension is `is_blocked`, deliberately.** Not a new column. That flag already carries platform-wide consequences (hidden trips, gated DMs), so reusing it means "suspended" actually means something, immediately, everywhere.

---

## API

All under `/api/v1/admin`, all behind `admin` middleware.

| method | route | |
|---|---|---|
| GET | `/dashboard` | metrics + queue counts |
| GET | `/revenue` | 6 months, by gateway |
| GET | `/users` | search / type / status, paginated |
| POST | `/users/{user}/suspend` | toggle |
| GET | `/agencies` | pending / verified / all |
| POST | `/agencies/{agency:id}/verify` | toggle |
| GET | `/reports` | by status |
| POST | `/reports/{report}/resolve` | actioned / dismissed + note |
| GET | `/destinations` | search, paginated |
| POST | `/destinations/{destination:id}/feature` | toggle |
| POST | `/destinations/{destination:id}/active` | toggle |
| POST | `/broadcast` | segment + title + body + link |

Agency and Destination bind by `:id`, not their slug route-key, since the console works from ids.

---

## Not done (from the master-plan list)

- **Destination add/edit.** The console toggles feature/visibility; creating a destination or editing its copy still happens via seeder/DB. A full editor (with cover upload) is the natural next addition.
- **Featured-placement manager beyond destinations.** Homepage feature is a per-destination toggle; there's no slot-auction or per-package promotion surface.
- **Broadcast segments are coarse.** All / travellers / agencies. No "agencies on Basic" or "travellers who booked this month" targeting yet.
- **A separate admin auth.** Admins log in through the same door as everyone else and the role gates the console. A separate admin login is a hardening step, not a feature gap.
