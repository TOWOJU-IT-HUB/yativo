@extends('layouts.admin')

@section('title', 'Create Payout Method')
@section('header', 'Create New Payout Method')

@section('content')
<div class="container mx-auto px-4 py-8">
    <p class="text-2xl dark:text-white">Add New Payout Method</p>
    <form action="{{ route('admin.payout-methods.store') }}" method="POST" class="bg-white dark:bg-boxdark p-6 shadow rounded">
        @csrf
        <div class="p-6.5">
            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Method Name -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Method Name</label>
                    <input 
                        type="text" 
                        name="method_name" 
                        placeholder="Enter method name" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Gateway -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Gateway</label>
                    <input 
                        type="text" 
                        name="gateway" 
                        placeholder="Enter gateway" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Country -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Country</label>
                    <input 
                        type="text" 
                        name="country" 
                        placeholder="Enter country" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Currency -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Currency</label>
                    <input 
                        type="text" 
                        name="currency" 
                        placeholder="Enter currency" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Payment Mode -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Payment Mode</label>
                    <input 
                        type="text" 
                        name="payment_mode" 
                        placeholder="Enter payment mode" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Charges Type -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Charges Type</label>
                    <input 
                        type="text" 
                        name="charges_type" 
                        placeholder="Enter charges type" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Fixed Charge -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Fixed Charge</label>
                    <input 
                        type="number" 
                        name="fixed_charge" 
                        placeholder="Enter fixed charge" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Float Charge -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Float Charge</label>
                    <input 
                        type="number" 
                        name="float_charge" 
                        placeholder="Enter float charge" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Estimated Delivery -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Estimated Delivery</label>
                    <input 
                        type="text" 
                        name="estimated_delivery" 
                        placeholder="Enter estimated delivery" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Pro Fixed Charge -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Pro Fixed Charge</label>
                    <input 
                        type="number" 
                        name="pro_fixed_charge" 
                        placeholder="Enter pro fixed charge" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Pro Float Charge -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Pro Float Charge</label>
                    <input 
                        type="number" 
                        name="pro_float_charge" 
                        placeholder="Enter pro float charge" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Minimum Withdrawal -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Minimum Withdrawal</label>
                    <input 
                        type="number" 
                        name="minimum_withdrawal" 
                        placeholder="Enter minimum withdrawal" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Maximum Withdrawal -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Maximum Withdrawal</label>
                    <input 
                        type="number" 
                        name="maximum_withdrawal" 
                        placeholder="Enter maximum withdrawal" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Minimum Charge -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Minimum Charge</label>
                    <input 
                        type="number" 
                        name="minimum_charge" 
                        placeholder="Enter minimum charge" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Maximum Charge -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Maximum Charge</label>
                    <input 
                        type="number" 
                        name="maximum_charge" 
                        placeholder="Enter maximum charge" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>

                <!-- Cutoff Hours Start -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Cutoff Hours Start</label>
                    <input 
                        type="time" 
                        name="cutoff_hrs_start" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>


            <!-- Operating Hours -->
            <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Working Hours Start -->
                <div class="form-group">
                    <label for="Working_hours_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Exchange Rate Float</label>
                    <input type="tel" id="exchange_rate_float" name="exchange_rate_float"
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                    @error('exchange_rate_float')
                        <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Working Hours End -->
                <div class="form-group">
                    <label for="base_currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base Currency</label>
                    <input type="text" id="base_currency" name="base_currency"
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
                    @error('base_currency')
                        <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>


            <div class="mb-4.5 flex flex-col gap-6 xl:flex-row">
                <!-- Cutoff Hours End -->
                <div class="w-full xl:w-1/2">
                    <label class="mb-3 block text-sm font-medium text-black dark:text-white">Cutoff Hours End</label>
                    <input 
                        type="time" 
                        name="cutoff_hrs_end" 
                        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary"
                    >
                </div>
            </div>

        </div>

        <div class="mt-6 flex justify-between">
            <a href="{{ route('admin.payout-methods.index') }}" class="px-4 py-2 hover:bg-red-700 bg-danger text-white rounded">Cancel</a>
            <button type="submit" class="px-4 py-2 hover:bg-primary bg-primary text-white rounded">Save Payout Method</button>
        </div>
    </form>
</div>
@endsection
