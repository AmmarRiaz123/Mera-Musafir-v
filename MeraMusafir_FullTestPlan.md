# Mera Musafir — Complete Manual Test Plan

A full pre-launch walkthrough of every flow in the app. Work through it over a few days; each section stands alone. Check the box, note anything odd.

**Golden rule for this app:** if something looks broken, **hard-reload first (Cmd/Ctrl+Shift+R)**. A stale frontend bundle has been the cause more often than a real bug.

---

## 0. Setup & accounts

Run the four processes (see the local-run notes): Docker (MySQL+Redis), `php artisan serve`, `php artisan reverb:start`, `npx quasar dev`. App at `http://localhost:9000/#/`.

**Seeded logins** (all password `password`):

| Account | Email | Role |
|---|---|---|
| Travellers | `test1@test.com` … `test6@test.com` | 3 female (1,3,5-ish), 3 male; some verified |
| Agency A | `agency1@test.com` | Hunza Explorers (Pro, verified) |
| Agency B | `agency2@test.com` | Karakoram Adventures (Basic) |
| Admin | `admin@meramusafir.com` | Platform operator |

Two browsers (or a normal + an incognito window) let you act as two people at once — needed for real-time, DMs, host/member flows.

- [ ] All four processes up; app loads at `/#/`.
- [ ] `reverb:start` is running (needed for §10 real-time). If it's down, real-time silently degrades to polling.

---

## 1. Auth & onboarding

1. [ ] Register a brand-new account → lands logged in.
2. [ ] Log out → log back in.
3. [ ] Wrong password → clear error, no login.
4. [ ] Visit `/#/login` while already logged in → redirected to home (or dashboard/console per role).
5. [ ] Refresh any page while logged in → stays logged in.
6. [ ] A **suspended** account can't log in (see §12 to create one) — the login shows "account has been suspended".

**Role landing:**
7. [ ] Traveller login → traveller home ("Trips Picked for You").
8. [ ] Agency login → **agency dashboard**, never the traveller home.
9. [ ] Admin login → **admin console** at `/admin`.

---

## 2. Profile & the social graph

1. [ ] Own profile: banner, avatar, name, city, travel-style/region chips, **Followers / Following / Friends** counts.
2. [ ] Edit profile (name, bio, city, avatar) → saves and persists on reload.
3. [ ] Open another user's profile (People → a card) → their info, a **Follow** button.
4. [ ] Follow them → button flips to Following, their follower count +1. Reload → still following (no reset).
5. [ ] Unfollow → reverts cleanly, count back down.
6. [ ] Click **Followers / Following / Friends** on your profile → the connections dialog opens with tabs.
7. [ ] Each tab shows the right people; the counts on the tabs match.
8. [ ] Click a person in the dialog (anywhere on the row) → opens **their** profile.
9. [ ] From another profile → then to a third profile → content updates each time (no stale page).
10. [ ] On **your** followers, the ⋯ menu offers **Remove follower** and **Block**; "Remove" explains it differs from blocking.
11. [ ] Remove a follower → they drop off the list, your follower count −1.
12. [ ] "Friends" = mutual follows only.

**Privacy:**
13. [ ] Profile → Privacy Settings → set "Who can message you" to **People I follow** → save → reload → the setting persisted (not reset to Everyone).
14. [ ] Blocked-users list shows anyone you've blocked, with Unblock.

---

## 3. Destinations

1. [ ] Explore → grid of destinations.
2. [ ] Open one → hero, description, best-season, tags.
3. [ ] **Agency packages** section — packages for that place.
4. [ ] **Trips to \<place\>** section — traveller-planned trips there (separate from packages), with an "All Trips" link.
5. [ ] **From the community** — posts tagged to that place.
6. [ ] The community section's **sort control** (Recommended / Newest / Most liked / Most talked about) reorders the posts.

---

## 4. Trips — create, browse, join

**Create (the 3-step wizard):**
1. [ ] Create Trip → step 1 basics (title, cover, description), step 2 dates & budget, step 3 type & "Who can join?".
2. [ ] End date before start date → blocked.
3. [ ] **"Who can join?"**: Public / Women Only / Invite Only.
4. [ ] **"Review each request to join"** toggle: off for a public trip = instant join; on = you approve each request. For **Invite Only** it's forced on and disabled ("always need your approval").
5. [ ] Create a **public, no-approval** trip → you're the host, it's browsable.
6. [ ] Create a **public, requires-approval** trip → also browsable.
7. [ ] Create a **women-only** trip.
8. [ ] Create an **invite-only** trip → it does **not** appear in Browse Trips.

**Browse & join (use a second traveller):**
9. [ ] Browse Trips shows public + women-only + requires-approval trips; **not** invite-only ones.
10. [ ] Open a **public no-approval** trip as another traveller → **Join Trip** → the styled popup → "You'll join straight away" → joined instantly, seat count +1.
11. [ ] Open a **requires-approval** trip → the button says **Request to Join**, the popup says the host reviews each request → joining lands you **pending** ("Awaiting approval"), seat count unchanged.
12. [ ] Try joining the same trip again → "your request is already in, host just needs to approve".
13. [ ] A **male** traveller opening a women-only trip → cannot join (blocked with a clear message).
14. [ ] A **date-conflicting** trip (overlaps a trip/booking you're already committed to) → the join popup shows an amber notice naming the clash, but still lets you continue.

**Host approving:**
15. [ ] As the **host** of the requires-approval trip, open it → a **Join requests** section lists the pending person with **Approve / Decline**.
16. [ ] Approve → they become a member, seat count +1, the request clears.
17. [ ] (On another request) Decline → the request clears; the person is told their request wasn't approved.

**Leaving & removing:**
18. [ ] As a member, **Leave Trip** → seat count −1, you're out.
19. [ ] As **host**, a member's card ⋯ menu has **Remove from trip** (a regular member's menu does not).
20. [ ] Remove a member → seat count −1, they're notified "removed from \<trip\>".
21. [ ] The removed member tries to rejoin → button says **Request to rejoin**, it lands **pending** (not instant), even on an open trip — the host must approve again.
22. [ ] Host can't remove themselves; a non-host can't remove anyone.

**Invites (via DM — see §6 for the DM basics):**
23. [ ] As **host**, DM someone → the trip-invite dropdown lists your trips → send an invite → they accept → they **join directly** (host invite bypasses the approval gate).
24. [ ] As a **regular member** of a requires-approval trip, invite someone → they accept → they land **pending** (a member's invite still needs host approval); the host sees it in Join requests.

---

## 5. Trip tools (members only)

Open a trip you're in → the tools row (Chat / Itinerary / Expenses / Checklist / Invite).
1. [ ] **Chat** — send a message; with a second member in the same trip chat, it arrives **live** (no reload).
2. [ ] **Itinerary** — add a day/activity; a second member sees it update live.
3. [ ] **Expenses** — add an expense; the split/settlement recomputes; live for others.
4. [ ] **Checklist** — add/tick items; live for others.
5. [ ] Non-members can't reach these tools for that trip.

---

## 6. Direct messages

1. [ ] People/profile → **Message** → opens (or creates) a conversation.
2. [ ] Send a text → appears immediately; the other person receives it **live**.
3. [ ] Paste past the character limit → capped, with the limit banner.
4. [ ] **DM privacy gate**: set your privacy to "People I follow", then from an account that you don't follow, try to DM you → it opens the **"Knock first"** message-request composer (not a dead error).
5. [ ] Send the request → you (recipient) see it in the **bell** with Accept / Ignore, and the note quoted.
6. [ ] Accepting a request → opens the chat, delivers the held message, and follows the sender back (the composer said it would).
7. [ ] Sending a request **auto-follows** the recipient (so they can reply) — the composer notes this.
8. [ ] **Share a post to a DM** (§8) renders as a preview card in the recipient's chat; if they blocked the author it's a "Post unavailable" card.

---

## 7. Community feed

1. [ ] Community → feed loads, ranked (not strictly chronological).
2. [ ] Each card: author, avatar, time-ago, type chip, place tag (if any), like + comment counts.
3. [ ] **Sort control** (Recommended / Newest / Most liked / Most talked about) reorders.
4. [ ] **Category filters** (All, Companions, Stories, Tips, …) narrow the feed.
5. [ ] Infinite scroll loads more; end shows "you're all caught up".

**Compose (the + button → blurred popup):**
6. [ ] The **+** opens the composer over a blurred page.
7. [ ] Post text only → appears at the top.
8. [ ] A long caption (up to 2000 chars) → the caption box scrolls internally; **Send stays reachable**.
9. [ ] Attach a **photo** → preview → renders full-bleed in the feed.
10. [ ] Attach a **video** → a **poster frame** shows (not a black rectangle) before you press play; plays inline.
11. [ ] **GIF** picker + **Music** mood shelves (needs keys — see the Phase 9 doc; without keys they show a friendly "not set up" note, not an error).
12. [ ] Tag a **destination** → the tag appears and the post shows on that destination's page.

**Interact:**
13. [ ] Like (heart) → fills, count +1; reload → persisted; unlike works.
14. [ ] **Double-tap** media → heart burst + like (double-tapping again never unlikes).
15. [ ] Comment (with a photo/GIF attachment) → appears, count +1.
16. [ ] Delete your own comment; on **your** post you can delete others' comments.
17. [ ] Click **more** / the timestamp / "View all N comments" → opens the **single-post view** with the full caption and all comments.
18. [ ] Comment authors and the post author are all clickable → open their profiles.

**Companion posts:**
19. [ ] A "Find companions" post shows **"I'm interested"** on others' accounts.
20. [ ] Click it → opens a DM (or the message-request composer if they gate DMs) — never a dead error.

**Agency posts:**
21. [ ] Post as an agency → the card has a plum edge, tinted header, **AGENCY** badge and a "View agency" link (visually distinct from traveller posts).

**Report:**
22. [ ] On someone else's post, ⋯ → **Report** → the styled report dialog (tappable reasons, anonymous note) → submits.

---

## 8. Sharing a post

1. [ ] A post's **Send** → search a person by name → send → it lands as a **preview card** in their chat (image + music preview if present).
2. [ ] Clicking the card in the chat opens the **exact** post (single-post view), not just the feed.
3. [ ] If the recipient has blocked the author → the card reads "Post unavailable".

---

## 9. Notifications

Trigger each as a second account, then check the first account's **bell**:
1. [ ] **Follow** you → teal person badge.
2. [ ] **Comment** on your post → amber chat badge.
3. [ ] **Like** your post → pink heart badge.
4. [ ] **Message** you → violet badge.
5. [ ] **Sidebar red dots**: the relevant tab (Community / Profile / Messages / My Trips / My Bookings / agency Bookings) shows a dot; opening the tab clears it.
6. [ ] The bell badge counts unread; **Mark all read** clears them.
7. [ ] Clicking a notification navigates to the right place and marks it read.
8. [ ] **Live**: with the bell closed, have the second account act → the count/dot appears within a few seconds (real-time), or ≤45s (poll fallback if Reverb is down).

**Booking/trip notifications** (cross-check with §11, §4):
9. [ ] Agency approves your booking → you get "approved — pay now".
10. [ ] You pay → the **agency** gets "X paid for \<package\>".
11. [ ] Someone joins/requests your trip → you (host) get notified.
12. [ ] The host removes you → you're notified.
13. [ ] An admin resolves a report you filed → you get "we reviewed your report" (see §12).

---

## 10. Real-time (needs `reverb:start`)

Two browsers, two accounts:
1. [ ] **DM** — B sends, A (chat open) sees it live.
2. [ ] **Trip chat** — B posts, A (same trip chat) sees it live.
3. [ ] **Community feed** — B publishes a post; A (on Community) sees a **"N new posts"** pill; clicking it prepends the post.
4. [ ] **Notifications** — B follows/likes/comments; A's bell updates live.
5. [ ] Open a DM, then Community, then a trip chat in one session → all keep working (the shared Echo connection doesn't clobber channels).

---

## 11. Packages, booking & payments (agency ↔ traveller)

**Agency side (agency1 / agency2):**
1. [ ] Agency dashboard: overview, packages, bookings, analytics, plan & billing.
2. [ ] **New Package** → create one (cover, price, dates, capacity, includes) → it appears in your storefront and in Packages browse.
3. [ ] The agency can **browse** other agencies' packages, trips, community, people (DISCOVER section) — but **cannot** book a package or join a trip (the buttons aren't there; a note explains why).

**Traveller books (vet-then-pay):**
4. [ ] As a traveller, open a package → the styled **Book this trip** popup (stepper for travellers, live total, "you won't be charged yet").
5. [ ] Send request → **My Bookings** shows it **Pending — "Waiting on the agency"**, no Pay button.
6. [ ] Trying `/#/checkout?...` for it directly → refused ("agency hasn't approved yet").
7. [ ] As the **agency**, Bookings → **Approve** → "traveller has 48 hours to pay".
8. [ ] Back as the traveller → the booking shows **Payment due** with a **Pay now** button and a countdown (red under 12h).
9. [ ] Pay now → checkout: **Test payment** is the only method (real gateways are hidden until credentials exist — that's correct).
10. [ ] "Simulate a declined payment" → red decline, no receipt, booking stays approved.
11. [ ] Pay → receipt (reference, package, method, total) + "you've been added to the group chat".
12. [ ] The booking is now **Confirmed** with a **Group chat** button; seat count moved by your traveller count (not by 1).
13. [ ] The **agency** gets the "X paid" notification; their Bookings shows it paid.
14. [ ] If the **payment window lapses** (force `payment_due_at` to the past in the DB, or wait): Pay is refused and the seat is released.

**Transactions:**
15. [ ] `/transactions` lists every attempt (failed struck-through, paid, refunded); **Total paid** counts only settled.
16. [ ] A settled booking shows the **5% platform fee** broken out.
17. [ ] An **agency's** empty transactions points to **View plans** (not "browse packages").

**Subscriptions:**
18. [ ] Agency → Plan & billing → Pro / Premium, monthly / yearly (yearly shows the 2-months-free saving).
19. [ ] Buy a plan → checkout → pay → the tier upgrades, billing history shows it, the sidebar badge updates.
20. [ ] The subscription payment shows **no platform fee** (commission is on bookings, not what an agency pays us).
21. [ ] Renew early → the term **extends** from the existing expiry, not from today.

**Agency profile:**
22. [ ] Public agency page: cover, logo, name (not hidden behind the banner), followers/packages, Follow button (rounded), packages grid.
23. [ ] Follow the agency → persists on reload, count correct (no −1 bug).

---

## 12. Admin console (`admin@meramusafir.com`)

1. [ ] A traveller visiting `/#/admin` is bounced home; no "Admin console" link in their sidebar.
2. [ ] Admin sees the console: dark sidebar, Dashboard / Users / Agencies / Reports / Destinations / Broadcast.

**Dashboard:**
3. [ ] Metric cards (travellers +today, agencies, active trips, commission this month) and two queue cards (open reports, agencies awaiting verification); sidebar badges match.

**Users:**
4. [ ] Search / filter by type / filter by status.
5. [ ] **Suspend** a traveller → they're suspended; **Reinstate** works. You can't suspend an admin.
6. [ ] **A suspended user, mid-session, is kicked to login on their next action** (test in a second browser: log in as the target, then suspend them from the console → their next click bounces to a "suspended" login).
7. [ ] A suspended user's **profile 404s**, they're **gone from People / feed / trips / packages**, and **can't be followed** — but an **admin can still open their profile**.

**Agencies:**
8. [ ] Verification queue (awaiting review) → **Verify** grants the badge and clears the queue; **Unverify** reverts.

**Reports:**
9. [ ] Open reports show reason chip, subject kind (with colour), an **Open** link to the content, and the reporter.
10. [ ] **Suspend \<name\>** on a report → suspends the person behind it *and* closes the report; their other open reports flip to "already suspended".
11. [ ] **Dismiss** → closes as no-violation; the reporter gets a "we reviewed your report" notification.
12. [ ] **Mark actioned** (when there's no one to suspend) closes it too.

**Destinations:**
13. [ ] Toggle **Featured** and **Visible**; a hidden destination drops out of the traveller-facing Explore.

**Broadcast:**
14. [ ] Send an announcement to Everyone / Travellers / Agencies → confirm dialog → "sent to N people"; recipients see it in their bell.

---

## 13. Agency-account guardrails (recheck)

1. [ ] Agency **can't** book any package (its own or a competitor's) — no Book button, a note instead.
2. [ ] Agency **can't** join or request any traveller trip — no Join button, a note instead.
3. [ ] Agency **can** view/browse packages, trips, agencies, people, community.
4. [ ] Agency never lands on, or can navigate to, the traveller home / My Trips / My Bookings.

---

## 14. Safety & edge cases

1. [ ] **Block a user** (profile ⋯ → Block) → their posts vanish from your feed, their trips/DMs are gated both ways.
2. [ ] Unblock → they reappear.
3. [ ] **Report** a user / post / package / message → lands in the admin Reports queue.
4. [ ] A blocked person can't DM you and vice-versa.
5. [ ] Deleting your own post/comment works; you can't edit/delete others' (except moderating your own post's comments, or admin).

---

## 15. Cross-cutting UI polish

1. [ ] No large plain-black page titles — headings use the plum gradient eyebrow/title style.
2. [ ] Dialogs (book, join, report, message-request, share, connections) all blur the page behind them.
3. [ ] Icons render as single glyphs everywhere (no doubled/garbled icons).
4. [ ] Empty states are friendly (no blank pages) — People, Transactions, Reports queue clear, no-posts destination, etc.
5. [ ] Responsive: sidebar is closable; nothing scrolls horizontally on a normal window.
6. [ ] Every list that shows a person is clickable through to their profile.

---

## What to log as you go

For anything off: the **account**, the **exact steps**, what you **expected** vs **saw**, and whether a **hard reload** fixed it. That last one instantly separates a stale-bundle hiccup from a real bug.

## Known non-bugs (don't report these)

- Only **"Test payment"** at checkout — real gateways (JazzCash/EasyPaisa/Card) stay hidden until merchant credentials exist.
- **GIF/Music** show "not set up yet" until you add the free Giphy/Jamendo keys.
- **Video isn't transcoded** (a 4K clip stays 4K, 50MB cap) — no FFmpeg in this environment.
- **No auto-renew** on subscriptions; charged per term.
- Seeded posts/trips are demo content.
