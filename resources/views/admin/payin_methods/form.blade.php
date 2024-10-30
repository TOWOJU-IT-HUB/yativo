@php
    $method = $method ?? new \App\Models\PayinMethods();
@endphp

<div>
    <label for="method_name" class="block dark:text-white">Method Name</label>
    <input type="text" id="method_name" name="method_name" value="{{ old('method_name', $method->method_name) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('method_name')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="gateway" class="block dark:text-white">Gateway</label>
    <input type="text" id="gateway" name="gateway" value="{{ old('gateway', $method->gateway) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('gateway')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="country" class="block dark:text-white">Country</label>
    <input type="text" id="country" name="country" value="{{ old('country', $method->country) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('country')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="currency" class="block dark:text-white">Currency</label>
    <input type="text" id="currency" name="currency" value="{{ old('currency', $method->currency) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('currency')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="payment_mode" class="block dark:text-white">Payment Mode</label>
    <select id="payment_mode" name="payment_mode"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
        <option value="online" {{ old('payment_mode', $method->payment_mode) == 'online' ? 'selected' : '' }}>Online
        </option>
        <option value="offline" {{ old('payment_mode', $method->payment_mode) == 'offline' ? 'selected' : '' }}>Offline
        </option>
    </select>
    @error('payment_mode')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="charges_type" class="block dark:text-white">Charges Type</label>
    <select id="charges_type" name="charges_type"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
        <option value="fixed" {{ old('charges_type', $method->charges_type) == 'fixed' ? 'selected' : '' }}>Fixed
        </option>
        <option value="percentage" {{ old('charges_type', $method->charges_type) == 'percentage' ? 'selected' : '' }}>
            Percentage</option>
    </select>
    @error('charges_type')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="fixed_charge" class="block dark:text-white">Fixed Charge</label>
    <input type="number" id="fixed_charge" name="fixed_charge"
        value="{{ old('fixed_charge', $method->fixed_charge) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('fixed_charge')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="percentage_charge" class="block dark:text-white">Percentage Charge</label>
    <input type="number" id="percentage_charge" name="percentage_charge"
        value="{{ old('percentage_charge', $method->percentage_charge) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('percentage_charge')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="min_amount" class="block dark:text-white">Minimum Amount</label>
    <input type="number" id="min_amount" name="min_amount" value="{{ old('min_amount', $method->min_amount) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('min_amount')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="max_amount" class="block dark:text-white">Maximum Amount</label>
    <input type="number" id="max_amount" name="max_amount" value="{{ old('max_amount', $method->max_amount) }}"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
    @error('max_amount')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="status" class="block dark:text-white">Status</label>
    <select id="status" name="status"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">
        <option value="active" {{ old('status', $method->status) == 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ old('status', $method->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>
    @error('status')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <label for="description" class="block dark:text-white">Description</label>
    <textarea id="description" name="description"
        class="mt-1 block w-full border-gray-300 dark:border-gray-700 rounded-md shadow-sm">{{ old('description', $method->description) }}</textarea>
    @error('description')
        <div class="text-red-500">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <button type="submit" class="mt-2 w-full bg-blue-500 text-white rounded-md p-2">Save</button>
</div>
