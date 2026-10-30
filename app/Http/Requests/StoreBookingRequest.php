<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guests' => 'nullable|integer|min:1|max:4',
            'guest_name' => 'nullable|string|max:255',
            'guest_phone' => 'nullable|string|min:7|max:40',
            'special_requests' => 'nullable|string|max:2000',
            'payment_reference' => 'nullable|string',
            'receipt' => 'nullable|image|max:5120',
        ];
    }
}
