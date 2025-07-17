<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reloadly extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'operatorId', 'name', 'bundle', 'data', 'pin', 'supportsLocalAmounts',
        'denominationType', 'senderCurrencyCode', 'senderCurrencySymbol',
        'destinationCurrencyCode', 'destinationCurrencySymbol', 'commission',
        'internationalDiscount', 'localDiscount', 'mostPopularAmount',
        'minAmount', 'maxAmount', 'localMinAmount', 'localMaxAmount', 'country',
        'fx', 'logoUrls', 'fixedAmounts', 'fixedAmountsDescriptions',
        'localFixedAmounts', 'localFixedAmountsDescriptions',
        'suggestedAmounts', 'suggestedAmountsMap', 'promotions',
    ];
}
