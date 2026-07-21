<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Ground truth for user-facing messages.
 *
 * Every message the user reads lives here — one code, one voice. Controllers
 * return `Messages::json('code')` instead of inlining strings, so the copy can
 * be reworded in one place and the frontend can style each code with its own
 * tone (see frontend/src/utils/notify.js, which mirrors these codes).
 *
 * Tone: warm, human, and never scolding. A blocked action should feel like a
 * friendly nudge, not an error.
 */
class Messages
{
    /** code => [message, default HTTP status] */
    public const CATALOG = [
        // ── Trips: joining ─────────────────────────────────────────────
        'women_only' => [
            "This one's a women-only trip 💜 It's a space made just for women travellers — but there are plenty of other adventures waiting for you.",
            403,
        ],
        'already_joined' => [
            "You're already on this trip 🎒 No need to join twice.",
            422,
        ],
        'trip_full' => [
            "This trip is fully packed 🧳 Every seat is taken — but there are more adventures out there.",
            422,
        ],
        'not_in_trip' => [
            "You're not on this trip, so there's nothing to leave.",
            422,
        ],
        'host_cannot_leave' => [
            "This trip is yours 🗺️ Hosts can't leave their own trip — you can delete it instead.",
            422,
        ],
        'join_blocked' => [
            "This trip isn't available to you right now.",
            403,
        ],

        // ── Trips: inviting ────────────────────────────────────────────
        'invite_pending' => [
            "They've already got this invite sitting in their inbox 👀 Give them a moment to reply.",
            422,
        ],
        'invite_already_member' => [
            "They're already part of this crew 🎒 No invite needed.",
            422,
        ],
        'not_a_member' => [
            "Join this trip first, then you can invite others along.",
            422,
        ],
        'invite_not_found' => [
            "This invite isn't around anymore — it may have been withdrawn.",
            404,
        ],
        'invite_answered' => [
            "You've already answered this invite.",
            422,
        ],
        'invite_own' => [
            "This is your own invite — wait for them to reply.",
            422,
        ],

        // ── Agency accounts ────────────────────────────────────────────
        // A business account can respond and host, but not push itself into
        // travellers' personal space.
        'agency_cannot_join' => [
            "Agency accounts can't join traveller trips 🧭 Your own package trips and their group chats live in your dashboard.",
            403,
        ],
        'agency_cannot_dm' => [
            "You can message travellers who've contacted you or booked with you 💬 It keeps their inbox free of cold pitches.",
            403,
        ],
    ];

    public static function text(string $code): string
    {
        return self::CATALOG[$code][0] ?? 'Something went wrong.';
    }

    public static function status(string $code): int
    {
        return self::CATALOG[$code][1] ?? 400;
    }

    /**
     * Build the standard error response for a code. The `code` travels with the
     * message so the client can pick the matching tone instead of a red box.
     */
    public static function json(string $code, ?int $status = null): JsonResponse
    {
        return response()->json([
            'message' => self::text($code),
            'code'    => $code,
        ], $status ?? self::status($code));
    }
}
