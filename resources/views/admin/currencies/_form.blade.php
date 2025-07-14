<div class="grid gap-6 mb-6 md:grid-cols-2">
    <div>
        <label for="currency_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Currency Name</label>
        <input type="text" name="currency_name" id="currency_name" value="{{ old('currency_name', $currency->currency_name ?? '') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" required />
    </div>

    <div>
        <label for="currency_full_name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Full Name</label>
        <input type="text" name="currency_full_name" id="currency_full_name" value="{{ old('currency_full_name', $currency->currency_full_name ?? '') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" required />
    </div>

    <div>
        <label for="currency_icon" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Icon</label>
        <input type="text" name="currency_icon" id="currency_icon" value="{{ old('currency_icon', $currency->currency_icon ?? '') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" />
    </div>

    <div>
        <label for="currency_country" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Country</label>
        <input type="text" name="currency_country" id="currency_country" value="{{ old('currency_country', $currency->currency_country ?? '') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" required />
    </div>

    <div>
        <label for="decimal_places" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Decimal Places</label>
        <input type="number" name="decimal_places" id="decimal_places" value="{{ old('decimal_places', $currency->decimal_places ?? 2) }}" min="0" max="8" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" required />
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
    <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
        Submit
    </button>
</div>