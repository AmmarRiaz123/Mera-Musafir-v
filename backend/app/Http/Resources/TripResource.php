<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Support\ImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'cover_image'    => ImageUrl::resolve($this->cover_image),
            'start_date'     => $this->start_date->format('Y-m-d'),
            'end_date'       => $this->end_date->format('Y-m-d'),
            'max_travelers'  => $this->max_travelers,
            'current_count'  => $this->current_count,
            'spots_left'     => $this->max_travelers - $this->current_count,
            'is_full'        => $this->isFull(),
            'budget_min'     => $this->budget_min,
            'budget_max'     => $this->budget_max,
            'type'           => $this->type,
            'visibility'     => $this->visibility,
            'requires_approval' => (bool) $this->requires_approval,
            'status'         => $this->status,
            'creator'        => new UserResource($this->whenLoaded('creator')),
            'destination'    => new DestinationResource($this->whenLoaded('destination')),
            'members'        => $this->whenLoaded('joinedMembers', fn () => $this->membersWithPartySize()),
            // Pending join requests — only the host needs (or should) see them.
            'pending_members' => $this->when(
                auth('sanctum')->id() === $this->creator_id,
                fn () => $this->pendingMembers->map(fn ($u) => [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'avatar'      => \App\Support\ImageUrl::resolve($u->avatar),
                    'city'        => $u->city,
                    'is_verified' => (bool) $u->is_verified,
                    'requested_at' => $u->pivot->created_at,
                ]),
            ),
            'members_count'  => $this->current_count,
            // The viewer's own membership status ('joined'|'pending'|'removed'|
            // 'left'|null) so the join UI can show "request pending" or "request
            // to rejoin" instead of promising an instant join.
            'viewer_status'  => $this->viewerStatus(),
            'viewer_removed' => $this->viewerStatus() === 'removed',
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }

    /**
     * On a package departure one account can hold several seats, so the member
     * list must say so — otherwise "6/12 filled" next to 3 names looks wrong.
     * A single query per trip; members are only ever loaded for a single trip.
     */
    private ?string $cachedViewerStatus = null;
    private bool $viewerStatusResolved = false;

    private function viewerStatus(): ?string
    {
        if ($this->viewerStatusResolved) {
            return $this->cachedViewerStatus;
        }
        $this->viewerStatusResolved = true;

        $userId = auth('sanctum')->id();

        $this->cachedViewerStatus = $userId
            ? \App\Models\TripMember::where('trip_id', $this->id)
                ->where('user_id', $userId)
                ->value('status')
            : null;

        return $this->cachedViewerStatus;
    }

    private function membersWithPartySize()
    {
        $partySizes = $this->package_id
            ? Booking::where('agency_package_id', $this->package_id)
                ->where('status', 'confirmed')
                ->pluck('travelers_count', 'user_id')
            : collect();

        $isPackageTrip = (bool) $this->package_id;

        return $this->joinedMembers->map(function ($user) use ($partySizes, $isPackageTrip) {
            // On a package departure, a member without a booking is agency
            // staff (the host) and holds no seat — null, not 1, so the party
            // sizes add up to the seats sold.
            $partySize = $isPackageTrip
                ? ($partySizes[$user->id] ?? null)
                : 1;

            return array_merge((new UserResource($user))->resolve(), [
                'party_size' => $partySize,
                'guests'     => $partySize ? max(0, $partySize - 1) : 0,
                'role'       => $user->pivot->role,
                'is_agency'  => $user->type === 'agency',
                'joined_at'  => $user->pivot->joined_at,
            ]);
        });
    }
}