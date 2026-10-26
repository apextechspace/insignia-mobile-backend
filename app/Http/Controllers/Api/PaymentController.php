<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\FoodOrder;
use App\Models\ServiceBooking;
use App\Services\FlutterwaveService;
use App\Services\OPayService;
use App\Services\ExpoPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        $reference = "INS-FLW-{$prefix}-{$record->id}-" . time();

        // Update record with reference and payment method
        $record->update([
            'payment_method'        => 'flutterwave',
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
        Log::info('Flutterwave Webhook Received:', $payload);

        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        $txRef = $data['tx_ref'] ?? null;
        $status = strtoupper($data['status'] ?? '');
        $transactionId = $data['id'] ?? null;

        if ($txRef && ($status === 'SUCCESSFUL' || $status === 'SUCCESS' || $status === 'COMPLETED')) {
            // Confirm transaction integrity with Flutterwave API
            if ($transactionId) {
                $verification = $this->flutterwaveService->verifyTransaction($transactionId);
                if ($this->isVerifiedPayment($verification, $txRef)) {
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
        if ($record->status === 'confirmed' || $record->status === 'paid' || !empty($record->paid_at)) {
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
            $this->markOrderAsPaid($reference, $transId, 'flutterwave');
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

        $record = $this->findRecordByReference($reference);
        $data = $result['data'] ?? [];
        if (!$record || ($data['tx_ref'] ?? null) !== $reference || strtoupper((string) ($data['currency'] ?? '')) !== 'NGN') {
            return false;
        }

        $expected = $record instanceof FoodOrder ? $record->total_amount : $record->total_price;
        return is_numeric($expected) && is_numeric($data['amount'] ?? null)
            && (int) round(((float) $expected) * 100) === (int) round(((float) $data['amount']) * 100);
    }

    /**
     * Mark matched record as paid and dispatch push notification
     */
    protected function markOrderAsPaid(string $reference, ?string $externalId, string $gateway = 'flutterwave')
    {
        $updateData = [
            'status'         => 'confirmed',
            'payment_method' => $gateway,
            'paid_at'        => now(),
        ];

        if ($gateway === 'flutterwave') {
            $updateData['flutterwave_reference'] = $reference;
            if ($externalId) {
                $updateData['flutterwave_transaction_id'] = $externalId;
            }
        } else {
            $updateData['opay_reference'] = $reference;
            if ($externalId) {
                $updateData['opay_order_id'] = $externalId;
            }
        }

        // 1. Try Booking
        $booking = Booking::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();

        if ($booking) {
            $booking->update($updateData);
            $this->sendConfirmationNotification(
                $booking->user,
                '🏨 Suite Reservation Confirmed',
                "Your suite reservation has been verified and confirmed via " . ucfirst($gateway) . "."
            );
            return;
        }

        // 2. Try Boutique Order
        $order = Order::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();

        if ($order) {
            $order->update($updateData);
            $this->sendConfirmationNotification(
                $order->user,
                '🛍️ Order Confirmed',
                "Your boutique purchase was paid via " . ucfirst($gateway) . " and is being prepared."
            );
            return;
        }

        // 3. Try Food Order
        $foodOrder = FoodOrder::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();

        if ($foodOrder) {
            $foodUpdateData = $updateData;
            $foodUpdateData['status'] = 'paid';
            $foodOrder->update($foodUpdateData);
            $this->sendConfirmationNotification(
                $foodOrder->user,
                '🍽️ Dining Order Confirmed',
                'Your dining order has been received by the kitchen.'
            );
            return;
        }

        // 4. Try Service Booking
        $serviceBooking = ServiceBooking::where('flutterwave_reference', $reference)
            ->orWhere('opay_reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();

        if ($serviceBooking) {
            $serviceBooking->update($updateData);
            $this->sendConfirmationNotification(
                $serviceBooking->user,
                '✨ Concierge Booking Confirmed',
                "Your service appointment has been confirmed via " . ucfirst($gateway) . "."
            );
            return;
        }
    }

    protected function findRecordByReference(string $reference)
    {
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
