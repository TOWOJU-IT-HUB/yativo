<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operators extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'product_name', 'global', 'status', 'supports_pre_order',
        'sender_fee', 'sender_fee_percentage', 'discount_percentage', 'denomination_type',
        'recipient_currency_code', 'min_recipient_denomination', 'max_recipient_denomination',
        'sender_currency_code', 'min_sender_denomination', 'max_sender_denomination',
        'fixed_recipient_denominations', 'fixed_sender_denominations',
        'fixed_recipient_to_sender_map', 'logo_urls', 'brand_id', 'brand_name',
        'category_id', 'category_name', 'country_iso', 'country_name', 'country_flag_url',
        'redeem_instruction_concise', 'redeem_instruction_verbose', 'user_id_required'
    ];

    protected $casts = [
        'global' => 'boolean',
        'supports_pre_order' => 'boolean',
        'user_id_required' => 'boolean',
        'fixed_recipient_denominations' => 'array',
        'fixed_sender_denominations' => 'array',
        'fixed_recipient_to_sender_map' => 'array',
        'logo_urls' => 'array',
        'sender_fee' => 'decimal:2',
        'sender_fee_percentage' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'min_recipient_denomination' => 'decimal:2',
        'max_recipient_denomination' => 'decimal:2',
        'min_sender_denomination' => 'decimal:2',
        'max_sender_denomination' => 'decimal:2'
    ];

    /**
     * Retrieve and filter operators based on request parameters.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Pagination\LengthAwarePaginator
     */
    public static function getFilteredOperators(array $filters = [])
    {
        return self::query()
            ->when(!empty($filters), function (Builder $query) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (in_array($key, (new self)->getFillable())) {
                        $query->where($key, $value);
                    }
                }
            })
            ->paginate(per_page(50));
    }
}
