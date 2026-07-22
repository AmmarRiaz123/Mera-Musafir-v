# Phase 10 — Testing Checklist

**Before you start.** All test data was reset after my own testing: no payments, no subscriptions, agencies back to their original tiers (Hunza Explorers = Pro, Karakoram = Basic), and every package's seat count reconciled against its live bookings. So you're starting clean.

**Accounts:** `test1@test.com` … `test6@test.com`, `agency1@test.com` (Hunza Explorers), `agency2@test.com` (Karakoram), `admin@meramusafir.com` — all password `password`.

**Expect one payment method: "Test payment".** That's correct — JazzCash, EasyPaisa and Card stay hidden until their credentials exist. See §7 for how to prove that's deliberate.

---

## 1. Booking → approval → payment (the main path)

**The agency vets first.** A booking is `pending` until the agency approves it, and only then can it be paid for. No money is ever held for a booking that gets declined.

1. As **test1**, open a package (`/packages` → any) and book 2 travellers.
2. ☐ You **stay on the package page** — no checkout. A notice reads *"Request sent. You'll be able to pay once the agency approves it."*
3. ☐ **My Trips → My Packages** shows it as **Pending**, with *"Waiting on the agency"* and **no Pay button**.
4. ☐ Visiting `/checkout?type=booking&id=<that id>` directly and pressing Pay is refused: *"The agency hasn't approved this booking yet…"*
5. As the **owning agency** (agency1 = Hunza Explorers, agency2 = Karakoram), open the dashboard → Bookings.
6. ☐ The action reads **Approve**, not Confirm.
7. Approve it. ☐ Notice: *"Approved — the traveller has 48 hours to pay."*
8. Back as **test1** → My Trips → My Packages.
9. ☐ Badge now reads **Payment due**, with a **Pay now** button and *"1d left to pay"*.
10. Click **Pay now** → checkout. ☐ Summary names the package, traveller count and total = price × travellers.
11. ☐ "Test payment" is **already selected** (it's the only option), with a note about test mode.
12. Click **Pay**. ☐ Receipt: reference (`MM…`), package, method, date, total.
13. ☐ Message reads *"Your seat is booked and you've been added to the trip's group chat."*

## 2. The payment actually did something

14. ☐ **My Trips → My Packages**: the booking now shows **Confirmed** with a **Group chat** button.
15. ☐ The package's "spots left" dropped by **your traveller count**, not by 1.
16. ☐ **My Trips → Trips I Joined**: the departure trip is there.
17. ☐ Open its **group chat** — you're a member and can post.
18. ☐ Approval alone did **not** seat you — only payment did. (Worth confirming on a second booking: approve it, check Trips I Joined, then pay.)

## 2b. The payment window

19. Book something as **test5** and have the agency approve it. Note the seats drop.
20. Force the window shut: `php artisan tinker --execute="App\Models\Booking::where('id',<id>)->update(['payment_due_at'=>now()->subHour()]);"`
21. ☐ Pressing **Pay now** is refused: *"The payment window on this booking has passed…"*
22. ☐ The package's **spots come straight back**.
23. ☐ `php artisan bookings:expire` reports what it released (run it with a fresh lapsed booking to see a non-zero count).

## 3. Declines

24. Book another package as **test1**, have the agency approve it, reach checkout, click **"Simulate a declined payment"**.
25. ☐ A red box says *"The bank declined this payment."* — no receipt.
26. ☐ The booking stays **approved / unpaid** and keeps its Pay now button.
27. Now click **Pay** on the same screen.
28. ☐ It succeeds, and you get a receipt.
29. ☐ In **Transactions**, both attempts appear — the failed one struck through, the successful one paid.

## 4. Guards (each should refuse)

30. ☐ Pay for the same booking twice → *"This booking is already paid."*
31. ☐ Open `/checkout?type=booking&id=<a booking of test5's>` as test1 → refuses, nothing charged.
32. ☐ A traveller (not admin) cannot refund — no refund control is exposed to them.
33. ☐ An agency cannot approve a booking on another agency's package.

## 5. Transactions

34. ☐ `/transactions` (sidebar → **Transactions**) lists every attempt, newest first.
35. ☐ **Total paid** at top counts only settled payments — not failed, not refunded.
36. ☐ A settled booking shows *"incl. PKR … platform fee"* = **5%** of the amount.
37. ☐ Each row shows its reference, method and date.
38. ☐ A brand-new account sees the empty state, not a blank page.

## 6. Agency subscriptions

39. As **agency2** (Basic), open **Plan & billing** in the sidebar.
40. ☐ Banner reads *"You're on the free Basic plan."*
41. ☐ Two plans: Pro **PKR 2,999/mo**, Premium **PKR 7,999/mo**.
42. Switch to **Yearly**.
43. ☐ Prices become 29,990 and 79,990 — and each shows the saving (5,998 / 15,998). That's ten months for twelve.
44. Click **Go Pro** → you land on checkout for the Pro plan, billed yearly.
45. ☐ Pay. Receipt says *"Your plan is active — the new limits apply straight away."*
46. ☐ Back on **Plan & billing**: *"You're on Pro until \<one year out\>."*
47. ☐ **Billing history** shows one row: Pro · Yearly · Active · PKR 29,990.
48. ☐ The sidebar badge under the agency name now reads **PRO**.
49. ☐ In **Transactions**, the subscription payment shows **no platform fee** — commission applies to bookings, not to what an agency pays us.

**Renewal extends, it doesn't reset:**

50. Buy **Pro monthly** again straight away.
51. ☐ The expiry moves **one month past the existing date**, not one month from today.

## 7. Prove the hidden gateways are deliberate

52. ☐ At checkout, only "Test payment" appears.
53. In `backend/.env` add any placeholder values:
    `JAZZCASH_MERCHANT_ID=x`, `JAZZCASH_PASSWORD=x`, `JAZZCASH_INTEGRITY_SALT=x`
54. Run `php artisan config:clear`, reload checkout.
55. ☐ **JazzCash now appears** in the list — the wiring is real, only the credentials were missing.
56. Remove those lines and `php artisan config:clear` again so you're back to a clean state.

## 8. Refunds (admin)

57. As **admin@meramusafir.com**, find a settled booking payment id (Transactions of the payer, or `payments` table).
58. `POST /api/v1/payments/{id}/refund` with the admin token.
59. ☐ Responds *"Refunded."*
60. ☐ The booking becomes **cancelled / refunded**.
61. ☐ **The package's spots come back** and the traveller is removed from the departure trip. *(This is the bug this phase fixed — worth confirming.)*
62. ☐ In the payer's Transactions the row shows **Refunded**, struck through, and no longer counts toward Total paid.

## 9. Receipt visibility

63. ☐ The payer can open their own payment.
64. ☐ The **selling agency** can open a payment for its own package.
65. ☐ Any other user gets **404** — not 403, which would confirm the payment exists.

## 10. Regressions to spot-check

66. ☐ Cancelling a booking the normal way still returns the seats.
67. ☐ A cancelled booking cannot then be approved.
68. ☐ Nothing on the packages, trips or community pages broke.

---

## Known gaps (not bugs)

- **No auto-renew.** Subscriptions are charged per term; true recurring billing needs a stored payment token from a real gateway.
- **The traveller isn't notified when approved.** They see it in My Bookings. A push/email nudge before the 48 hours run out is worth adding — right now an approved booking can quietly lapse.
- **No agency payouts.** `net_amount` is recorded per payment, but paying agencies out is a settlement process, not an API call.
- **JazzCash/EasyPaisa refunds** return *"processed from the merchant portal"* — that's accurate, their API refund tier isn't available to a new merchant.
- **No PDF receipt.** The receipt page prints fine.

## If something looks broken

Hard-reload first (**Cmd+Shift+R**). A stale bundle has been the cause more often than a real bug in this project.
