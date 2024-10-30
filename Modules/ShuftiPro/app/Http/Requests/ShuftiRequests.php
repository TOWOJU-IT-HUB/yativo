<?php

namespace Modules\ShuftiPro\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShuftiRequests extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'address_proof'     => 'required|in:utility_bill,passport,bank_statement',
            'proof'             => 'required',
            'additional_proof'  => 'required',
            'document_number'   => 'required',
            'expiry_date'       => 'required',
            'issue_date'        => 'required'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }
}
