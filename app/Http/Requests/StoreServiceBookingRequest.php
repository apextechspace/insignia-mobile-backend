<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'service_variant_id' => 'nullable|exists:service_variants,id',
            'scheduled_at' => 'nullable|date',
            'date' => 'nullable|date',
            'time' => 'nullable|string',
            'notes' => 'nullable|string',
            'payment_reference' => 'nullable|string',
            'payment_method' => 'nullable|string|in:flutterwave,bank_transfer',
            'receipt' => 'nullable|image|max:5120',
            // Metadata fields for dynamic forms
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date',
            'guests' => 'nullable|integer|min:1',
            'add_ons' => 'nullable|array',
            'request_details' => 'nullable|string',
            'preferred_date' => 'nullable|date',
            'attachments' => 'nullable|array',
            // Transport specific fields
            'flight_number' => 'nullable|string',
            'terminal' => 'nullable|string',
            'pickup_location' => 'nullable|string',
            'destination' => 'nullable|string',
        ];
    }
}
