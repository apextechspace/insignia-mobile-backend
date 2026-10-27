<?php

namespace App\Actions;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateServiceBookingAction
{
    public function execute(array $data): ServiceBooking
    {
        $service = Service::findOrFail($data['service_id']);

        // 1. Validate Variant Requirements
        $variant = null;
        if ($service->requires_variant) {
            if (empty($data['service_variant_id'])) {
                throw ValidationException::withMessages(['service_variant_id' => 'A variant selection is required for this service.']);
            }
            $variant = ServiceVariant::where('service_id', $service->id)->findOrFail($data['service_variant_id']);
        }

        // 2. Determine Price
        $totalPrice = $variant ? $variant->price : ($service->price ?? 0);

        // 3. Prepare Metadata based on Type
        $metadata = $this->prepareMetadata($service, $data);

        // 4. Create Booking
        return ServiceBooking::create([
            'user_id' => Auth::id() ?? $data['user_id'], // Fallback for testing/seeding
            'service_id' => $service->id,
            'service_variant_id' => $variant?->id,
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'date' => $data['date'] ?? null,
            'time' => $data['time'] ?? null,
            'notes' => $data['notes'] ?? null,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'payment_reference' => $data['payment_reference'] ?? null,
            'receipt_url' => $data['receipt_url'] ?? null,
            'payment_method' => !empty($data['receipt_url']) ? 'bank_transfer' : null,
            'metadata' => $metadata,
        ]);
    }

    protected function prepareMetadata(Service $service, array $data): array
    {
        $metadata = [];

        switch ($service->type) {
            case 'booking':
                // Specific logic for Transport category
                if ($service->category === 'Transport') {
                    $metadata['flight_number'] = $data['flight_number'] ?? null;
                    $metadata['terminal'] = $data['terminal'] ?? null;
                    $metadata['pickup_location'] = $data['pickup_location'] ?? null;
                    $metadata['destination'] = $data['destination'] ?? null;
                }
                break;

            case 'reservation':
                $metadata['check_in'] = $data['check_in'] ?? null;
                $metadata['check_out'] = $data['check_out'] ?? null;
                $metadata['guests'] = $data['guests'] ?? 1;
                $metadata['add_ons'] = $data['add_ons'] ?? [];
                break;

            case 'request':
                $metadata['request_details'] = $data['request_details'] ?? '';
                $metadata['preferred_date'] = $data['preferred_date'] ?? null;
                $metadata['attachments'] = $data['attachments'] ?? [];
                break;
        }

        return $metadata;
    }
}
