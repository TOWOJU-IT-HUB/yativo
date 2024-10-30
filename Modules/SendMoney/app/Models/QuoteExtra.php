<?php

namespace Modules\SendMoney\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SendMoney\Database\factories\QuoteExtraFactory;

class QuoteExtra extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "quote_id",
        "transfer_purpose",
        "transfer_memo",
        "attachment",
        "metadata"
    ];


    protected $casts = [
        "metadata" => "object",
    ];

    public function quote()
    {
        return $this->belongsTo(SendQuote::class);
    }

    protected $hidden = [
        "created_at",
        "updated_at",
        "deleted_at",
        "id"
    ];
    
    protected static function newFactory(): QuoteExtraFactory
    {
        //return QuoteExtraFactory::new();
    }
}
