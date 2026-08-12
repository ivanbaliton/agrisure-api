<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotifyFarmersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow authorized users (like logged-in Barangay officials) to perform this action
        return true; 
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Ensure farmer_ids is a non-empty array and the IDs actually exist in the users table
            'farmer_ids' => ['required', 'array', 'min:1'],
            'farmer_ids.*' => ['required', 'integer', 'exists:users,id'],
            
            // Ensure channels is sent as an array and contains at least one chosen medium
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', 'in:push,email,sms'],
        ];
    }

    /**
     * Customize the validation error messages.
     */
    public function messages(): array
    {
        return [
            'farmer_ids.required' => 'Please select at least one farmer to notify.',
            'farmer_ids.*.exists' => 'One or more selected farmers could not be found.',
            'channels.required' => 'You must choose at least one notification channel (App, Email, or SMS).',
            'channels.*.in' => 'The selected notification channel is invalid.',
        ];
    }
}