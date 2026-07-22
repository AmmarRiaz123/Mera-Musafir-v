<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StorePostRequest;
use App\Http\Requests\Community\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\CommunityPost;
use App\Models\BlockedUser;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\PostLike;
use App\Support\ImageUrl;
use App\Services\FeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $data = $request->validated();

        // media_url/media_type mirror the gallery's first item so single-media
        // consumers (share cards, previews) keep working unchanged.
        if (!empty($data['gallery'])) {
            $data['media_url']  = $data['gallery'][0]['url'];
            $data['media_type'] = $data['gallery'][0]['type'];
        }

        $post = CommunityPost::create([
            ...$data,
            'user_id' => $request->user()->id,
            'type'    => $data['type'] ?? 'story',
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

    /**
     * POST /community/posts/{post}/share — send this post into one or more DMs.
     *
     * The preview is snapshotted into the message metadata so the chat can render
     * a card without an extra lookup, and so a later edit to the post doesn't
     * silently rewrite what someone was sent.
     */
    public function share(Request $request, CommunityPost $post)
    {
        $validated = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1', 'max:10'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'note'       => ['nullable', 'string', 'max:500'],
        ], [
            'user_ids.required' => 'Choose at least one person to send this to.',
            'user_ids.max'      => 'You can send to 10 people at a time.',
        ]);

        if ($post->is_hidden) {
            return response()->json(['message' => 'This post is no longer available'], 404);
        }

        $senderId = $request->user()->id;
        $post->loadMissing(['author', 'destination']);

        $metadata = [
            'post_id'      => $post->id,
            'post_type'    => $post->type,
            'excerpt'      => Str::limit($post->body, 140),
            'media_url'    => ImageUrl::resolve($post->media_url),
            'media_type'   => $post->media_type,
            'author_id'    => $post->user_id,
            'author_name'  => $post->author?->name,
            'author_avatar'=> ImageUrl::resolve($post->author?->avatar),
            'destination'  => $post->destination?->name,
            'media_count'  => count($post->gallery ?: ($post->media_url ? [1] : [])),
            'audio'        => $post->audio ? [
                'title'  => $post->audio['title'] ?? null,
                'artist' => $post->audio['artist'] ?? null,
                'cover'  => $post->audio['cover'] ?? null,
            ] : null,
        ];

        $sent    = 0;
        $skipped = [];

        foreach (array_unique($validated['user_ids']) as $recipientId) {
            $recipientId = (int) $recipientId;

            if ($recipientId === $senderId) {
                continue;
            }

            // Sender <-> recipient block: no conversation at all.
            if (BlockedUser::blockExistsBetween($senderId, $recipientId)) {
                $skipped[] = $recipientId;
                continue;
            }

            $conversation = Conversation::findOrCreateBetween($senderId, $recipientId);

            ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $senderId,
                'body'            => $validated['note'] ?: 'Shared a post',
                'type'            => 'post_share',
                'metadata'        => $metadata,
            ]);

            $conversation->update(['last_message_at' => now()]);
            $sent++;
        }

        return response()->json([
            'message' => $sent === 1 ? 'Post sent' : "Post sent to {$sent} people",
            'sent'    => $sent,
            'skipped' => count($skipped),
        ]);
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
