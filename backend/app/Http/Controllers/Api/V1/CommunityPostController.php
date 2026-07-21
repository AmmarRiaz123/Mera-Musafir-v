<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StorePostRequest;
use App\Http\Requests\Community\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\CommunityPost;
use App\Models\PostLike;
use App\Services\FeedService;
use Illuminate\Http\Request;

class CommunityPostController extends Controller
{
    public function __construct(private FeedService $feed) {}

    /** GET /community/posts — the ranked feed. */
    public function index(Request $request)
    {
        $posts = $this->feed->feed(
            auth('sanctum')->user(),
            $request->only(['destination_id', 'type', 'user_id']),
            (int) ($request->per_page ?? 10),
        );

        return PostResource::collection($posts)->additional(['message' => 'Feed retrieved']);
    }

    public function show(CommunityPost $post)
    {
        if ($post->is_hidden) {
            return response()->json(['message' => 'This post is no longer available'], 404);
        }

        $post->load(['author', 'destination']);

        return response()->json(['data' => new PostResource($post)]);
    }

    public function store(StorePostRequest $request)
    {
        $post = CommunityPost::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'type'    => $request->validated()['type'] ?? 'story',
        ]);

        $post->load(['author', 'destination']);

        return response()->json([
            'message' => 'Post published',
            'data'    => new PostResource($post),
        ], 201);
    }

    public function update(UpdatePostRequest $request, CommunityPost $post)
    {
        $this->authorize('update', $post);

        $post->update($request->validated());
        $post->load(['author', 'destination']);

        return response()->json([
            'message' => 'Post updated',
            'data'    => new PostResource($post),
        ]);
    }

    public function destroy(Request $request, CommunityPost $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }

    /** POST /community/posts/{post}/like — toggles. */
    public function toggleLike(Request $request, CommunityPost $post)
    {
        $userId = $request->user()->id;

        $existing = PostLike::where('community_post_id', $post->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            // Idempotent: a double-tap can't create two likes.
            PostLike::firstOrCreate(['community_post_id' => $post->id, 'user_id' => $userId]);
            $liked = true;
        }

        // Derive the counter from the rows rather than incrementing, so it can
        // never drift out of sync (or go negative) after a stale toggle.
        $this->feed->syncCounters($post);

        return response()->json([
            'is_liked'    => $liked,
            'likes_count' => (int) $post->fresh()->likes_count,
        ]);
    }
}
