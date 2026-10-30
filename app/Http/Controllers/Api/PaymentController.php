<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\FoodOrder;
use App\Models\ServiceBooking;
use App\Models\PaymentAttempt;
use App\Services\FlutterwaveService;
use App\Services\ExpoPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected FlutterwaveService $flutterwaveService;

    public function __construct(FlutterwaveService $flutterwaveService)
    {
        $this->flutterwaveService = $flutterwaveService;
    }

    /**
     * Initialize Flutterwave Payment (Default Primary Gateway)
     *
     * POST /api/payments/flutterwave/initialize
     * POST /api/payments/initialize
     */
    public function initializeFlutterwave(Request $request)
    {
        $validated = $request->validate([
            'order_type' => 'required|in:booking,order,food_order,service_booking',
            'order_id'   => 'required|integer',
            'redirect_url' => 'nullable|string|max:2048',
        ]);

        $redirectBase = $validated['redirect_url'] ?? 'insignia://payment-complete';
        $webReturn = config('services.flutterwave.web_return_url');
        if ($redirectBase !== 'insignia://payment-complete'
            && (!$webReturn || !str_starts_with($webReturn, 'https://') || $redirectBase !== $webReturn)) {
            return response()->json(['message' => 'Payment return URL is not configured for this app. Please contact support.'], 422);
        }

        $user = $request->user();
        $orderType = $validated['order_type'];
        $orderId = $validated['order_id'];

        $record = null;
        $amount = 0;
        $productName = 'Insignia Luxury Living';
        $productDesc = 'Insignia Courts hospitality transaction';

        switch ($orderType) {
            case 'booking':
                $record = Booking::with('room')->where('id', $orderId)->where('user_id', $user->id)->firstOrFail();
                $amount = $record->total_price;
                $suiteName = $record->room->name ?? 'Suite';
                $productName = "Insignia Suite: {$suiteName}";
                $productDesc = "Reservation from {$record->check_in} to {$record->check_out}";
                break;

            case 'order':
                $record = Order::with('product')->where('id', $orderId)->where('user_id', $user->id)->firstOrFail();
                $amount = $record->total_price;
                $productName = "Insignia Boutique: " . ($record->product->name ?? 'Luxury Goods');
                $productDesc = "Delivery to {$record->delivery_location}";
                break;

            case 'food_order':
                $record = FoodOrder::where('id', $orderId)->where('user_id', $user->id)->firstOrFail();
                $amount = $record->total_amount;
                $productName = "Insignia Dining (" . ucfirst(str_replace('_', ' ', $record->type)) . ")";
                $productDesc = "Order #{$record->id} dining service";
                break;

            case 'service_booking':
                $record = ServiceBooking::with('service')->where('id', $orderId)->where('user_id', $user->id)->firstOrFail();
                $amount = $record->total_price;
                $serviceName = $record->service->name ?? 'Concierge';
                $productName = "Insignia Service: {$serviceName}";
                $productDesc = "Scheduled for {$record->scheduled_at}";
                break;
        }

        // Generate distinct reference
        if ($record instanceof Booking) {
            DB::transaction(function () use ($record) {
                $room = \App\Models\Room::whereKey($record->room_id)->lockForUpdate()->firstOrFail();
                $locked = Booking::whereKey($record->id)->lockForUpdate()->firstOrFail();
                if (!$room->is_available || $room->status === 'maintenance' || $locked->paid_at
                    || $locked->status !== 'pending'
                    || ($locked->hold_expires_at && $locked->hold_expires_at->lte(now()))
                    || \App\Services\RoomAvailability::blocking($locked->check_in, $locked->check_out, $locked->id)->where('room_id', $room->id)->exists()) {
                    abort(409, 'This checkout is no longer available. Please check suite availability again.');
                }
                if (!$locked->hold_expires_at) $locked->update(['hold_expires_at' => now()->addMinutes(15)]);
            }, 3);
        }

        $prefix = strtoupper(substr($orderType, 0, 4));
        $reference = "INS-FLW-{$prefix}-{$record->id}-" . Str::uuid();

        if (!empty($record->paid_at) || in_array($record->status, ['cancelled', 'refunded', 'completed', 'delivered'], true)) {
            return response()->json(['status' => 'error', 'message' => 'This transaction is no longer payable.'], 409);
        }

        if ($record instanceof Order && !$record->stock_deducted
            && (!$record->product || $record->product->stock < $record->quantity)) {
            return response()->json(['status' => 'error', 'message' => 'This item is no longer available in the requested quantity.'], 409);
        }

        // Every checkout attempt is retained, even if a later attempt replaces
        // the compatibility reference on the original payable.
        PaymentAttempt::create([
            'gateway' => 'flutterwave',
            'reference' => $reference,
            'payable_type' => get_class($record),
            'payable_id' => $record->getKey(),
            'amount' => $amount,
            'currency' => 'NGN',
            'status' => 'initiated',
        ]);

        $record->update([
            'payment_method' => 'flutterwave',
            'flutterwave_reference' => $reference,
        ]);

        // Request payment link from Flutterwave
        $response = $this->flutterwaveService->createPaymentLink([
            'reference'           => $reference,
            'amount'              => $amount,
            'product_name'        => $productName,
            'product_description' => $productDesc,
            'customer_name'       => $record instanceof Booking ? ($record->booking_contact['name'] ?? $user->name) : $user->name,
            'user_phone'          => $record instanceof Booking ? ($record->booking_contact['phone'] ?? $user->phone ?? '+2348000000000') : ($user->phone ?? '+2348000000000'),
            'user_email'          => $user->email,
            'redirect_url'        => $redirectBase . (str_contains($redirectBase, '?') ? '&' : '?') . http_build_query([
                'reference' => $reference, 'order_type' => $orderType, 'order_id' => $orderId,
            ]),
            'meta'                => [
                'order_type' => $orderType,
                'order_id'   => $orderId,
                'user_id'    => $user->id,
            ],
        ]);

        if (!$response['success']) {
            PaymentAttempt::where('reference', $reference)->update(['status' => 'initialization_failed']);
            return response()->json([
                'status'  => 'error',
                'message' => $response['message'] ?? 'Unable to initialize Flutterwave checkout.',
            ], 400);
        }

        return response()->json([
            'status'      => 'success',
            'payment_url' => $response['payment_url'],
            'cashier_url' => $response['payment_url'], // Backward compatibility with mobile clients
            'reference'   => $reference,
            'order_id'    => $record->id,
            'order_type'  => $orderType,
            'amount'      => $amount,
        ]);
    }

    /**
     * Flutterwave Webhook Handler (Instant server-to-server confirmation)
     *
     * POST /api/payments/flutterwave/webhook
     */
    public function webhookFlutterwave(Request $request)
    {
        $signature = $request->header('verif-hash') ?? $request->header('Verif-Hash');

        if (!$this->flutterwaveService->verifyWebhookSignature($signature)) {
            Log::warning('Flutterwave webhook rejected: invalid secret hash.');
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        Log::info('Flutterwave webhook received', ['event' => $payload['event'] ?? null]);

        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        $txRef = $data['tx_ref'] ?? null;
        $status = strtoupper($data['status'] ?? '');
        $transactionId = $data['id'] ?? null;

        if ($txRef && ($status === 'SUCCESSFUL' || $status === 'SUCCESS' || $status === 'COMPLETED')) {
            // Confirm transaction integrity with Flutterwave API
            if ($transactionId) {
                $verification = $this->flutterwaveService->verifyTransaction($transactionId);
                if ($this->isVerifiedPayment($verification, $txRef)
                    && (string) ($verification['data']['id'] ?? '') === (string) $transactionId) {
                    $this->markOrderAsPaid($txRef, (string) $transactionId, 'flutterwave');
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Verify payment status directly (used by mobile app polling upon return)
     *
     * GET /api/payments/flutterwave/verify/{reference}
     * GET /api/payments/verify/{reference}
     */
    public function verifyFlutterwave($reference)
    {
        $record = $this->findRecordByReference($reference);

        if (!$record) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found.'], 404);
        }

        if ((int) $record->user_id !== (int) auth()->id()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // If already confirmed in database
        $attempt = PaymentAttempt::where('reference', $reference)->first();
        if ($attempt && $attempt->status === 'confirmed' && !empty($record->paid_at)) {
            return response()->json([
                'status'  => 'success',
                'is_paid' => true,
                'record'  => $record,
            ]);
        }

        // Otherwise query Flutterwave API by reference
        $flwStatus = $this->flutterwaveService->verifyByReference($reference);

        if ($this->isVerifiedPayment($flwStatus, $reference)) {
            $transId = (string) ($flwStatus['data']['id'] ?? '');
            $confirmed = $this->markOrderAsPaid($reference, $transId, 'flutterwave');
            $record->refresh();

            return response()->json([
                'status'  => $confirmed || PaymentAttempt::where('reference', $reference)->where('status', 'confirmed')->exists() ? 'success' : 'pending',
                'is_paid' => $confirmed || PaymentAttempt::where('reference', $reference)->where('status', 'confirmed')->exists(),
                'record'  => $record,
            ]);
        }

        return response()->json([
            'status'  => 'pending',
            'is_paid' => false,
            'record'  => $record,
        ]);
    }

    protected function isVerifiedPayment(array $result, string $reference): bool
    {
        if (empty($result['success']) || strtoupper((string) ($result['status'] ?? '')) !== 'SUCCESSFUL') {
            return false;
        }

        $attempt = PaymentAttempt::where('reference', $reference)->where('gateway', 'flutterwave')->first();
        $data = $result['data'] ?? [];
        if (!$attempt || ($data['tx_ref'] ?? null) !== $reference
            || strtoupper((string) ($data['currency'] ?? '')) !== $attempt->currency
            || empty($data['id'])) {
            return false;
        }

        // The amount is frozen when the payment link was generated. Never
        // compare only against a mutable shopping-cart or booking amount.
        return is_numeric($data['amount'] ?? null)
            && (int) round(((float) $attempt->amount) * 100) === (int) round(((float) $data['amount']) * 100);
    }

    /**
     * Apply provider-verified payment once, preserving all past checkout attempts.
     * When funds arrive for a conflicting/inactive booking, record an exception
     * for finance rather than incorrectly confirming the reservation.
     */
    protected function markOrderAsPaid(string $reference, ?string $externalId, string $gateway = 'flutterwave'): bool
    {
        if ($gateway !== 'flutterwave' || !$externalId) {
            return false;
        }

        $notification = DB::transaction(function () use ($reference, $externalId) {
            $attempt = PaymentAttempt::where('reference', $reference)->first();
            if (!$attempt || $attempt->gateway !== 'flutterwave') {
                return null;
            }

            $model = $attempt->payable;
            if (!$model) {
                Log::error('Payment attempt payable missing', ['reference' => $reference]);
                return null;
            }

            // Consistent ordering: lock suite first, then booking, then attempt.
            if ($model instanceof Booking) {
                \App\Models\Room::whereKey($model->room_id)->lockForUpdate()->firstOrFail();
            }
            if ($model instanceof Order) {
                \App\Models\Product::whereKey($model->product_id)->lockForUpdate()->first();
            }

            $record = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $attempt = PaymentAttempt::whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($attempt->status, ['confirmed', 'needs_review'], true)) {
                return null; // Webhook replay, no additional notification.
            }

            if ($attempt->provider_transaction_id
                && $attempt->provider_transaction_id !== $externalId) {
                $this->flagPaymentForReview($attempt, $externalId, 'transaction_id_mismatch');
                return null;
            }

            // A provider transaction is globally unique, even across payable types.
            if (PaymentAttempt::where('provider_transaction_id', $externalId)
                ->whereKeyNot($attempt->getKey())->exists()) {
                $this->flagPaymentForReview($attempt, $externalId, 'transaction_id_reused');
                return null;
            }

            $currentPrice = $record instanceof FoodOrder ? $record->total_amount : $record->total_price;
            if (!is_numeric($currentPrice)
                || (int) round(((float) $attempt->amount) * 100) !== (int) round(((float) $currentPrice) * 100)) {
                $this->flagPaymentForReview($attempt, $externalId, 'payable_amount_changed');
                return null;
            }

            if (!empty($record->paid_at)) {
                $this->flagPaymentForReview($attempt, $externalId, 'already_paid_other_attempt');
                return null;
            }

            if (in_array($record->status, ['cancelled', 'refunded', 'completed', 'delivered'], true)) {
                $this->flagPaymentForReview($attempt, $externalId, 'payable_inactive');
                return null;
            }

            if ($record instanceof Booking) {
                if ($record->hold_expires_at && $record->hold_expires_at->lte(now())) {
                    $this->flagPaymentForReview($attempt, $externalId, 'reservation_hold_expired');
                    return null;
                }
                $conflict = \App\Services\RoomAvailability::blocking($record->check_in, $record->check_out, $record->id)
                    ->where('room_id', $record->room_id)->exists();

                if ($conflict) {
                    $this->flagPaymentForReview($attempt, $externalId, 'room_date_conflict');
                    return null;
                }
            }

            if ($record instanceof Order && !$record->stock_deducted) {
                $product = \App\Models\Product::whereKey($record->product_id)->first();
                if (!$product || $product->stock < $record->quantity) {
                    $this->flagPaymentForReview($attempt, $externalId, 'product_out_of_stock');
                    return null;
                }
                $product->decrement('stock', $record->quantity);
                $record->stock_deducted = true;
            }

            $record->status = $record instanceof FoodOrder ? 'paid' : 'confirmed';
            $record->payment_method = 'flutterwave';
            $record->paid_at = now();
            $record->flutterwave_reference = $reference;
            $record->flutterwave_transaction_id = $externalId;
            $record->save();

            $attempt->update([
                'status' => 'confirmed',
                'provider_transaction_id' => $externalId,
                'confirmed_at' => now(),
            ]);

            $title = match (true) {
                $record instanceof Booking => 'Suite Reservation Confirmed',
                $record instanceof FoodOrder => 'Dining Order Confirmed',
                $record instanceof Order => 'Boutique Order Confirmed',
                default => 'Concierge Booking Confirmed',
            };

            if ($record instanceof Booking) {
                $recipients = \App\Models\User::whereIn('role', [
                    \App\Models\User::ROLE_ADMIN,
                    \App\Models\User::ROLE_IPL_ADMIN,
                    \App\Models\User::ROLE_DELIVERY_PARTNER,
                    \App\Models\User::ROLE_GUEST_RELATIONS,
                ])->get();
                if ($record->user) {
                    $recipients->push($record->user);
                }
                foreach ($recipients->unique('id') as $recipient) {
                    $recipient->notify(new \App\Notifications\PaymentActivityNotification(
                        'Verified suite booking',
                        'Suite booking #' . $record->id . ' was paid and confirmed.',
                        $record->id, 'confirmed'
                    ));
                }
            }

            if ($record instanceof FoodOrder || $record instanceof Order || $record instanceof ServiceBooking) {
                $orderType = match (true) {
                    $record instanceof FoodOrder => 'food_order',
                    $record instanceof Order => 'order',
                    default => 'service_booking',
                };

                // Owner sees all verified income. Operations sees the relevant
                // fulfillment request in its own database notification feed.
                $recipients = \App\Models\User::whereIn('role', [
                    \App\Models\User::ROLE_ADMIN,
                    \App\Models\User::ROLE_IPL_ADMIN,
                    \App\Models\User::ROLE_DELIVERY_PARTNER,
                    \App\Models\User::ROLE_GUEST_RELATIONS,
                ])->get();

                if ($record->user) {
                    $recipients->push($record->user);
                }

                foreach ($recipients->unique('id') as $recipient) {
                    $recipient->notify(new \App\Notifications\VerifiedCommercePayment(
                        $orderType,
                        $record->id,
                        $title,
                        "Verified payment for {$orderType} #{$record->id}; review fulfillment."
                    ));
                }
            }

            app(\App\Services\CustomerPaymentEmailService::class)->send($record);

            return ['user' => $record->user, 'title' => $title];
        }, 3);

        if (!$notification) {
            return false;
        }

        // Each recipient's push and the customer email are queued separately.
        return true;
    }

    protected function flagPaymentForReview(PaymentAttempt $attempt, string $externalId, string $reason): void
    {
        // Keep the provider transaction evidence while withholding fulfillment.
        $changes = ['status' => 'needs_review'];
        if (!$attempt->provider_transaction_id && !PaymentAttempt::where('provider_transaction_id', $externalId)->exists()) {
            $changes['provider_transaction_id'] = $externalId;
        }
        $attempt->update($changes);

        \App\Models\PaymentReconciliationCase::updateOrCreate(
            ['payment_reference' => $attempt->reference],
            [
                'gateway' => 'flutterwave',
                'provider_transaction_id' => $externalId,
                'payable_type' => $attempt->payable_type,
                'payable_id' => $attempt->payable_id,
                'reason' => $reason,
                'status' => 'needs_review',
            ]
        );
        foreach (\App\Models\User::whereIn('role', [
            \App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_IPL_ADMIN,
        ])->get() as $financeUser) {
            $financeUser->notify(new \App\Notifications\PaymentReviewRequired($attempt->reference, $reason));
        }
        Log::warning('Verified Flutterwave payment requires reconciliation', [
            'reference' => $attempt->reference,
            'reason' => $reason,
        ]);
    }

    protected function findRecordByReference(string $reference)
    {
        $attempt = PaymentAttempt::where('gateway', 'flutterwave')
            ->where('reference', $reference)->first();
        if ($attempt) {
            return $attempt->payable;
        }

        return Booking::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first()
            ?? Order::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first()
            ?? FoodOrder::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first()
            ?? ServiceBooking::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();
    }

}
