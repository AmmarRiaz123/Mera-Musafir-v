# Phase 9 — Community Feed

**Status:** Complete and verified end to end
**Goal (from master plan):** *"Living community that keeps users engaged between trips"*

---

## 1. What was built

| Master-plan requirement | Delivered |
|---|---|
| Community posts table, CRUD | `community_posts` + full CRUD with Policy-based authorisation |
| Like system | `post_likes` with a unique constraint; toggle endpoint |
| Comment system | `post_comments` (soft-deleted), list/add/delete |
| Post-to-destination association | Optional `destination_id`; posts render a clickable place tag |
| Moderation flag | `is_flagged` / `is_hidden` on posts and comments; posts reportable via the existing Report system |
| Feed algorithm | `FeedService` — recency + destination relevance + followed agencies/people + engagement |
| Community feed page (infinite scroll) | `CommunityPage.vue`, scroll-triggered pagination |
| Create post (text + image upload) | Inline composer using the Phase 8.5 `ImageUpload` pipeline |
| Post card (image, text, destination tag, like + comment counts) | `PostCard.vue` |
| Destination-specific feed | "From the community" section on the destination detail page |
| Agency post type (styled differently) | Agency posts get a gradient background, an **AGENCY** badge and link to the storefront |

---

## 2. Architecture decisions

**Scoring runs in SQL, not PHP.**
The feed ranks by a computed score. Doing that in PHP would mean loading *every* post to sort them before paginating. The score is built as a SQL expression so MySQL can rank and paginate in one query.

**Score composition** (`app/Services/FeedService.php`):

| Signal | Weight | Rationale |
|---|---|---|
| Recency | 60 → 0 over ~14 days | Base score. A great old post eventually yields to fresh ones |
| Followed author | +40 | People you chose to follow are the strongest relevance signal |
| Followed agency | +35 | Directly from the brief |
| Destination you've travelled to | +25 | Derived from *joined* trip membership, not stated preferences — actions beat claims |
| Engagement | +2 per like/comment, **capped at 20** | Rewards good posts without letting one viral post dominate forever |

**Counters are denormalised but derived, never incremented.**
`likes_count` / `comments_count` live on the post so the feed can sort on them without a `COUNT` per row. They're recomputed from the underlying rows after every change (`FeedService::syncCounters`) rather than `increment()`-ed. This is deliberate: the agency follower counter used `increment/decrement` and drifted **negative** in Phase 8.5. Deriving the value makes drift structurally impossible.

**Blocked users are filtered at read time**, in both the feed and comment lists, reusing `BlockedUser::relatedIds`.

**Authorisation via Policies**, per the project standards — `CommunityPostPolicy` (author-only edit/delete) and `PostCommentPolicy` (comment author *or* post author can delete, so people can moderate their own threads). Both grant admins blanket access via `before()`. This required adding the `AuthorizesRequests` trait to the base controller, which Laravel 11 ships empty.

---

## 3. Files

**Backend — new**
```
database/migrations/2026_07_22_000100_create_community_tables.php
app/Models/CommunityPost.php, PostLike.php, PostComment.php
app/Services/FeedService.php
app/Policies/CommunityPostPolicy.php, PostCommentPolicy.php
app/Http/Requests/Community/StorePostRequest.php, UpdatePostRequest.php, StoreCommentRequest.php
app/Http/Resources/PostResource.php, CommentResource.php
app/Http/Controllers/Api/V1/CommunityPostController.php, PostCommentController.php
database/seeders/CommunityPostSeeder.php
```

**Backend — changed**
```
app/Http/Controllers/Controller.php   → added AuthorizesRequests
app/Http/Controllers/Api/V1/SafetyController.php → reports now accept type "post"
routes/api.php, database/seeders/DatabaseSeeder.php
```

**Frontend — new**
```
src/stores/communityStore.js
src/components/PostCard.vue
src/pages/community/CommunityPage.vue
```

**Frontend — changed**
```
src/router/routes.js                            → /community
src/layouts/MainLayout.vue                      → Community nav (travellers + agencies)
src/pages/destinations/DestinationDetailPage.vue → destination feed + removed a dead
                                                   via.placeholder.com image reference
```

---

## 4. API

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/community/posts` | optional | Ranked feed. `?destination_id=`, `?type=`, `?user_id=`, `?page=`, `?per_page=` |
| GET | `/api/v1/community/posts/{post}` | optional | 404 if hidden |
| POST | `/api/v1/community/posts` | required | `body` (≤2000), `type`, `destination_id`, `image` |
| PUT | `/api/v1/community/posts/{post}` | author | |
| DELETE | `/api/v1/community/posts/{post}` | author | Soft delete |
| POST | `/api/v1/community/posts/{post}/like` | required | Toggles; returns `is_liked` + `likes_count` |
| GET | `/api/v1/community/posts/{post}/comments` | optional | |
| POST | `/api/v1/community/posts/{post}/comments` | required | `body` (≤1000) |
| DELETE | `/api/v1/community/posts/{post}/comments/{comment}` | comment or post author | |

Post types: `story` · `tip` · `review` · `announcement`.

---

## 5. What I verified

Backend (curl) and UI (headless Chromium), all passing with zero console errors:

- Post creation as traveller and as agency; agency posts return `is_agency: true` with the agency name
- Like toggle: `on → off → on`, count stays correct, double-tap can't double-like
- Comment add/list, `comments_count` syncs
- **Authorisation**: a non-author deleting someone else's post → `403`
- **Validation**: empty body → `422` with the custom message
- Anonymous browsing works; `is_liked` is `false` for guests
- Destination filter returns only that destination's posts
- Reporting a post → `201` through the existing Report system
- **Infinite scroll**: 10 posts → 15 on scroll
- Type filter (Tips) returns only `Tip` chips
- Image upload through the composer, and the image renders in the feed
- **Ranking**: following an agency moved its post from **position 4 → position 1**

---

## 6. Known limitations / deliberate scope cuts

1. **No realtime.** New posts appear on refresh or navigation, not pushed over Reverb. Trip chat is realtime; the feed is not. Worth adding if engagement justifies it.
2. **Moderation is data-only.** `is_hidden` / `is_flagged` exist and are respected everywhere, but there's no admin UI to set them — that belongs to Phase 11 (Admin Panel). Reports do land in the existing reports table.
3. **No edit UI.** `PUT` works and is policy-protected, but the frontend only offers delete.
4. **Feed isn't cached.** Deliberate — the Phase 8.5 matching cache served stale results for an hour after a trip changed. Ranking is cheap here; caching can come with proper invalidation later.
5. **`per_page` is unbounded**, so a client could request a huge page. Fine for now; worth clamping before public launch.
6. Seeded post text is illustrative demo copy, not real user content.

---

## 7. Testing checklist

Log in as **test6@test.com** (or any `test1–test6`, password `password`). Agency: **agency1@test.com**.

### Feed basics
1. Open **Community** in the sidebar → feed loads with posts, newest/most relevant first.
2. Posts show author, avatar, time ago, type chip (Story/Tip/Review), and like/comment counts.
3. Posts tagged with a place show a purple **📍 destination** link → clicking it opens that destination.
4. Scroll to the bottom → more posts load automatically; at the end you see *"You're all caught up."*
5. Click **Tips** filter → only Tip posts remain. Click **All** → everything returns.

### Posting
6. Click the composer → it expands to show type, destination and photo fields.
7. Post text only → appears at the top of the feed immediately.
8. Post with a **photo** (drag-drop or click) → preview shows before posting, image renders in the feed.
9. Tag a **destination** and post → the tag appears on the card, and the post shows on that destination's page.
10. Try posting an empty body → the Post button stays disabled.
11. Paste >2000 characters → the field stops at the limit.

### Likes & comments
12. Click the heart → it fills, count increments. Click again → it unfills, count decrements.
13. Reload the page → your like state persisted.
14. Click the comment icon → comments expand.
15. Add a comment → it appears instantly and the count increments.
16. Delete your own comment (✕) → it disappears and the count decrements.
17. On **your own** post, you can delete comments left by others.

### Permissions & safety
18. On someone else's post, the ⋮ menu shows **Report post** (not Delete).
19. On your own post, ⋮ shows **Delete post** → confirm dialog → post disappears.
20. Report a post → "Report submitted" confirmation.
21. Block a user (People → ⋮ → Block), then reload Community → their posts are gone from your feed.
22. Log out and open `/#/community` → you can read the feed, but there's no composer and liking prompts you to log in.

### Agency behaviour
23. Log in as **agency1@test.com** → sidebar has **Community**.
24. Post as the agency → the card has a tinted background, an **AGENCY** badge, and the business name.
25. Click the agency name on the post → opens the storefront (not a user profile).

### Destination feed
26. Open **Explore → Hunza Valley** → scroll to **"From the community"** → only Hunza-tagged posts appear.
27. Like and comment from the destination page → both work inline.
28. Open a destination with no posts → friendly empty state with a link to Community.

### Ranking (optional, the interesting one)
29. As **test4**, note where the agency's post sits in the feed.
30. Follow **Hunza Explorers** (Agencies → Follow), reload Community → its post ranks noticeably higher.

---

## 8. Reproducing the data

```bash
cd backend
php artisan migrate
php artisan db:seed --class=CommunityPostSeeder   # 12 demo posts, idempotent
```

`CommunityPostSeeder` is in the default `DatabaseSeeder` chain, so `migrate:fresh --seed`
recreates everything. It reuses images already downloaded by `ImageSeeder` — no new
network calls — and skips posts whose body text already exists, so re-running is safe.
