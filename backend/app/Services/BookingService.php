<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripMember;

/**
 * The parts of a booking's lifecycle that more than one caller needs.
 *
 * Cancelling used to live entirely in the controller, which was fine until
 * refunds also had to cancel — a refund that forgot to release the seats would
 * hold places nobody occupies, and the package would sell out at a number
 * nobody could explain.
 */
class BookingService
{
    /** How long an approved traveller has to pay before the seat goes back. */
    public const PAYMENT_WINDOW_HOURS = 48;

    /**
     * The agency vets first: approving opens a payment window but seats nobody.
     *
     * Money only changes hands once a human at the agency has said yes, so a
     * declined booking never has to be refunded — which matters while JazzCash
     * and EasyPaisa refunds are a manual trip to their merchant portal.
     */
    public function approve(Booking $booking): Booking
    {
        if (!in_array($booking->status, ['pending', 'approved'], true)) {
            return $booking;
        }

        $booking->update([
            'status'         => 'approved',
            'approved_at'    => $booking->approved_at ?? now(),
            'payment_due_at' => now()->addHours(self::PAYMENT_WINDOW_HOURS),
        ]);

        return $booking;
    }

    /**
     * Release approved bookings nobody paid for.
     *
     * An approved seat is capacity held against the package, so leaving it
     * open forever would let a package sell out to people who never paid.
     * Called lazily wherever bookings are read, and by bookings:expire.
     */
    public function expireUnpaid(?int $packageId = null): int
    {
        $stale = Booking::where('status', 'approved')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->when($packageId, fn ($q) => $q->where('agency_package_id', $packageId))
            ->get();

        foreach ($stale as $booking) {
            $this->release($booking);
        }

        return $stale->count();
    }

    /**
     * Confirm a booking and seat the traveller on the package's group trip,
     * creating that trip (and its chat) the first time anyone books.
     *
     * Reached by a settled payment, after the agency has already approved. The
     * seating lives here rather than in a controller so a paid traveller can't
     * end up confirmed but missing from the group chat.
     *
     * Idempotent: confirming twice must not seat anyone twice.
     */
    public function confirm(Booking $booking): Booking
    {
        if ($booking->status === 'confirmed') {
            return $booking;
        }

        $booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        $package = $booking->agencyPackage;

        if (!$package) {
            return $booking;
        }

        $trip = $package->trip ?: $this->openTripFor($package);

        $member = TripMember::firstOrCreate(
            ['trip_id' => $trip->id, 'user_id' => $booking->user_id],
            ['status' => 'joined', 'role' => 'member', 'joined_at' => now()],
        );

        // Seats, not accounts: a party of four fills four spots.
        if ($member->wasRecentlyCreated) {
            $trip->increment('current_count', $booking->travelers_count);
        }

        return $booking;
    }

    /** The departure's group trip: agency owner hosts, capacity is in seats. */
    private function openTripFor($package): Trip
    {
        $trip = Trip::create([
            'creator_id'     => $package->agency->user_id,
            'destination_id' => $package->destination_id,
            'package_id'     => $package->id,
            'title'          => $package->title,
            'description'    => $package->description,
            'start_date'     => $package->start_date,
            'end_date'       => $package->end_date,
            'max_travelers'  => $package->max_capacity,
            // The agency host is staff and holds no paid seat, so this starts
            // at zero and grows by travelers_count.
            'current_count'  => 0,
            'type'           => 'cultural',
            'visibility'     => 'invite_only',
            'status'         => 'planning',
        ]);

        TripMember::create([
            'trip_id'   => $trip->id,
            'user_id'   => $package->agency->user_id,
            'status'    => 'joined',
            'role'      => 'host',
            'joined_at' => now(),
        ]);

        GroupChat::create([
            'trip_id' => $trip->id,
            'name'    => $package->title . ' Group',
        ]);

        return $trip;
    }

    /**
     * Cancel a booking and give back everything it was holding: the package's
     * seats, the group trip's headcount, and the traveller's place on the trip.
     *
     * Safe to call twice — an already-cancelled booking releases nothing.
     */
    public function release(Booking $booking): Booking
    {
        if ($booking->status === 'cancelled') {
            return $booking;
        }

        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $package = $booking->agencyPackage;

        if (!$package) {
            return $booking;
        }

        $package->decrement('booked_count', $booking->travelers_count);

        $trip = $package->trip;

        if (!$trip) {
            return $booking;
        }

        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $booking->user_id)
            ->where('role', 'member')
            ->first();

        if ($member && $member->status === 'joined') {
            $member->delete();
            // The whole party's seats, not just one.
            $trip->decrement('current_count', $booking->travelers_count);
        }

        return $booking;
    }
}
