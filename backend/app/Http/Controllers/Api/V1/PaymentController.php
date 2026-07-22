<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\AgencySubscription;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private GatewayManager $gateways,
    ) {}

    /** GET /payments/methods — what this user can actually pay with today. */
    public function methods()
    {
        return response()->json(['data' => $this->gateways->available()]);
    }

    /** GET /payments — the signed-in user's transaction history. */
    public function index(Request $request)
    {
        $payments = Payment::with('payable')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return PaymentResource::collection($payments);
    }

    /** GET /payments/{payment} — one payment, used as the receipt. */
    public function show(Request $request, Payment $payment)
    {
        // A receipt names what someone paid and how much we kept — only the
        // payer and the agency being paid have any business reading it.
        if (!$this->mayView($request, $payment)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return new PaymentResource($payment->load('payable'));
    }

    /**
     * POST /payments/initiate — start paying for a booking or a subscription.
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'type'     => 'required|in:booking,subscription',
            'id'       => 'required|integer',
            'gateway'  => 'nullable|string',
            'simulate' => 'nullable|in:fail',
        ]);

        $user = $request->user();

        $payable = $validated['type'] === 'booking'
            ? Booking::find($validated['id'])
            : AgencySubscription::with('agency')->find($validated['id']);

        if (!$payable) {
            return response()->json(['message' => 'Nothing to pay for here.'], 404);
        }

        if (!$this->mayPay($user, $payable)) {
            return response()->json(['message' => "That isn't yours to pay for."], 403);
        }

        if ($payable instanceof Booking) {
            if ($payable->payment_status === 'paid') {
                return response()->json(['message' => 'This booking is already paid.'], 422);
            }

            // Agencies vet before money moves. Paying a booking they haven't
            // approved would be taking money for a seat they may refuse.
            if ($payable->status === 'pending') {
                return response()->json([
                    'message' => "The agency hasn't approved this booking yet — you'll be able to pay once they do.",
                ], 422);
            }

            if ($payable->status !== 'approved') {
                return response()->json(['message' => 'This booking can no longer be paid for.'], 422);
            }

            if ($payable->payment_due_at?->isPast()) {
                app(\App\Services\BookingService::class)->release($payable);

                return response()->json([
                    'message' => 'The payment window on this booking has passed. Ask the agency to approve it again.',
                ], 422);
            }
        }

        $gateway = $validated['gateway'] ?? config('payments.default');

        $available = collect($this->gateways->available())->pluck('name');
        if (!$available->contains($gateway)) {
            return response()->json(['message' => "That payment method isn't available right now."], 422);
        }

        $payment = $this->payments->initiate($user, $payable, $gateway, [
            'simulate' => $validated['simulate'] ?? null,
        ]);

        return response()->json([
            'message' => match ($payment->status) {
                'succeeded' => 'Payment complete.',
                'failed'    => $payment->failure_reason ?? 'The payment did not go through.',
                default     => 'Payment started.',
            },
            'data'    => new PaymentResource($payment->load('payable')),
            // Only set when the gateway needs the browser to go somewhere.
            'redirect' => $payment->gateway_payload['redirect_url'] ?? null,
        ], $payment->status === 'failed' ? 402 : 201);
    }

    /**
     * POST /payments/callback/{gateway} — where gateways report back.
     *
     * Unauthenticated by necessity: the caller is the provider, not the user.
     * Every driver verifies a signature and returns null if it doesn't check
     * out, so a forged body can't settle anything.
     */
    public function callback(Request $request, string $gateway)
    {
        try {
            $driver = $this->gateways->driver($gateway);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'Unknown gateway'], 404);
        }

        // Stripe signs the raw body — parsing and re-encoding would break it.
        if ($driver instanceof StripeGateway
            && !$driver->verifyWebhook($request->getContent(), $request->header('Stripe-Signature'))) {
            Log::warning('Stripe webhook rejected — bad signature.');

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = $request->all();
        $result  = $driver->handleCallback($payload);

        if (!$result) {
            return response()->json(['message' => 'Ignored'], 202);
        }

        $reference = $payload['pp_TxnRefNo']
            ?? $payload['orderRefNum']
            ?? data_get($payload, 'data.object.metadata.payment_reference');

        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            Log::warning('Callback for unknown payment reference', ['ref' => $reference]);

            return response()->json(['message' => 'Unknown payment'], 404);
        }

        $this->payments->apply($payment, $result);

        return response()->json(['message' => 'Recorded']);
    }

    /** POST /payments/{payment}/refund — admin only, manual for now. */
    public function refund(Request $request, Payment $payment)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Not allowed'], 403);
        }

        if ($payment->status !== 'succeeded') {
            return response()->json(['message' => 'Only a settled payment can be refunded.'], 422);
        }

        $payment = $this->payments->refund($payment);

        return response()->json([
            'message' => $payment->status === 'refunded'
                ? 'Refunded.'
                : 'The gateway could not complete this refund — settle it manually.',
            'data'    => new PaymentResource($payment),
        ]);
    }

    private function mayPay($user, $payable): bool
    {
        if ($payable instanceof Booking) {
            return $payable->user_id === $user->id;
        }

        if ($payable instanceof AgencySubscription) {
            return $payable->agency?->user_id === $user->id;
        }

        return false;
    }

    private function mayView(Request $request, Payment $payment): bool
    {
        $user = $request->user();

        if ($payment->user_id === $user->id || $user->hasRole('admin')) {
            return true;
        }

        // The selling agency can see payments for its own packages.
        $payable = $payment->payable;

        return $payable instanceof Booking
            && $payable->agencyPackage?->agency?->user_id === $user->id;
    }
}
