<div class="bg-gray-50 dark:bg-slate-800 rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($array as $key => $value)
                <tr class="hover:bg-gray-100 dark:hover:bg-slate-700">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        {{ ucwords(str_replace('_', ' ', $key)) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                        @if(is_array($value))
                            @include('components.recursive-array-table', ['array' => $value])
                        @else
                            {{ $value }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
