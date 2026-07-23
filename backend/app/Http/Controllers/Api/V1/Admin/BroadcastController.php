<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Send an announcement to everyone, or a segment, as a real notification —
 * it lands in the bell and lights the sidebar dot like any other. Reuses the
 * notification pipeline rather than inventing a second delivery path.
 */
class BroadcastController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function send(Request $request)
    {
        $validated = $request->validate([
            'segment' => 'required|in:all,travelers,agencies',
            'title'   => 'required|string|max:120',
            'body'    => 'nullable|string|max:300',
            'link'    => 'nullable|string|max:200',
        ]);

        $recipients = User::query()
            ->when($validated['segment'] === 'travelers', fn ($q) => $q->where('type', 'traveler'))
            ->when($validated['segment'] === 'agencies', fn ($q) => $q->where('type', 'agency'))
            ->where('is_blocked', false);

        $sent = 0;

        // Chunked so a broadcast to thousands doesn't hold the whole table in
        // memory. Each row is a normal notification, so it broadcasts live too.
        $recipients->chunkById(200, function ($users) use ($validated, &$sent) {
            foreach ($users as $user) {
                $this->notifications->push(
                    recipient: $user,
                    type: 'announcement',
                    copy: [
                        'title' => $validated['title'],
                        'body'  => $validated['body'] ?? null,
                        'link'  => $validated['link'] ?? null,
                    ],
                );
                $sent++;
            }
        });

        return response()->json([
            'message' => "Announcement sent to {$sent} " . ($sent === 1 ? 'person' : 'people') . '.',
            'sent'    => $sent,
        ]);
    }
}
