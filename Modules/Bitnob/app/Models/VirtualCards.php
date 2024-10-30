<?php

namespace Modules\Bitnob\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Bitnob\Database\factories\VirtualCardsFactory;

class VirtualCards extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['user_id', 'card_number', 'expiry_date', 'cvv', 'card_id', 'raw_data'];
    
    protected static function newFactory(): VirtualCardsFactory
    {
        //return VirtualCardsFactory::new();
    }
    
}
