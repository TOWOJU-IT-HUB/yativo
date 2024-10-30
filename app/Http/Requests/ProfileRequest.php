<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes',
            'bussinessName' => 'sometimes',  
            'idNumber' => 'nullable|string',
            'idType' => 'nullable|string',
            'firstName' => 'nullable|string',
            'lastName' => 'nullable|string',
            'phoneNumber' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'zipCode' => 'nullable|string',
            'street' => 'nullable|string',
            'additionalInfo' => 'nullable|string',
            'houseNumber' => 'nullable|string',
            'verificationDocument' => 'nullable|file',
        ];
    }
}
