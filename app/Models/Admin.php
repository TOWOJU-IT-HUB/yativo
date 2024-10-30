<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'google2fa_secret',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google2fa_secret',
    ];
}
