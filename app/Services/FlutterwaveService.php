<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected string $publicKey;
    protected string $secretKey;
    protected string $secretHash;
    protected string $baseUrl;

    public function __construct()
    {
        $this->publicKey  = config('services.flutterwave.public_key', env('FLW_PUBLIC_KEY', ''));
        $this->secretKey  = config('services.flutterwave.secret_key', env('FLW_SECRET_KEY', ''));
        $this->secretHash = config('services.flutterwave.secret_hash', env('FLW_SECRET_HASH', ''));
        $this->baseUrl    = rtrim(config('services.flutterwave.base_url', env('FLW_BASE_URL', 'https://api.flutterwave.com/v3')), '/');
    }

    /**
     * Create a standard Flutterwave Standard Hosted Payment Link
     *
     * @param array $data [
     *    'reference' => string,
     *    'amount' => float|int,
     *    'product_name' => string,
     *    'product_description' => string,
     *    'customer_name' => string,
     *    'user_phone' => string,
     *    'user_email' => string,
     *    'redirect_url' => string,
     *    'meta' => array,
     * ]
     * @return array
     */
    public function createPaymentLink(array $data): array
    {
        $endpoint = "{$this->baseUrl}/payments";
        $amount = round((float) $data['amount'], 2);

        $payload = [
            'tx_ref'       => $data['reference'],
            'amount'       => (string) $amount,
            'currency'     => 'NGN',
            'redirect_url' => $data['redirect_url'] ?? "insignia://payment-complete?reference={$data['reference']}",
            'meta'         => $data['meta'] ?? [],
            'customer'     => [
                'email'       => !empty($data['user_email']) ? $data['user_email'] : 'guest@insigniacourt.com',
                'phonenumber' => !empty($data['user_phone']) ? $data['user_phone'] : '+2348000000000',
                'name'        => !empty($data['customer_name']) ? $data['customer_name'] : 'Insignia Guest',
            ],
            'customizations' => [
                'title'       => 'Insignia Court Luxury Living',
                'description' => substr($data['product_description'] ?? 'Hospitality Services', 0, 100),
                'logo'        => 'https://insigniacourt.com/logo.png',
            ],
        ];

        Log::info('Flutterwave Payment Link Request:', [
            'endpoint'  => $endpoint,
            'reference' => $data['reference'],
            'amount'    => $amount,
        ]);

        try {
            $response = Http
                ->withHeaders([
                    'Authorization' => "Bearer {$this->secretKey}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->timeout(25)
                ->post($endpoint, $payload);

            $result = $response->json();

            Log::info('Flutterwave Payment Link Response:', [
                'status' => $response->status(),
                'gateway_status' => $result['status'] ?? null,
            ]);

            if ($response->successful() && isset($result['data']['link'])) {
                return [
                    'success'     => true,
                    'payment_url' => $result['data']['link'],
                    'reference'   => $data['reference'],
                    'raw'         => $result,
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initialize Flutterwave payment.',
                'raw'     => $result,
            ];
        } catch (\Throwable $e) {
            Log::error('Flutterwave Connection Exception:', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Network error connecting to Flutterwave: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment status using Flutterwave transaction ID
     *
     * @param string|int $transactionId
     * @return array
     */
    public function verifyTransaction($transactionId): array
    {
        $endpoint = "{$this->baseUrl}/transactions/{$transactionId}/verify";

        try {
            $response = Http
                ->withHeaders([
                    'Authorization' => "Bearer {$this->secretKey}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->timeout(20)
                ->get($endpoint);

            $result = $response->json();

            Log::info('Flutterwave verification response received', ['id' => $transactionId, 'http_status' => $response->status()]);

            if ($response->successful() && isset($result['data'])) {
                return [
                    'success' => true,
                    'status'  => strtoupper($result['data']['status'] ?? ''),
                    'data'    => $result['data'],
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Transaction verification failed.',
                'raw'     => $result,
            ];
        } catch (\Throwable $e) {
            Log::error('Flutterwave Verify Exception:', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Query / Verify transaction by merchant reference (tx_ref)
     *
     * @param string $txRef
     * @return array
     */
    public function verifyByReference(string $txRef): array
    {
        $endpoint = "{$this->baseUrl}/transactions/verify_by_reference";

        try {
            $response = Http
                ->withHeaders([
                    'Authorization' => "Bearer {$this->secretKey}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->timeout(20)
                ->get($endpoint, ['tx_ref' => $txRef]);

            $result = $response->json();

            Log::info('Flutterwave reference verification received', ['tx_ref' => $txRef, 'http_status' => $response->status()]);

            if ($response->successful() && isset($result['data'])) {
                return [
                    'success' => true,
                    'status'  => strtoupper($result['data']['status'] ?? ''),
                    'data'    => $result['data'],
                ];
            }

            return [
                'success' => false,
                'message' => $result['message'] ?? 'Reference verification failed.',
                'raw'     => $result,
            ];
        } catch (\Throwable $e) {
            Log::error('Flutterwave Verify Ref Exception:', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Validate webhook signature via secret hash
     *
     * @param string|null $signatureHeader
     * @return bool
     */
    public function verifyWebhookSignature(?string $signatureHeader): bool
    {
        if (empty($signatureHeader) || empty($this->secretHash)) {
            return false;
        }

        return hash_equals($this->secretHash, $signatureHeader);
    }
}
