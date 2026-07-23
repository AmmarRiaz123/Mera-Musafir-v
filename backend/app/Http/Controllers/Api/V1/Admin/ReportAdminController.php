<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgencyPackage;
use App\Models\CommunityPost;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class ReportAdminController extends Controller
{
    /** GET /admin/reports?status= (defaults to the open queue). */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $reports = Report::with('reporter:id,name,avatar')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $reports->through(fn (Report $r) => [
                'id'          => $r->id,
                'reason'      => $r->reason,
                'description' => $r->description,
                'status'      => $r->status,
                'admin_note'  => $r->admin_note,
                'created_at'  => $r->created_at->toDateTimeString(),
                'reporter'    => $r->reporter ? [
                    'id'     => $r->reporter->id,
                    'name'   => $r->reporter->name,
                    'avatar' => ImageUrl::resolve($r->reporter->avatar),
                ] : null,
                // The thing reported, resolved to something a moderator can open
                // and judge — a person, a post, a package.
                'subject'     => $this->describeSubject($r),
                // The person behind it, so a moderator can suspend them straight
                // from the report without hunting them down in the users table.
                'offender'    => $this->offender($r),
            ])->items(),
            'meta' => [
                'total'        => $reports->total(),
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
            ],
        ]);
    }

    /**
     * POST /admin/reports/{report}/resolve — action or dismiss, with a note.
     *
     * "Actioned" records that something was done (a suspend, a takedown, done
     * separately); "dismissed" that the report didn't hold up. Either way it
     * leaves the open queue.
     */
    public function resolve(Request $request, Report $report)
    {
        $validated = $request->validate([
            'status'  => 'required|in:actioned,dismissed,reviewed',
            'note'    => 'nullable|string|max:1000',
            // When true, suspend the person behind the report as part of
            // actioning it — the whole point of a moderation queue.
            'suspend' => 'nullable|boolean',
        ]);

        if (!empty($validated['suspend'])) {
            $offender = $this->offenderModel($report);

            if (!$offender) {
                return response()->json(['message' => 'That account is no longer here to suspend.'], 422);
            }
            if ($offender->hasRole('admin')) {
                return response()->json(['message' => "You can't suspend an admin."], 422);
            }

            $offender->update(['is_blocked' => true]);
        }

        $report->update([
            'status'      => $validated['status'],
            'admin_note'  => $validated['note'] ?? $report->admin_note,
            'actioned_at' => now(),
        ]);

        // Close the loop with whoever flagged it — a report that vanishes
        // silently makes people stop bothering. Deliberately vague about what
        // was done: the reported user's outcome is none of the reporter's
        // business, and 'reviewed' is an internal state that isn't an outcome.
        if ($report->reporter && $validated['status'] !== 'reviewed') {
            $actioned = $validated['status'] === 'actioned';
            app(\App\Services\NotificationService::class)->push(
                recipient: $report->reporter,
                type: 'report_reviewed',
                copy: [
                    'title' => $actioned
                        ? 'We took action on something you reported'
                        : 'We reviewed your report',
                    'body'  => $actioned
                        ? 'Thanks for flagging it — you help keep Mera Musafir safe.'
                        : "We didn't find a violation this time, but thanks for looking out.",
                ],
            );
        }

        return response()->json(['message' => 'Report updated.']);
    }

    /**
     * The user behind a report, resolved through whatever was flagged: the
     * reported user directly, a post's author, a package's agency owner, a
     * message's sender. Null if that account is gone.
     */
    private function offenderModel(Report $report): ?User
    {
        $type = class_basename($report->reported_type);

        return match ($type) {
            'User'          => User::find($report->reported_id),
            'CommunityPost' => CommunityPost::find($report->reported_id)?->author,
            'AgencyPackage' => AgencyPackage::find($report->reported_id)?->agency?->user,
            'Message'       => Message::find($report->reported_id)?->sender,
            default         => null,
        };
    }

    /** The offender flattened for the list — name, current status, whether they can be suspended. */
    private function offender(Report $report): ?array
    {
        $user = $this->offenderModel($report);

        if (!$user) {
            return null;
        }

        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'is_suspended' => (bool) $user->is_blocked,
            'is_admin'     => $user->hasRole('admin'),
        ];
    }

    private function describeSubject(Report $report): array
    {
        $type = class_basename($report->reported_type);

        return match ($type) {
            'User' => (function () use ($report) {
                $u = User::find($report->reported_id);
                return [
                    'kind'  => 'user',
                    'label' => $u?->name ?? 'Deleted user',
                    'link'  => $u ? '/profile/' . $u->id : null,
                    'gone'  => !$u,
                ];
            })(),
            'CommunityPost' => (function () use ($report) {
                $post = CommunityPost::find($report->reported_id);
                return [
                    'kind'  => 'post',
                    'label' => $post ? \Illuminate\Support\Str::limit($post->body ?: 'Post', 50) : 'Deleted post',
                    'link'  => $post ? '/community?post=' . $post->id : null,
                    'gone'  => !$post,
                ];
            })(),
            'AgencyPackage' => (function () use ($report) {
                $pkg = AgencyPackage::find($report->reported_id);
                return [
                    'kind'  => 'package',
                    'label' => $pkg?->title ?? 'Deleted package',
                    'link'  => $pkg ? '/packages/' . $pkg->slug : null,
                    'gone'  => !$pkg,
                ];
            })(),
            default => ['kind' => 'message', 'label' => 'A private message', 'link' => null, 'gone' => false],
        };
    }
}
