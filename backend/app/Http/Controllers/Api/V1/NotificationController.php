<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /notifications — the recipient's feed, newest first. */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data'     => NotificationResource::collection($notifications),
            'unread'   => $this->unreadByCategory($request),
        ]);
    }

    /**
     * GET /notifications/unread — just the counts, for the sidebar dots.
     * Cheap enough to poll and small enough to ship on every page.
     */
    public function unread(Request $request)
    {
        return response()->json(['unread' => $this->unreadByCategory($request)]);
    }

    /** POST /notifications/read — mark some read, or a whole category. */
    public function markRead(Request $request)
    {
        $validated = $request->validate([
            'ids'      => 'nullable|array',
            'ids.*'    => 'integer',
            'category' => 'nullable|string',
        ]);

        $query = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at');

        if (!empty($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        } elseif (!empty($validated['category'])) {
            // Clearing a sidebar dot: everything in that category.
            $types = array_keys(array_filter(
                Notification::CATEGORY,
                fn ($cat) => $cat === $validated['category'],
            ));
            $query->whereIn('type', $types);
        }
        // Neither given → mark everything read (the "mark all" button).

        $query->update(['read_at' => now()]);

        return response()->json(['unread' => $this->unreadByCategory($request)]);
    }

    /**
     * Unread counts keyed by sidebar category, plus a grand total for the bell.
     * Zero categories are omitted, so the client can treat any key as "has a dot".
     */
    private function unreadByCategory(Request $request): array
    {
        $rows = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->selectRaw('type, COUNT(*) as n')
            ->groupBy('type')
            ->pluck('n', 'type');

        $byCategory = [];
        $total = 0;

        foreach ($rows as $type => $n) {
            $category = Notification::CATEGORY[$type] ?? 'other';
            $byCategory[$category] = ($byCategory[$category] ?? 0) + $n;
            $total += $n;
        }

        $byCategory['total'] = $total;

        return $byCategory;
    }
}
