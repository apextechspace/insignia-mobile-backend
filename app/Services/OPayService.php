<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OPayService
{
    protected string $merchantId;
    protected string $publicKey;
    protected string $secretKey;
    protected string $baseUrl;
    protected string $env;

    public function __construct()
    {
        $this->merchantId = config('services.opay.merchant_id', '256626100809577');
        $this->publicKey  = config('services.opay.public_key', '');
        $this->secretKey  = config('services.opay.secret_key', '');
        $this->baseUrl    = rtrim(config('services.opay.base_url', 'https://sandboxapi.opaycheckout.com'), '/');
        $this->env        = config('services.opay.env', 'sandbox');
    }

    /**
     * Create an OPay Cashier Payment Link
     *
     * @param array $data [
     *    'reference' => string,
     *    'amount' => float|int,
     *    'product_name' => string,
     *    'product_description' => string,
     *    'customer_name' => string,
     *    'user_phone' => string,
     *    'user_email' => string,
     *    'callback_url' => string,
     *    'return_url' => string,
     * ]
     * @return array
     */
    public function createCashierPayment(array $data): array
    {
        $endpoint = "{$this->baseUrl}/api/v1/international/cashier/create";

        $amount = round((float) $data['amount'], 2);

        $payload = [
            'country'   => 'NG',
            'reference' => $data['reference'],
            'amount'    => [
                'total'    => (int) $amount,
                'currency' => 'NGN',
            ],
            'product' => [
                'name'        => substr($data['product_name'], 0, 100),
                'description' => substr($data['product_description'] ?? 'Insignia Luxury Living', 0, 200),
            ],
            'returnUrl'    => $data['return_url'] ?? "insignia://payment-complete?reference={$data['reference']}",
            'callbackUrl'  => $data['callback_url'] ?? url('/api/payments/opay/webhook'),
            'userPhone'    => !empty($data['user_phone']) ? $data['user_phone'] : '+2348000000000',
            'customerName' => !empty($data['customer_name']) ? $data['customer_name'] : 'Insignia Guest',
        ];

        Log::info('OPay Cashier Request:', [
            'endpoint' => $endpoint,
            'merchant_id' => $this->merchantId,
            'reference' => $data['reference'],
            'amount' => $amount,
        ]);

        try {
            $response = Http::withoutVerifying()
                ->withOptions([
                    'connect_timeout' => 15,
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->withHeaders([
                    'Authorization' => "Bearer {$this->publicKey}",
                    'MerchantId'    => $this->merchantId,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->timeout(25)
                ->post($endpoint, $payload);

            $result = $response->json();

            Log::info('OPay Cashier Response:', ['status' => $response->status(), 'body' => $result]);

            if ($response->successful() && isset($result['data']['cashierUrl'])) {
                return [
                    'success'    => true,
                    'cashierUrl' => $result['data']['cashierUrl'],
                    'reference'  => $data['reference'],
                    'orderNo'    => $result['data']['orderNo'] ?? null,
                    'raw'        => $result,
                ];
            }

            $errorMessage = $result['message'] ?? 'Failed to initialize OPay cashier payment.';
            return [
                'success' => false,
                'message' => $errorMessage,
                'raw'     => $result,
            ];

        } catch (\Exception $e) {
            Log::error('OPay Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => 'Connection error connecting to OPay gateway: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify the HMAC-SHA512 Signature of an incoming OPay Webhook
     *
     * @param string $rawPayload
     * @param string|null $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        if (empty($signature) || empty($this->secretKey)) {
            Log::warning('OPay Webhook Signature Verification skipped: missing signature or secret key.');
            return false;
        }

        $calculatedSignature = hash_hmac('sha512', $rawPayload, $this->secretKey);

        $matches = hash_equals($calculatedSignature, $signature);

        if (!$matches) {
            Log::warning('OPay Webhook Signature Mismatch', [
                'received'   => $signature,
                'calculated' => $calculatedSignature,
            ]);
        }

        return $matches;
    }

    /**
     * Query payment status directly from OPay (fallback polling)
     *
     * @param string $reference
     * @param string|null $orderNo
     * @return array
     */
    public function queryPaymentStatus(string $reference, ?string $orderNo = null): array
    {
        $endpoint = "{$this->baseUrl}/api/v1/international/cashier/status";

        $payload = [
            'reference' => $reference,
        ];
        if ($orderNo) {
            $payload['orderNo'] = $orderNo;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->publicKey}",
                'MerchantId'    => $this->merchantId,
                'Content-Type'  => 'application/json',
            ])->timeout(20)->post($endpoint, $payload);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error('OPay Query Exception: ' . $e->getMessage());
            return ['code' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
}
