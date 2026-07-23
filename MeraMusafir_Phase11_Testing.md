# Phase 11 — Testing Checklist

**Before you start.** All admin test mutations were reverted: Karakoram is back to Basic/unverified, the reports queue is back to 9 open, test suspensions are cleared, and the test broadcasts were deleted. One thing left alone: a settled PKR 7,999 subscription payment dated today that wasn't from testing — it shows as real revenue on the dashboard, which is correct.

**Log in as admin:** `admin@meramusafir.com` / `password`. Other accounts: `test1@`…`test6@test.com`, `agency1@` (Hunza Explorers), `agency2@` (Karakoram) — all `password`.

---

## 1. Access & guard

1. As **admin**, look at the sidebar — there's an **Admin console** link (shield icon) near the bottom.
2. Click it → you land in the dark-purple console at `/admin`.
3. ☐ The console has its own layout: dark sidebar, "Mera Musafir Admin" header, "Exit console" top-right.
4. Log out, log in as **test1** (a traveller).
5. ☐ **No** Admin console link in test1's sidebar.
6. Manually visit `http://localhost:9000/#/admin`.
7. ☐ You're bounced straight back to the home page — never see the console.

## 2. Dashboard

8. Back as **admin**, open the console.
9. ☐ Four metric cards: Travellers (with "+N today"), Agencies, Active trips, Commission this month (with gross processed beneath).
10. ☐ Two queue cards: **Open reports** and **Agencies awaiting verification**, both showing a count.
11. ☐ The sidebar badges (red) on **Reports** and **Agencies** match those two counts.
12. ☐ A **Revenue — last 6 months** section: a bar per month if any payments settled, or "No settled payments yet."
13. Click the **Open reports** card → it takes you to the Reports page.

## 3. Users

14. Open **Users**.
15. ☐ A paginated list — avatar, name, verified tick, type chip (TRAVELER/AGENCY), email, followers, join date.
16. Type a name in the search box → ☐ the list filters as you type.
17. Click the **Travellers** / **Agencies** type filter → ☐ list narrows to that type.
18. Click **Active** / **Suspended** → ☐ list narrows by status.
19. Find any traveller, click **Suspend** → ☐ toast confirms, the chip flips to "Suspended", the button becomes **Reinstate**.
20. Filter to **Suspended** → ☐ that user is there.
21. Click **Reinstate** → ☐ back to active. *(Leave everyone reinstated.)*
22. ☐ Try to find yourself (the admin) — you have no Suspend button, or suspending is refused. (There's a server guard: admins can't be suspended.)

## 4. Agencies (verification)

23. Open **Agencies** — defaults to **Awaiting review**.
24. ☐ Karakoram Adventures is there (unverified), showing licence/owner/contact and package count.
25. Click **Verify** → ☐ toast, the card leaves the pending tab, and the **Agencies** sidebar badge drops.
26. Switch to the **Verified** tab → ☐ Karakoram is now there with the badge.
27. Click **Unverify** → ☐ it flips back. *(Leave Karakoram unverified to match its seeded state.)*
28. ☐ The **Storefront** button opens the public agency page in a new tab.

## 5. Reports (moderation)

29. Open **Reports** — defaults to **Open**.
30. ☐ Each card shows a coloured reason chip (SPAM/SCAM/etc.), the subject kind (Post/Package/User) with a matching left-edge colour, and the reporter.
31. ☐ Reports on content that still exists have an **Open** link; ones on deleted content say "already removed" and strike the label.
32. On any report, click **Dismiss** → ☐ a dialog opens explaining "close as not a violation" with a note field.
33. Add a note, confirm → ☐ toast "Report closed", the card leaves the Open queue, the Reports badge drops by one.
34. Switch to the **Dismissed** tab → ☐ that report is there, showing your note.
35. Try **Mark actioned** on another report → ☐ the dialog wording changes to "record that you acted on this", and it closes the report the same way.
36. *(Both dismissed/actioned reports stay closed — that's fine, or reopen via the DB if you want a clean queue.)*

## 6. Destinations

37. Open **Destinations**.
38. ☐ A grid of cards with cover images; featured ones show a gold "Featured" tag, hidden ones a "Hidden" tag and dimmed.
39. Search "hunza" → ☐ the grid filters.
40. Toggle **Featured** off on a destination → ☐ the gold tag disappears; toggle back on → ☐ it returns.
41. Toggle **Visible** off → ☐ the card dims and shows "Hidden"; toggle back on → ☐ it brightens.
42. *(Optional cross-check: a destination toggled Hidden should drop out of the traveller-facing `/destinations` list.)*

## 7. Broadcast

43. Open **Broadcast**.
44. ☐ Three audience buttons (Everyone / Travellers / Agencies), a title, an optional message and link, and a **live preview** card that updates as you type.
45. Pick **Agencies**, type a title and message.
46. Click **Send to Agencies** → ☐ a confirm dialog ("this can't be unsent").
47. Confirm → ☐ toast "Announcement sent to 2 people."
48. Log in as **agency1@test.com** → open the notification bell.
49. ☐ Your announcement is there, with the campaign icon and your text.
50. *(Delete the test announcement afterward if you want a clean slate — it's just a notification.)*

## 8. Badges stay in sync

51. ☐ Whenever you verify an agency or close a report, the matching sidebar badge and the dashboard queue count update **without a page reload**.

---

## Known gaps (not bugs)

- **No destination add/edit.** The console toggles feature/visibility; creating or editing a destination's copy still happens in the DB.
- **Broadcast targeting is coarse** — all / travellers / agencies, no finer segments.
- **No separate admin login.** Admins sign in through the normal login; the role gates the console.

## If something looks off

Hard-reload first (**Cmd+Shift+R**). A stale bundle has been the usual culprit in this project.
