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
            'status'         => $this->status,
            'creator'        => new UserResource($this->whenLoaded('creator')),
            'destination'    => new DestinationResource($this->whenLoaded('destination')),
            'members'        => $this->whenLoaded('joinedMembers', fn () => $this->membersWithPartySize()),
            'members_count'  => $this->current_count,
            // Whether the current viewer was removed by the host — the join
            // button/dialog uses it to say "request to rejoin" instead of
            // promising an instant join the backend won't grant.
            'viewer_removed' => $this->viewerWasRemoved(),
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }

    /**
     * On a package departure one account can hold several seats, so the member
     * list must say so — otherwise "6/12 filled" next to 3 names looks wrong.
     * A single query per trip; members are only ever loaded for a single trip.
     */
    private function viewerWasRemoved(): bool
    {
        $userId = auth('sanctum')->id();

        return $userId
            ? \App\Models\TripMember::where('trip_id', $this->id)
                ->where('user_id', $userId)
                ->where('status', 'removed')
                ->exists()
            : false;
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