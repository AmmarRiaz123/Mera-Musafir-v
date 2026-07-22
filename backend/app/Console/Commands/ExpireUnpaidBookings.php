<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * Hand back seats on approved bookings nobody paid for.
 *
 * The read paths expire lazily too, so a package's capacity is honest even
 * without this running — but a package nobody browses would otherwise hold
 * stale seats indefinitely. Schedule it hourly.
 */
class ExpireUnpaidBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Release approved bookings whose payment window has passed';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->expireUnpaid();

        $this->info($count === 0
            ? 'Nothing to expire.'
            : "Released {$count} unpaid booking(s).");

        return self::SUCCESS;
    }
}
