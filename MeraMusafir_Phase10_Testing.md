# Phase 10 — Testing Checklist

**Before you start.** All test data was reset after my own testing: no payments, no subscriptions, agencies back to their original tiers (Hunza Explorers = Pro, Karakoram = Basic), and every package's seat count reconciled against its live bookings. So you're starting clean.

**Accounts:** `test1@test.com` … `test6@test.com`, `agency1@test.com` (Hunza Explorers), `agency2@test.com` (Karakoram), `admin@meramusafir.com` — all password `password`.

**Expect one payment method: "Test payment".** That's correct — JazzCash, EasyPaisa and Card stay hidden until their credentials exist. See §7 for how to prove that's deliberate.

---

## 1. Booking → payment (the main path)

1. As **test1**, open a package (`/packages` → any) and book 2 travellers.
2. ☐ You land on **`/checkout`** immediately — not on "awaiting agency confirmation".
3. ☐ The summary names the package, the traveller count, and a total = price × travellers.
4. ☐ "Test payment" is **already selected** (it's the only option).
5. ☐ A note explains test mode and that real gateways switch on later.
6. Click **Pay**.
7. ☐ A receipt replaces the form: reference (`MM…`), package, method, date, total.
8. ☐ Message reads *"Your seat is booked and you've been added to the trip's group chat."*

## 2. The payment actually did something

9. ☐ **My Trips → My Packages**: the booking shows **confirmed**, not pending.
10. ☐ The package's "spots left" dropped by **your traveller count**, not by 1.
11. ☐ **My Trips → Trips I Joined**: the departure trip is there.
12. ☐ Open its **group chat** — you're a member and can post.
13. ☐ As **agency1/agency2** (whoever owns that package), Bookings shows it as confirmed.

## 3. Declines

14. Book a second package as **test1**, reach checkout, click **"Simulate a declined payment"**.
15. ☐ A red box says *"The bank declined this payment."* — no receipt.
16. ☐ The booking stays **pending / unpaid**; no seat is taken beyond the hold.
17. Now click **Pay** on the same screen.
18. ☐ It succeeds, and you get a receipt.
19. ☐ In **Transactions**, both attempts appear — the failed one struck through, the successful one paid.

## 4. Guards (each should refuse)

20. ☐ Pay for the same booking twice → *"This booking is already paid."*
21. ☐ Open `/checkout?type=booking&id=<a booking of test5's>` as test1 → refuses, nothing charged.
22. ☐ A traveller (not admin) cannot refund — no refund control is exposed to them.

## 5. Transactions

23. ☐ `/transactions` (sidebar → **Transactions**) lists every attempt, newest first.
24. ☐ **Total paid** at top counts only settled payments — not failed, not refunded.
25. ☐ A settled booking shows *"incl. PKR … platform fee"* = **5%** of the amount.
26. ☐ Each row shows its reference, method and date.
27. ☐ A brand-new account sees the empty state, not a blank page.

## 6. Agency subscriptions

28. As **agency2** (Basic), open **Plan & billing** in the sidebar.
29. ☐ Banner reads *"You're on the free Basic plan."*
30. ☐ Two plans: Pro **PKR 2,999/mo**, Premium **PKR 7,999/mo**.
31. Switch to **Yearly**.
32. ☐ Prices become 29,990 and 79,990 — and each shows the saving (5,998 / 15,998). That's ten months for twelve.
33. Click **Go Pro** → you land on checkout for the Pro plan, billed yearly.
34. ☐ Pay. Receipt says *"Your plan is active — the new limits apply straight away."*
35. ☐ Back on **Plan & billing**: *"You're on Pro until \<one year out\>."*
36. ☐ **Billing history** shows one row: Pro · Yearly · Active · PKR 29,990.
37. ☐ The sidebar badge under the agency name now reads **PRO**.
38. ☐ In **Transactions**, the subscription payment shows **no platform fee** — commission applies to bookings, not to what an agency pays us.

**Renewal extends, it doesn't reset:**

39. Buy **Pro monthly** again straight away.
40. ☐ The expiry moves **one month past the existing date**, not one month from today.

## 7. Prove the hidden gateways are deliberate

41. ☐ At checkout, only "Test payment" appears.
42. In `backend/.env` add any placeholder values:
    `JAZZCASH_MERCHANT_ID=x`, `JAZZCASH_PASSWORD=x`, `JAZZCASH_INTEGRITY_SALT=x`
43. Run `php artisan config:clear`, reload checkout.
44. ☐ **JazzCash now appears** in the list — the wiring is real, only the credentials were missing.
45. Remove those lines and `php artisan config:clear` again so you're back to a clean state.

## 8. Refunds (admin)

46. As **admin@meramusafir.com**, find a settled booking payment id (Transactions of the payer, or `payments` table).
47. `POST /api/v1/payments/{id}/refund` with the admin token.
48. ☐ Responds *"Refunded."*
49. ☐ The booking becomes **cancelled / refunded**.
50. ☐ **The package's spots come back** and the traveller is removed from the departure trip. *(This is the bug this phase fixed — worth confirming.)*
51. ☐ In the payer's Transactions the row shows **Refunded**, struck through, and no longer counts toward Total paid.

## 9. Receipt visibility

52. ☐ The payer can open their own payment.
53. ☐ The **selling agency** can open a payment for its own package.
54. ☐ Any other user gets **404** — not 403, which would confirm the payment exists.

## 10. Regressions to spot-check

55. ☐ Agency **manually confirming** a booking still seats the traveller on the trip (that logic moved into a service).
56. ☐ Cancelling a booking the normal way still returns the seats.
57. ☐ Nothing on the packages, trips or community pages broke.

---

## Known gaps (not bugs)

- **No auto-renew.** Subscriptions are charged per term; true recurring billing needs a stored payment token from a real gateway.
- **No agency payouts.** `net_amount` is recorded per payment, but paying agencies out is a settlement process, not an API call.
- **JazzCash/EasyPaisa refunds** return *"processed from the merchant portal"* — that's accurate, their API refund tier isn't available to a new merchant.
- **No PDF receipt.** The receipt page prints fine.

## If something looks broken

Hard-reload first (**Cmd+Shift+R**). A stale bundle has been the cause more often than a real bug in this project.
