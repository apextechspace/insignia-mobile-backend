<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeliverPaymentPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public array $data = [],
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user || !$user->expo_push_token) {
            return;
        }

        if (!ExpoPushService::send($user->expo_push_token, $this->title, $this->message,
            $this->data ?: ['type' => 'payment_activity'])) {
            // ExpoPushService returns false for HTTP errors as well as failures;
            // throwing lets the queue driver retry instead of silently dropping.
            throw new RuntimeException('Expo declined payment push delivery.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Payment push exhausted retries', [
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
