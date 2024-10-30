@extends('layouts.admin')

@section('content')
<?php
if (is_string($business->shareholders)) {
    $business->shareholders = json_decode($business->shareholders, true);
}
if (is_string($business->directors)) {
    $business->directors = json_decode($business->directors, true);
}
if (is_string($business->documents)) {
    $business->documents = json_decode($business->documents, true);
}
?>
<div class="container mx-auto py-8 dark:bg-gray-950/75 p-4 dark:text-white">
    {{-- <h1 class="text-3xl font-bold mb-6">Business Details</h1> --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
        <div class="bg-white dark:bg-gray-950/75 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Basic Information</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Legal Name:</p>
                    <p class="font-bold">{{ $business->business_legal_name }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Operating Name:</p>
                    <p class="font-bold">{{ $business->business_operating_name }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Incorporation Country:</p>
                    <p class="font-bold">{{ $business->incorporation_country }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Operation Address:</p>
                    <p class="font-bold">{{ $business->business_operation_address }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Entity Type:</p>
                    <p class="font-bold">{{ $business->entity_type }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Registration Number:</p>
                    <p class="font-bold">{{ $business->business_registration_number }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Tax ID:</p>
                    <p class="font-bold">{{ $business->business_tax_id }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Industry:</p>
                    <p class="font-bold">{{ $business->business_industry }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Sub Industry:</p>
                    <p class="font-bold">{{ $business->business_sub_industry }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Website:</p>
                    <p class="font-bold">{{ $business->business_website }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-950/75 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Additional Information</h2>
            <div class="grid grid-cols-1 gap-4">
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Business Description:</p>
                    <p class="font-bold">{{ $business->business_description }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Account Purpose:</p>
                    <p class="font-bold">{{ $business->account_purpose }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Plan of Use:</p>
                    <p class="font-bold">{{ $business->plan_of_use }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2 flex justify-between">
                    <p class="text-gray-600 dark:text-gray-400">Is PEP Owner:</p>
                    <p class="font-bold">{{ $business->is_pep_owner ? 'Yes' : 'No' }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2 flex justify-between">
                    <p class="text-gray-600 dark:text-gray-400">Is OFAC Sanctioned:</p>
                    <p class="font-bold">{{ $business->is_ofac_sanctioned ? 'Yes' : 'No' }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2 flex justify-between">
                    <p class="text-gray-600 dark:text-gray-400">Shareholder Count:</p>
                    <p class="font-bold">{{ $business->shareholder_count }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Use Case:</p>
                    <p class="font-bold">{{ $business->use_case }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Estimated Monthly Transactions:</p>
                    <p class="font-bold">{{ $business->estimated_monthly_transactions }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Estimated Monthly Payments:</p>
                    <p class="font-bold">{{ $business->estimated_monthly_payments }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2 flex justify-between">
                    <p class="text-gray-600 dark:text-gray-400">Is Self Use:</p>
                    <p class="font-bold">{{ $business->is_self_use ? 'Yes' : 'No' }}</p>
                </div>
                <div class="border border-gray-100 rounded p-2">
                    <p class="text-gray-600 dark:text-gray-400">Terms Agreed Date:</p>
                    <p class="font-bold">{{ $business->terms_agreed_date }}</p>
                </div>
            </div>
        </div>
    </div>



    @if (!empty($business->shareholders))
        <div class="mt-8 shadow-lg rounded-lg bg-white dark:bg-gray-950/75 p-4 mb-3">
            <h2 class="text-xl font-bold mb-4">Shareholders</h2>
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-200 dark:bg-gray-950/75 text-left">
                        @foreach (array_keys(reset($business->shareholders)) as $key)
                            <th class="px-4 py-2">{{ ucfirst($key) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($business->shareholders as $shareholder)
                        <tr class="dark:bg-gray-800">
                            @foreach ($shareholder as $value)
                                <td class="border px-4 py-2">
                                    @if (is_array($value))
                                        @foreach ($value as $v)
                                            {{ $v }}
                                        @endforeach
                                    @else
                                        {{ $value ?? '-' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (!empty($business->directors))
        <div class="mt-8 shadow-lg rounded-lg bg-white dark:bg-gray-950/75 p-4 mb-3">
            <h2 class="text-xl font-bold mb-4">Directors</h2>
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-200 dark:bg-gray-950/75 text-left">
                        @foreach (array_keys(reset($business->directors)) as $key)
                            <th class="px-4 py-2">{{ ucfirst($key) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($business->directors as $director)
                        <tr class="dark:bg-gray-800">
                            @foreach ($director as $value)
                                <td class="border px-4 py-2">
                                    @if (is_string($value))
                                        {{ $value ?? '-' }}
                                    @elseif(is_array($value) && isset($value['url']))
                                        <a href="{{ $value['url'] }}" target="_blank"
                                            class="text-blue-500 hover:underline">View File</a>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (!empty($business->documents))
        <div class="mt-8 shadow-lg rounded-lg bg-white dark:bg-gray-950/75 p-4 mb-3">
            <h2 class="text-xl font-bold mb-4">Documents</h2>
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-200 dark:bg-gray-950/75 text-left">
                        @foreach (array_keys(reset($business->documents)) as $key)
                            <th class="px-4 py-2">{{ ucfirst($key) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($business->documents as $director)
                        <tr class="dark:bg-gray-800">
                            @foreach ($director as $value)
                                <td class="border px-4 py-2">
                                    @if (filter_var($value, FILTER_VALIDATE_URL))
                                        <a href="{{ $value }}" target="_blank"
                                            class="text-blue-500 hover:underline">View File</a>
                                    @elseif (is_string($value))
                                        {{ ucwords(str_replace('_', ' ', $value)) ?? '-' }}
                                    @elseif(is_array($value) && isset($value['url']))
                                        <a href="{{ $value['url'] }}" target="_blank"
                                            class="text-blue-500 hover:underline">View File</a>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

@endsection
