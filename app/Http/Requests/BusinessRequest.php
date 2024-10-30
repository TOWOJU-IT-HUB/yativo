<?php

namespace App\Http\Requests;


use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;


class BusinessRequest extends FormRequest
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
            'business_legal_name' => 'sometimes|string|max:255',
            'business_operating_name' => 'sometimes|string|max:255',
            'incorporation_country' => 'sometimes|string|max:255',
            'business_operation_address' => 'sometimes|string|max:255',
            'entity_type' => 'sometimes|string|max:255',
            'business_registration_number' => 'sometimes|string|max:255',
            'business_tax_id' => 'sometimes|string|max:255',
            'business_industry' => 'sometimes|string|max:255',
            'business_sub_industry' => 'sometimes|string|max:255',
            'business_description' => 'sometimes|string',
            'business_website' => 'sometimes|url',
            'account_purpose' => 'sometimes|string',
            'plan_of_use' => 'sometimes|string|max:255',
            'is_pep_owner' => 'sometimes|boolean',
            'is_ofac_sanctioned' => 'sometimes|boolean',
            'shareholder_count' => 'sometimes|integer',
            'shareholders' => 'sometimes|array',
            'directors_count' => 'sometimes|integer',
            'directors' => 'sometimes|array',
            'administrator' => 'sometimes|array',
            'documents' => 'sometimes|array',
            'use_case' => 'sometimes|string|max:255',
            'estimated_monthly_transactions' => 'sometimes|string|max:255',
            'estimated_monthly_payments' => 'sometimes|string|max:255',
            'is_self_use' => 'sometimes|boolean',
            'terms_agreed_date' => 'sometimes|date',
            'other_datas' => 'sometimes|array'
        ];
    }

    
    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors(); // Get error messages

        $response = get_error_response([
            'errors' => $errors->messages()
        ], 422); // Customize the status code as needed

        throw new HttpResponseException($response);
    }
}
