# Mera Musafir — Project Handover Document

**Date of handover:** July 2026
**Purpose:** Bring a new collaborator up to speed on what's built, what's broken, what's untested, and where to start.

---

## 1. What This Project Is

Mera Musafir is Pakistan's first collaborative travel platform — replacing the Instagram page + WhatsApp group system that Pakistani travel agencies currently use. Three user types: Travelers, Agencies, Admins.

Full product vision and original spec: see `MeraMusafir_MasterPlan.md` in the project root. That document is the source of truth for the long-term plan. This handover doc reflects the **actual current state**, which has diverged slightly from the original plan (in good ways — see Section 5).

---

## 2. Tech Stack

**Backend:** Laravel 11, PHP 8.3, Laravel Sanctum (auth tokens), Laravel Reverb (WebSockets), Spatie Permission (roles), MySQL 8, Redis
**Frontend:** Vue 3 (Composition API, `<script setup>` only), Pinia, Quasar Framework, Axios, Laravel Echo
**Infra (local dev):** Docker Compose runs MySQL + Redis. Laragon runs PHP/Composer. Node/npm run natively on Windows.

**Repo:** `github.com/AmmarRiaz123/Mera-Musafir-v`
**Folder structure:**
```
Mera-Musafir-v/
├── backend/          ← Laravel 11 API (port 8000)
├── frontend/          ← Vue 3 + Quasar SPA (port 9000)
└── docker-compose.yml
```

---

## 3. How to Run the Project Locally

You need **4 things running simultaneously**, in this order:

**1. Docker (MySQL + Redis):**
```powershell
cd C:\path\to\Mera-Musafir-v
docker-compose up -d
```

**2. Laravel API server (Laragon terminal):**
```bash
cd backend
php artisan serve
```

**3. Laravel Reverb (WebSocket server) — separate Laragon terminal:**
```bash
cd backend
php artisan reverb:start
```

**4. Quasar frontend (PowerShell):**
```powershell
cd frontend
npx quasar dev
```

Frontend runs at `http://localhost:9000/#/` — note the `#/` hash routing, required by Quasar dev mode.

**Test accounts already seeded:**
- `test@example.com` / `password` (traveler, original test account)
- `ahmed@test.com`, `sara@test.com`, `bilal@test.com`, `fatima@test.com`, `hassan@test.com`, `zainab@test.com` — all `password`, seeded travelers with different preferences for testing the matching algorithm
- `traveler@test.com` / `password` (second test traveler)
- An agency test account — check `Agency::first()->user` in tinker if needed, or register a new one via `/#/agencies/register` while logged in as a `type: agency` user

---

## 4. What's Built (Phase by Phase)

| Phase | Name | Status |
|---|---|---|
| 0 | Environment setup | ✅ Done |
| 1 | Auth system (register/login/logout, Sanctum tokens, route guards) | ✅ Done |
| 2 | Profiles + Destinations (49 Pakistani destinations seeded) | ✅ Done |
| 3 | Trip system (create/browse/join/leave/my-trips) | ✅ Done |
| 4 | Real-time group chat (Reverb WebSockets) | ✅ Done |
| 5 | Planning tools (itinerary, expense tracker, checklist — all real-time) | ✅ Done |
| 6 | Agency platform (packages, bookings, tiers, analytics) | ✅ Done |
| 7 | Matching algorithm (suggested trips/travelers, Redis-cached scoring) | ✅ Done |
| 8 | Safety features (report, block, verified badges, privacy settings) | ⚠️ **Built but UNTESTED — start here** |
| 8.5 | User discovery, following, DMs, trip invites via DM | ⚠️ **Built but UNTESTED — start here** |
| 9 | Community feed | ❌ Not started |
| 10 | Payments (JazzCash/EasyPaisa/Stripe) | ❌ Not started |
| 11 | Admin panel | ❌ Not started |
| 12 | Polish & testing | ❌ Not started |
| 13 | Deployment | ❌ Not started |
| 14 | Beta launch | ❌ Not started |

**Phases 8 and 8.5 were built together** in one large session and have gone through one round of bug fixes based on manual spot-checking, but have **not been systematically tested end to end**. This is where you start.

---

## 5. Deviation from Original Master Plan

Two things were added beyond the original plan, based on product decisions made mid-build:

**1. Full social layer added in Phase 8.5** (not in original master plan): user discovery page (`/#/people`), following system, direct messaging between any two users, and trip invites sent via DM. This was added because the founder wants Mera Musafir to function partially as a social platform, not just a trip-planning tool. Community feed (Phase 9) will build on top of this.

**2. Agency packages now spawn real trips.** Originally, agency packages were just static listings with a booking system — no chat, no planning tools for the people who booked. This was identified as a product gap: agency packages are the core revenue driver, so booked travelers need a way to communicate. Fix implemented: when an agency confirms the first booking on a package, the system auto-creates a `Trip` record (linked via `package_id`), auto-creates its group chat, and adds the agency as host. Every subsequent confirmed booking adds that traveler to the trip. Cancelling a booking removes them.

Both of these are considered core to the product now, not optional extras.

---

## 6. Known Bugs Fixed (verify these still hold)

A round of bugs was found and fixed. **These fixes have not been re-verified after the fix** — check them as part of your Phase 8/8.5 testing pass:

1. **Follow button reset on reload** — was showing "Follow" after reload even when already following, and follower count would go negative. Fixed by always reading `is_following`/`followers_count` fresh from the API response instead of local state.
2. **DM message alignment wrong on first load** — own messages appeared on the wrong side until page reload. Root cause was a race condition — messages rendered before `authStore.user` was populated from localStorage. Fixed with a loading guard.
3. **Long messages with no spaces overflow the chat bubble** — fixed with `overflow-wrap: anywhere; word-break: break-word;`
4. **Agency packages had no group chat** — fixed via the trip auto-spawn described in Section 5.
5. **Booked travelers couldn't see their booking status or join the group chat** — needed a traveler-facing `GET /api/v1/bookings/my` endpoint (the existing endpoint was agency-owner-only). Added, plus a `/#/my-bookings` page.
6. **DM privacy setting (Everyone/Followers/Nobody) only enforced on new conversations** — someone could keep messaging you in an existing conversation even after you changed your privacy setting. Fixed to check privacy on every message send, not just conversation creation.
7. **Blocking didn't affect existing DM conversations** — a blocked user could still message you and vice versa. Fixed with Instagram-style blocking: existing conversation history stays visible to both sides, but the input bar is replaced with "You blocked this user" (+ Unblock button) for the blocker, and "You can't reply to this conversation" for the blocked person. No notification is sent to the blocked person.
8. **Expense settlement math was broken** — was showing users owing themselves money, and listing every individual per-expense debt separately instead of consolidating. Fixed with a proper net-settlement algorithm: sum total paid vs. owed per user across all expenses, then generate the minimum number of transactions needed. Verified correct against manual math — confirmed working.

---

## 7. Where You Start: Full Phase 8 & 8.5 Test Checklist

Nobody has run this checklist top to bottom yet. Go through every item. Note pass/fail for each. Don't fix as you go — collect the full list of failures first, then we batch-fix.

### Block System
1. Block a user from their profile (three-dot menu → Block User) → do they disappear from `/#/people`?
2. Do their trips disappear from `/#/trips`?
3. Open an existing DM with them → does the input bar show "You blocked this user" with an Unblock button?
4. `/#/privacy` → does the blocked user appear in the list? Click Unblock → do they reappear in discovery?
5. Log in as the blocked user → try to message in the existing DM → are they prevented, with the "can't reply" message?

### Report System
6. Report a user (three-dot → Report User) → reason + description → does it submit with a success toast? (Nothing else should happen yet — reports just queue for Phase 11 admin review.)
7. Right-click a chat message → does "Report Message" appear?
8. Any package detail page → is there a flag/report button?

### DM Privacy
9. `/#/privacy` → set DM privacy to "Followers only"
10. As a non-follower, try to message that user (new or existing conversation) → are they blocked with a clear error?
11. Set back to "Everyone" → does messaging resume normally?

### Follow System
12. `/#/people` → follow someone → does the follower count update correctly?
13. Reload the page → does it still say "Unfollow" (not reset)?
14. Unfollow → does the count go back down correctly (not negative)?
15. Mutual follow between two accounts → does a "Friends" badge appear?

### Direct Messages
16. `/#/people` → click Message on a user → does a conversation open/create?
17. Send a message → right-aligned, purple, for you?
18. Open the same conversation as the other user (separate browser/tab) → left-aligned, grey, appears in real time without refresh?
19. Reply back → appears instantly on your side?
20. Send a long no-space string → wraps inside the bubble, no overflow?

### Trip Invites via DM
21. In a DM, click the trip invite icon → do your trips list in the modal?
22. Send an invite → does it render as a special card (not plain text)?
23. Recipient sees Accept/Decline buttons on the invite card?
24. Accept → check `/#/my-trips` — are they now a member?

### Trip Member Three-Dot Menu
25. Trip detail page → members list → three-dot on any other member → View Profile, Message, Block, Report all present?
26. View Profile → correct navigation?
27. Message → opens/creates the right conversation?

### Verified Badge
28. Set a user `is_verified = true` (via API/DB) → confirm the purple badge shows on: their profile, trip cards (as host), chat messages, trip member list.

### Women-Only Trips
29. Create a trip with visibility = Women Only.
30. Log in as a male user → attempt to join → rejected with a clear message?

### Agency Package → Trip Flow (new, from Section 5)
31. Book a package as a traveler.
32. Confirm the booking as the agency (dashboard → Bookings tab).
33. As the traveler, reload the package page → does it show "Booking Confirmed" + "Join Group Chat"?
34. Click Join Group Chat → does it open the correct trip chat?
35. `/#/my-bookings` → does the booking show with correct status and a working Join Group Chat button?
36. Cancel a booking → does "Book Now" reappear on the package (re-booking should be allowed after cancellation)?

---

## 8. After Testing — What Comes Next

Once the Phase 8/8.5 checklist is fully run and any failures are batched into one fix prompt, the plan continues as:

- **Phase 9** — Community Feed (posts, comments, destination-tagged content). Now that following/DMs exist, this phase should also surface followed users' posts in the feed.
- **Phase 10** — Payments (JazzCash, EasyPaisa, Stripe)
- **Phase 11** — Admin Panel (this is where the Report queue from Phase 8 actually gets reviewed/actioned — currently reports just sit in the database)
- **Phase 12** — Polish & Testing (styling pass happens here — current UI is functional but intentionally unstyled/default Quasar theme in most new pages; purple/mauve theme is applied globally but individual page layouts haven't had a design pass)
- **Phase 13** — Deployment (also the point at which Quasar's PWA build mode gets enabled — `quasar build -m pwa` — so the app becomes installable on phones without needing a native app or App Store)
- **Phase 14** — Beta Launch

---

## 9. Development Conventions to Follow

- **Vue:** `<script setup>` only, never Options API
- **Pinia:** one store per domain (`authStore`, `tripStore`, `chatStore`, `agencyStore`, `socialStore`, `safetyStore`, `matchStore`, `planningStore`)
- **Laravel:** thin controllers, business logic in Service classes where it exists (e.g. `AuthService`, `MatchingService`), Form Requests for validation, API Resources for every response — never return raw Eloquent models
- **API responses:** always `{ message: "...", data: {...} }` shape
- **Routes:** specific/static routes must be registered BEFORE wildcard routes (e.g. `/users/blocked` before `/users/{user}`, `/packages/my` before `/packages/{package}`) — this has bitten us multiple times, check route order carefully when adding new endpoints
- **Real-time:** all WebSocket features go through Laravel Reverb + Laravel Echo, following the exact subscribe/unsubscribe pattern already established in `chatStore.js` — copy that pattern for any new real-time feature
- **CORS:** `config/cors.php` paths array must include `broadcasting/auth` or WebSocket auth silently fails with a CORS error (this took a while to debug — it's already fixed, just noting why it's there)

---

## 10. Development Approach

Claude Code (Anthropic's CLI coding tool) has been the primary implementation tool throughout — given detailed, complete prompts covering both backend and frontend for each phase, then manually tested by the founder afterward. This handover assumes whoever picks this up will continue that same workflow: detailed prompt → implementation → manual test → bug list → batch fix prompt.

Claude Code does not have memory of past sessions — always give it context by pointing it at `MeraMusafir_MasterPlan.md` and this handover doc, plus naming the specific files relevant to the task at hand.

---

*End of handover. Start with Section 7.*
