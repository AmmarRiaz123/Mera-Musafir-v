# Phase 10 — Payments

**Goal (master plan):** real money flows through the platform. User books a package → pays → agency receives, minus the platform's cut.

**What actually shipped:** the entire payment system — schema, gateway layer, commission, subscriptions, refunds, receipts, history — with a sandbox gateway that settles locally so every path is exercisable today, and the three real providers written to their published specs behind the same interface.

---

## The credentials problem, and what was done about it

JazzCash and EasyPaisa only issue merchant credentials to a **registered Pakistani business**, and their sandboxes are gated behind the same registration. Stripe's test keys are self-serve but still need an account. None of those exist yet.

Building against that reality had two options: stub payments and leave the whole flow untested until the business is registered, or build the real architecture and put a working driver behind it. This took the second route.

- `SandboxGateway` implements the same contract as the real three and settles inline. It refuses to load in production (`!app()->isProduction()`), so it cannot escape into a live deployment.
- `JazzCashGateway`, `EasyPaisaGateway` and `StripeGateway` are written to their actual specs — JazzCash's salt-prefixed HMAC-SHA256 over sorted `pp_*` fields, EasyPaisa's AES-128-ECB request hash, Stripe's PaymentIntent + signed webhook.
- Each reports `isConfigured()` false while its credentials are missing, and `GatewayManager::available()` filters them out. **An unconfigured gateway is never offered** — showing JazzCash and then failing at the moment of payment is the worst possible dead end.

Switching a gateway on later is `.env` plus `PAYMENT_GATEWAY=`, not a code change.

---

## Schema

**`payments`** — one row per attempt, polymorphic on `payable` (a `Booking` or an `AgencySubscription`) so both share the same plumbing, receipts and history.

| column | why |
|---|---|
| `reference` | ours, unique, `MM<yymmdd><hex>` — short enough to read down a phone line |
| `gateway_reference` | theirs, once known |
| `amount` / `commission` / `net_amount` | whole rupees; PKR has no subunit in practice and the rest of the schema already stores rupees |
| `commission_rate` | **stored per payment** — changing the platform's cut must never rewrite what past agencies were charged |
| `gateway_payload` | whatever they sent, verbatim, for disputes |
| `status` | `pending → succeeded / failed`, plus `refunded` |

**`agency_subscriptions`** — tier, period, amount, status, term dates. `basic` is free and never becomes a subscription row.

---

## Architecture

```
PaymentController ──► PaymentService ──► GatewayManager ──► PaymentGateway
                            │                                 ├─ SandboxGateway   (works now)
                            │                                 ├─ JazzCashGateway  (needs merchant id)
                            ├──► BookingService                ├─ EasyPaisaGateway (needs store id)
                            │    confirm() / release()         └─ StripeGateway    (needs secret key)
                            └──► Agency tier + expiry
```

`GatewayResult` flattens three incompatible provider behaviours — settle inline, redirect the browser, answer later on a webhook — into one shape, so neither the service nor the frontend branches per provider.

**The money rules live in `PaymentService` only.** Controllers decide *who may pay for what*; how much the platform keeps, when a booking becomes confirmed, and what a settled payment does to the thing it paid for are all in one place, so the two payable types can't drift apart.

---

## Decisions worth knowing

**Commission applies to bookings, not subscriptions.** 5% of an agency booking is the platform's cut of someone else's sale. What an agency pays *us* for its own plan is already entirely ours, so its commission is 0 rather than a meaningless 5% of our own revenue.

**The agency vets before money moves.** A booking goes `pending → approved → confirmed`. The agency approves first; only then can the traveller pay; paying is what confirms the seat and puts them in the group chat.

The ordering matters because of the refund constraint above: JazzCash and EasyPaisa refunds are a manual trip to their merchant portal on a new merchant account. Taking payment up front would make every agency decline a refund somebody processes by hand. Vetting first means **no money is ever held for a booking that gets declined**.

**An approved seat expires.** Approval opens a 48-hour payment window (`BookingService::PAYMENT_WINDOW_HOURS`). Past it the booking is released and its seats go back, so a package can't sell out to people who were approved and never paid. Enforced two ways: lazily wherever bookings are read or paid for, and by `php artisan bookings:expire` for packages nobody happens to be browsing. Schedule that hourly.

**Subscriptions extend, they don't reset.** Renewing early adds the term to whatever is left rather than starting from today, so paying ahead never costs an agency the remainder of the current month.

**A pending payment is reused, not duplicated.** Someone who backs out of a gateway page and returns gets the same row, so they can't end up with two open charges for one booking. A *failed* attempt is not reused — it stays as an audit record and the retry opens a fresh row.

**Webhooks are idempotent.** Gateways retry. `PaymentService::apply()` returns early when an already-succeeded payment receives another success, so a replay cannot confirm a booking twice or extend a subscription by another month. Verified: three replays, expiry unchanged, one row.

**An unverified callback settles nothing.** Every driver checks a signature and returns `null` on mismatch. Stripe's check runs on the **raw request body** — parsing and re-encoding would change the bytes and the signature would never match.

---

## Two bugs this phase surfaced in existing code

**Refunds stranded seats.** Cancelling a booking through the controller released the package's seats, the trip's headcount and the traveller's place. The refund path set `status = 'cancelled'` directly and did none of it — a refunded package would have sold out at a number nobody could explain. Both paths now go through `BookingService::release()`.

**Paid travellers would have missed the group chat.** Seating a traveller on the departure trip lived inside the agency's `confirmBooking` controller action. Once payment became what confirms a booking, a paid traveller would have been `confirmed` but absent from the trip — the entire point of booking a package. Seating now lives in `BookingService::confirm()`, reached by a settled payment.

Both are the same shape of mistake: lifecycle logic living in a controller that a second caller later needed.

---

## API

| method | route | notes |
|---|---|---|
| `GET` | `/payments` | own history, paginated |
| `GET` | `/payments/methods` | only gateways that are actually usable |
| `POST` | `/payments/initiate` | `{type, id, gateway, simulate?}` |
| `GET` | `/payments/{payment}` | receipt — payer, selling agency, or admin only |
| `POST` | `/payments/{payment}/refund` | admin only |
| `POST` | `/payments/callback/{gateway}` | **public** — the caller is the provider; every driver verifies a signature |
| `GET` | `/subscriptions/plans` | plans + where this agency stands |
| `POST` | `/subscriptions` | reserve, pending until paid |
| `POST` | `/packages/{package}/bookings/{booking}/confirm` | agency approves; opens the payment window |
| `GET` | `/subscriptions/history` | agency's billing record |

---

## Frontend

- **`/checkout?type=&id=`** — method selection, order summary, and the receipt after settling. Preselects the only method rather than making you tap it. In sandbox it offers an explicit *"Simulate a declined payment"*, because a failure path nobody can trigger is a failure path nobody has tested.
- **`/transactions`** — history for travellers and agencies, with status colouring and the platform fee broken out on settled bookings.
- **`/subscription`** — plan comparison, monthly/yearly toggle showing the real saving, current standing, billing history.
- Booking ends on *"Request sent. You'll be able to pay once the agency approves it."* — there's nothing to pay yet.
- **My Bookings** is where a bill appears: an approved booking shows **Payment due**, a **Pay now** button and the time left, turning red under 12 hours.
- The agency dashboard says **Approve**, not Confirm, and its notice explains that the traveller then has 48 hours.

---

## What is deliberately not done

- **Recurring billing.** Subscriptions are charged per term. True auto-renew needs a stored payment token, which needs a real gateway.
- **Automatic payouts to agencies.** `net_amount` is recorded per payment, but moving money to an agency's bank is a settlement process, not an API call. Admin refunds are likewise manual for JazzCash/EasyPaisa — both refund from their merchant portals, not the API tier available to a new merchant.
- **PDF receipts.** The receipt is a page. A PDF library is a real dependency for something the browser already prints well.
- **Traveler platform fee (PKR 99–199/join).** The business plan explicitly holds this until supply justifies it.
