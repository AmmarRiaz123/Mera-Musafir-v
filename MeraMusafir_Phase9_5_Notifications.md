# Phase 9.5 — Notifications

A single notification system for travellers and agencies: a live bell feed, a red dot on the sidebar tab where something happened, and eight event types each with its own visual identity.

---

## What notifies whom

| type | recipient | trigger | icon · accent | sidebar dot |
|---|---|---|---|---|
| `follow` | the followed user | someone follows you | person · teal | Profile |
| `comment` | post author | comment on your post | chat · amber | Community |
| `like` | post author | like on your post | heart · pink | Community |
| `message` | DM recipient | new direct message | chat · violet | Messages |
| `booking_request` | agency owner | traveller requests a package | luggage · orange | Bookings |
| `booking_approved` | traveller | agency approves — pay now | shield · green | My Bookings |
| `booking_paid` | agency owner | traveller pays | payments · green | Bookings |
| `trip_join` | trip host | someone joins/requests your trip | groups · teal | My Trips |

Message requests keep their own accept/ignore section at the top of the bell — that lifecycle was already built and tested, so it wasn't folded in.

---

## Design decisions

**One table, denormalised.** A notification snapshots the actor (name, avatar) and its copy (title, body, link) onto the row at creation. The feed renders from one row without joining four tables, and a notification still reads correctly after its subject is deleted. `subject_type/id` is kept too, polymorphic, for anything that later wants the live object.

**Category lives next to the types.** `Notification::CATEGORY` maps each type to a sidebar tab, in one place the backend and frontend both read from — so a follow can't show up under Community on one side and Profile on the other.

**Never notify yourself.** `NotificationService::push()` drops the notification when the actor is the recipient — liking your own post, paying yourself. Nothing to tell you that you already know.

**Counts are cheap; the feed is lazy.** `/notifications/unread` returns only per-category tallies (one grouped query) and is what the sidebar dots and bell badge poll. The full list is fetched only when the bell opens.

**Live, with a reliable floor.** New notifications broadcast on the recipient's existing `App.Models.User.{id}` private channel — the same one message events use — as `ShouldBroadcastNow`, so no queue worker. But `window.Echo` is a singleton the chat stores recreate, so the store doesn't depend on it: a 45-second poll of the unread counts keeps the badges honest regardless, and the socket layers on top when it's available. Worst case without a socket is a dot that appears within 45s instead of instantly.

**Opening the tab clears the dot.** Tapping a sidebar tab marks that whole category read, optimistically (the dot vanishes at once) with the server call reconciling. The coloured left-edge on a feed item is the "unread" signal; read items lose it.

---

## Files

**Backend**
- `notifications` table — `user_id`, `actor_id`, `type`, nullable polymorphic `subject`, `data` (json snapshot), `read_at`; indexed on `(user_id, read_at)` and `(user_id, type)`
- `Notification` model with the `CATEGORY` map
- `NotificationService::push()` — the one method the app calls
- `NotificationCreated` event — broadcasts on the user channel
- `NotificationController` — `index`, `unread`, `read` (ids / category / all)
- `NotificationResource`
- Triggers wired into: follow (`UserController`), like + comment (`CommunityPostController`, `PostCommentController`), DM (`ConversationController`), booking request + approve (`PackageController`), booking paid (`PaymentService`), trip join (`TripController`)

**Frontend**
- `notificationStore` — extended: feed `items`, per-category `unread`, poll + live `subscribe`, optimistic `markCategoryRead` / `markAllRead`, `ingest` for socket arrivals
- `NotificationBell.vue` — the dropdown; each type its own badge colour and left-edge
- `MainLayout` — bell swapped in, red dots (`.nav-dot`) on Community / Profile / Messages / My Trips / My Bookings / agency Bookings, lifecycle started on mount and stopped on logout

---

## Not done

- **No digest / email.** In-app only. An approved-booking email would matter most, given the 48-hour pay window.
- **No preferences.** Everyone gets everything; there's no per-type mute yet.
- **The bell shows the latest 50.** No infinite scroll or "load older" — a full notifications page is the natural next step if volume grows.
