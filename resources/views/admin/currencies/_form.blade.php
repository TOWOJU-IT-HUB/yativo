<div class="grid gap-6 mb-6 md:grid-cols-2">
    <div>
        <label for="currency_name" class="block mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Currency Name</label>
        <input type="text" name="currency_name" id="currency_name" value="{{ old('currency_name', $currency->currency_name ?? '') }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" required />
    </div>

    <div>
        <label for="currency_full_name" class="block mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Full Name</label>
        <input type="text" name="currency_full_name" id="currency_full_name" value="{{ old('currency_full_name', $currency->currency_full_name ?? '') }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" required />
    </div>

    <div>
        <label for="wallet" class="block mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Currency ISO 3 <small>USD, EUR, COP</small></label>
        <input type="text" name="wallet" id="wallet" value="{{ old('wallet', $currency->wallet ?? '') }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" required />
    </div>

    <div>
        <label for="currency_icon" class="block mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Icon</label>
        <input type="text" name="currency_icon" id="currency_icon" value="{{ old('currency_icon', $currency->currency_icon ?? '') }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" />
    </div>

    <div>
        <label for="currency_country" class="block mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Country</label>
        <input type="text" name="currency_country" id="currency_country" value="{{ old('currency_country', $currency->currency_country ?? '') }}" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" required />
    </div>

    <div>
        <label for="decimal_places" class="block mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">Decimal Places</label>
        <input type="number" name="decimal_places" id="decimal_places" value="{{ old('decimal_places', $currency->decimal_places ?? 2) }}" min="0" max="8" class="w-full rounded border-[1.5px] border-stroke bg-transparent px-5 py-3 font-normal text-black outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:text-white dark:focus:border-primary" required />
    </div>

    <div class="flex items-center mt-4">
        <input type="checkbox" name="can_hold_balance" id="can_hold_balance" value="1" {{ old('can_hold_balance', $currency->can_hold_balance ?? false) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
        <label for="can_hold_balance" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Can Hold Balance</label>
    </div>

    <div class="flex items-center mt-4">
        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $currency->is_active ?? true) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
        <label for="is_active" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Is Active</label>
    </div>
</div>

<div class="flex justify-end">
    <button type="submit" class="text-white bg-primary hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-primary dark:focus:ring-blue-800">
        Submit
    </button>
</div>