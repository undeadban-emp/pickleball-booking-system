<?php

namespace App\Http\Requests;

use App\Models\OperatingHours;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'court_slot_ids' => ['required', 'array', 'min:1', 'max:'.(OperatingHours::current()->max_customer_booking_hours ?? 24)],
            'court_slot_ids.*' => ['integer', 'distinct', 'exists:court_slots,id'],
        ];
    }
}
