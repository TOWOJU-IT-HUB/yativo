<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_form',
        'configs',
        'user_id'
    ];

    protected $casts = [
        'configs' => 'array'
    ];

    protected $hidden = [
        'id',
        'user_id',
        'application_form',
    ];

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
