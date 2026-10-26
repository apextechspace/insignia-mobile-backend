<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\FoodOrder;
use App\Models\ServiceBooking;
use App\Models\PaymentAttempt;
use App\Services\FlutterwaveService;
use App\Services\OPayService;
use App\Services\ExpoPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected FlutterwaveService $flutterwaveService;
    protected OPayService $opayService;

    public function __construct(FlutterwaveService $flutterwaveService, OPayService $opayService)
    {
        $this->flutterwaveService = $flutterwaveService;
        $this->opayService = $opayService;
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
        ]);

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
        $prefix = strtoupper(substr($orderType, 0, 4));
        $reference = "INS-FLW-{$prefix}-{$record->id}-" . Str::uuid();

        if (!empty($record->paid_at) || in_array($record->status, ['cancelled', 'refunded', 'completed', 'delivered'], true)) {
            return response()->json(['status' => 'error', 'message' => 'This transaction is no longer payable.'], 409);
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
            'customer_name'       => $user->name,
            'user_phone'          => $user->phone ?? '+2348000000000',
            'user_email'          => $user->email,
            'redirect_url'        => "insignia://payment-complete?reference={$reference}&order_type={$orderType}&order_id={$orderId}",
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
                'status'  => $confirmed || !empty($record->paid_at) ? 'success' : 'pending',
                'is_paid' => $confirmed || !empty($record->paid_at),
                'record'  => $record,
            ]);
        }

        return response()->json([
            'status'  => 'pending',
            'is_paid' => false,
            'record'  => $record,
        ]);
    }

    /**
     * Legacy / OPay Cashier compatibility handlers
     */
    public function initialize(Request $request)
    {
        $gateway = $request->input('gateway', 'flutterwave');

        if ($gateway === 'opay') {
            return $this->initializeOPay($request);
        }

        return $this->initializeFlutterwave($request);
    }

    public function verify($reference)
    {
        // Try Flutterwave verification first
        $response = $this->verifyFlutterwave($reference);
        $data = $response->getData(true);

        if (!empty($data['is_paid'])) {
            return $response;
        }

        // Fallback to OPay verification if record references OPay
        $record = $this->findRecordByReference($reference);
        if ($record && ($record->payment_method === 'opay' || !empty($record->opay_reference))) {
            return $this->verifyOPay($reference);
        }

        return $response;
    }

    public function initializeOPay(Request $request)
    {
        $validated = $request->validate([
            'order_type' => 'required|in:booking,order,food_order,service_booking',
            'order_id'   => 'required|integer',
        ]);

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

        $prefix = strtoupper(substr($orderType, 0, 4));
        $reference = "INS-OPAY-{$prefix}-{$record->id}-" . time();

        $record->update([
            'payment_method' => 'opay',
            'opay_reference' => $reference,
        ]);

        $response = $this->opayService->createCashierPayment([
            'reference'           => $reference,
            'amount'              => $amount,
            'product_name'        => $productName,
            'product_description' => $productDesc,
            'customer_name'       => $user->name,
            'user_phone'          => $user->phone ?? '+2348000000000',
            'user_email'          => $user->email,
            'callback_url'        => url('/api/payments/opay/webhook'),
            'return_url'          => "insignia://payment-complete?reference={$reference}&order_type={$orderType}&order_id={$orderId}",
        ]);

        if (!$response['success']) {
            return response()->json([
                'status'  => 'error',
                'message' => $response['message'] ?? 'Unable to initialize OPay checkout.',
            ], 400);
        }

        return response()->json([
            'status'      => 'success',
            'cashier_url' => $response['cashierUrl'],
            'payment_url' => $response['cashierUrl'],
            'reference'   => $reference,
            'order_id'    => $record->id,
            'order_type'  => $orderType,
            'amount'      => $amount,
        ]);
    }

    public function webhook(Request $request)
    {
        $rawPayload = $request->getContent();
        $payload = $request->all();

        $signature = $request->header('sha512') 
            ?? $request->header('Signature') 
            ?? $request->input('sha512');

        $isValid = $this->opayService->verifyWebhookSignature($rawPayload, $signature);
        if (!$isValid && config('services.opay.env') === 'production') {
            Log::error('OPay Webhook rejected: Invalid signature.');
            return response()->json(['code' => '400', 'message' => 'Invalid signature'], 400);
        }

        $reference = $payload['reference'] ?? null;
        $status = strtoupper($payload['status'] ?? '');
        $orderNo = $payload['orderNo'] ?? null;

        if (!$reference) {
            return response()->json(['code' => '400', 'message' => 'Missing reference'], 400);
        }

        if ($status === 'SUCCESS' || $status === 'PAID') {
            $this->markOrderAsPaid($reference, $orderNo, 'opay');
        }

        return response()->json(['code' => '00000', 'message' => 'SUCCESS']);
    }

    public function verifyOPay($reference)
    {
        $record = $this->findRecordByReference($reference);

        if (!$record) {
            return response()->json(['status' => 'error', 'message' => 'Transaction not found.'], 404);
        }

        if ($record->status === 'confirmed' || $record->status === 'paid' || !empty($record->paid_at)) {
            return response()->json([
                'status'  => 'success',
                'is_paid' => true,
                'record'  => $record,
            ]);
        }

        $statusResponse = $this->opayService->queryPaymentStatus($reference, $record->opay_order_id);
        $opayStatus = strtoupper($statusResponse['data']['status'] ?? '');

        if ($opayStatus === 'SUCCESS' || $opayStatus === 'PAID') {
            $this->markOrderAsPaid($reference, $statusResponse['data']['orderNo'] ?? null, 'opay');
            $record->refresh();
            return response()->json([
                'status'  => 'success',
                'is_paid' => true,
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
     * Confirm once after provider verification, with row locks and no duplicate notification.
     */
    protected function markOrderAsPaid(string $reference, ?string $externalId, string $gateway = 'flutterwave'): bool
    {
        if ($gateway === 'flutterwave' && !$externalId) {
            return false;
        }

        $notification = DB::transaction(function () use ($reference, $externalId, $gateway) {
            $model = $this->findRecordByReference($reference);
            if (!$model) {
                return null;
            }

            if ($model instanceof Booking) {
                // Serialize payment confirmations for competing stays in the same suite.
                \App\Models\Room::whereKey($model->room_id)->lockForUpdate()->firstOrFail();
            }

            $record = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $referenceColumn = $gateway === 'flutterwave' ? 'flutterwave_reference' : 'opay_reference';
            $idColumn = $gateway === 'flutterwave' ? 'flutterwave_transaction_id' : 'opay_order_id';

            if ($record->{$referenceColumn} !== $reference || !empty($record->paid_at)) {
                return null;
            }
            if (in_array($record->status, ['cancelled', 'refunded', 'completed', 'delivered'], true)) {
                \App\Models\PaymentReconciliationCase::updateOrCreate(
                    ['payment_reference' => $reference],
                    [
                        'gateway' => $gateway,
                        'provider_transaction_id' => $externalId,
                        'payable_type' => get_class($record),
                        'payable_id' => $record->getKey(),
                        'reason' => 'payable_inactive',
                        'status' => 'needs_review',
                    ]
                );
                Log::warning('Payment for inactive record needs reconciliation', ['reference' => $reference]);
                return null;
            }

            if ($externalId) {
                foreach ([Booking::class, Order::class, FoodOrder::class, ServiceBooking::class] as $type) {
                    $query = $type::where($idColumn, $externalId);
                    if ($type === get_class($record)) {
                        $query->whereKeyNot($record->getKey());
                    }
                    if ($query->exists()) {
                        Log::warning('Gateway transaction ID has already been assigned', ['reference' => $reference]);
                        return null;
                    }
                }
            }

            if ($record instanceof Booking) {
                $conflict = Booking::where('room_id', $record->room_id)
                    ->whereKeyNot($record->getKey())
                    ->whereIn('status', ['confirmed', 'paid', 'completed'])
                    ->where('check_in', '<', $record->check_out)
                    ->where('check_out', '>', $record->check_in)
                    ->exists();
                if ($conflict) {
                    \App\Models\PaymentReconciliationCase::updateOrCreate(
                        ['payment_reference' => $reference],
                        [
                            'gateway' => $gateway,
                            'provider_transaction_id' => $externalId,
                            'payable_type' => get_class($record),
                            'payable_id' => $record->getKey(),
                            'reason' => 'room_date_conflict',
                            'status' => 'needs_review',
                        ]
                    );
                    Log::error('Paid reservation date conflict requires reconciliation', [
                        'booking_id' => $record->id, 'reference' => $reference,
                    ]);
                    return null;
                }
            }

            $record->status = $record instanceof FoodOrder ? 'paid' : 'confirmed';
            $record->payment_method = $gateway;
            $record->paid_at = now();
            $record->{$idColumn} = $externalId;
            $record->save();

            $title = match (true) {
                $record instanceof Booking => 'Suite Reservation Confirmed',
                $record instanceof FoodOrder => 'Dining Order Confirmed',
                $record instanceof Order => 'Boutique Order Confirmed',
                default => 'Concierge Booking Confirmed',
            };
            return ['user' => $record->user, 'title' => $title];
        }, 3);

        if (!$notification) {
            return false;
        }

        $this->sendConfirmationNotification(
            $notification['user'], $notification['title'],
            'Your payment has been verified and confirmed.'
        );
        return true;
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

    protected function sendConfirmationNotification($user, string $title, string $message)
    {
        if ($user && !empty($user->expo_push_token)) {
            ExpoPushService::send($user->expo_push_token, $title, $message, [
                'type' => 'payment_confirmation',
            ]);
        }
    }
}
