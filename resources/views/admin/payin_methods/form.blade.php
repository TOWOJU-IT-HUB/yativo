@php
    $method = $method ?? new \App\Models\PayinMethods();
    $countries = \DB::table('countries')->get();
    $currencies = \DB::table('currency_lists')->get();

    // echo json_encode($currencies); exit;
@endphp

<!-- Method Name and Gateway -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Method Name -->
    <div class="form-group">
        <label for="method_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Method Name</label>
        <input type="text" id="method_name" name="method_name" value="{{ old('method_name', $method->method_name) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('method_name')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Gateway -->
    <div class="form-group">
        <label for="gateway" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gateway</label>
        <input type="text" id="gateway" name="gateway" value="{{ old('gateway', $method->gateway) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('gateway')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Country and Currency -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Country -->
    <div class="form-group">
        <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Country</label>
        <select id="country" name="country" value="{{ old('country', $method->country) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            @foreach ($countries as $country)
                <option value="{{ $country->iso3 }}" @if ($method->country == $country->iso3) selected @endif>
                    {{ ucfirst($country->name) }}</option>
            @endforeach
        </select>
        @error('country')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Currency -->
    <div class="form-group">
        <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
        <select id="currency" name="currency" value="{{ old('currency', $method->currency) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            @foreach ($currencies as $currency)
                <option value="{{ $currency->currency_code }}" @if ($method->currency == $currency->currency_code) selected @endif>
                    {{ ucfirst("$currency->name $currency->currency_name ($currency->currency_code)") }}</option>
            @endforeach
        </select>
        @error('currency')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Payment Mode and Payment Mode Code -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Payment Mode -->
    <div class="form-group">
        <label for="payment_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment
            Mode</label>
        <input type="text" id="payment_mode" name="payment_mode"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('payment_mode')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Payment Mode Code -->
    <div class="form-group">
        <label for="payment_mode_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Payment Mode
            Code</label>
        <input type="text" id="payment_mode_code" name="payment_mode_code"
            value="{{ old('payment_mode_code', $method->payment_mode_code) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('payment_mode_code')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Charges Type and Fixed Charge -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Charges Type -->
    <div class="form-group">
        <label for="charges_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Charges
            Type</label>
        <select id="charges_type" name="charges_type"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
            <option value="fixed" {{ old('charges_type', $method->charges_type) == 'fixed' ? 'selected' : '' }}> Fixed
            </option>
            <option value="percentage"
                {{ old('charges_type', $method->charges_type) == 'percentage' ? 'selected' : '' }}>Percentage</option>
            <option value="combined" {{ old('charges_type', $method->charges_type) == 'combined' ? 'selected' : '' }}>
                Combined</option>
        </select>
        @error('charges_type')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Fixed Charge -->
    <div class="form-group">
        <label for="fixed_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fixed
            Charge</label>
        <input type="number" step="0.01" id="fixed_charge" name="fixed_charge"
            value="{{ old('fixed_charge', $method->fixed_charge) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('fixed_charge')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Float Charge and Settlement Time -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Float Charge -->
    <div class="form-group">
        <label for="float_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Float
            Charge</label>
        <input type="number" step="0.01" id="float_charge" name="float_charge"
            value="{{ old('float_charge', $method->float_charge) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('float_charge')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Settlement Time -->
    <div class="form-group">
        <label for="settlement_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Settlement Time
            (hours)</label>
        <input type="number" id="settlement_time" name="settlement_time"
            value="{{ old('settlement_time', $method->settlement_time) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('settlement_time')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Pro Fixed Charge and Pro Float Charge -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Pro Fixed Charge -->
    <div class="form-group">
        <label for="pro_fixed_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pro Fixed
            Charge</label>
        <input type="number" step="0.01" id="pro_fixed_charge" name="pro_fixed_charge"
            value="{{ old('pro_fixed_charge', $method->pro_fixed_charge) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('pro_fixed_charge')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Pro Float Charge -->
    <div class="form-group">
        <label for="pro_float_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pro Float
            Charge</label>
        <input type="number" step="0.01" id="pro_float_charge" name="pro_float_charge"
            value="{{ old('pro_float_charge', $method->pro_float_charge) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('pro_float_charge')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Minimum Deposit and Maximum Deposit -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Minimum Deposit -->
    <div class="form-group">
        <label for="minimum_deposit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Minimum
            Deposit</label>
        <input type="number" step="0.01" id="minimum_deposit" name="minimum_deposit"
            value="{{ old('minimum_deposit', $method->minimum_deposit) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('minimum_deposit')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Maximum Deposit -->
    <div class="form-group">
        <label for="maximum_deposit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Maximum
            Deposit</label>
        <input type="number" step="0.01" id="maximum_deposit" name="maximum_deposit"
            value="{{ old('maximum_deposit', $method->maximum_deposit) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('maximum_deposit')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Charge Limits -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Minimum Charge -->
    <div class="form-group">
        <label for="minimum_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Minimum
            Charge</label>
        <input type="number" step="0.01" id="minimum_charge" name="minimum_charge"
            value="{{ old('minimum_charge', $method->minimum_charge) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('minimum_charge')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Maximum Charge -->
    <div class="form-group">
        <label for="maximum_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Maximum
            Charge</label>
        <input type="number" step="0.01" id="maximum_charge" name="maximum_charge"
            value="{{ old('maximum_charge', $method->maximum_charge) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('maximum_charge')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Operating Hours -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Cutoff Hours Start -->
    <div class="form-group">
        <label for="cutoff_hrs_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cutoff Hours
            Start</label>
        <input type="text" id="cutoff_hrs_start" name="cutoff_hrs_start"
            value="{{ old('cutoff_hrs_start', $method->cutoff_hrs_start) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('cutoff_hrs_start')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Cutoff Hours End -->
    <div class="form-group">
        <label for="cutoff_hrs_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cutoff Hours
            End</label>
        <input type="text" id="cutoff_hrs_end" name="cutoff_hrs_end"
            value="{{ old('cutoff_hrs_end', $method->cutoff_hrs_end) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('cutoff_hrs_end')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>


<!-- Operating Hours -->
<div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
    <!-- Working Hours Start -->
    <div class="form-group">
        <label for="Working_hours_end" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Working
            Hours Start</label>
        <input type="tel" id="Working_hours_start" name="Working_hours_start"
            value="{{ old('Working_hours_start', $method->Working_hours_start) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('Working_hours_start')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>

    <!-- Working Hours End -->
    <div class="form-group">
        <label for="Working_hours_start" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Working
            Hours End</label>
        <input type="text" id="Working_hours_end" name="Working_hours_end"
            value="{{ old('Working_hours_end', $method->Working_hours_end) }}"
            class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">
        @error('Working_hours_end')
            <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Extra Data -->
<div class="form-group">
    <label for="required_extra_data" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Required Extra
        Data</label>
    <textarea id="required_extra_data" name="required_extra_data" rows="4"
        class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary">{{ old('required_extra_data', $method->required_extra_data) }}</textarea>
    @error('required_extra_data')
        <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
    @enderror
</div>
