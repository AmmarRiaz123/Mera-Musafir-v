<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\BlockedUser;
use App\Models\CommunityPost;
use App\Models\PostComment;
use App\Services\FeedService;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request, CommunityPost $post)
    {
        $query = $post->comments()->with('author')->where('is_hidden', false)->oldest();

        // Hide comments from anyone in a block relationship with the viewer.
        if ($viewer = auth('sanctum')->user()) {
            $blockedIds = BlockedUser::relatedIds($viewer->id);
            if ($blockedIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $blockedIds);
            }
        }

        return CommentResource::collection($query->get())
            ->additional(['message' => 'Comments retrieved']);
    }

    public function store(StoreCommentRequest $request, CommunityPost $post)
    {
        if ($post->is_hidden) {
            return response()->json(['message' => 'This post is no longer available'], 404);
        }

        $data = $request->validated();

        $comment = PostComment::create([
            'community_post_id' => $post->id,
            'user_id'           => $request->user()->id,
            'body'              => $data['body'] ?? null,
            'media_url'         => $data['media_url'] ?? null,
            'media_type'        => $data['media_type'] ?? null,
        ]);

        $this->feed->syncCounters($post);
        $comment->load('author');

        return response()->json([
            'message'        => 'Comment added',
            'data'           => new CommentResource($comment),
            'comments_count' => (int) $post->fresh()->comments_count,
        ], 201);
    }

    public function destroy(Request $request, CommunityPost $post, PostComment $comment)
    {
        if ($comment->community_post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found on this post'], 404);
        }

        $this->authorize('delete', $comment);

        $comment->delete();
        $this->feed->syncCounters($post);

        return response()->json([
            'message'        => 'Comment deleted',
            'comments_count' => (int) $post->fresh()->comments_count,
        ]);
    }
}
