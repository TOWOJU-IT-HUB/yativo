<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AuthorizeRequestsOnly
{
    use AuthorizesRequests;
}