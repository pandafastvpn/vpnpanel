<?php

namespace App\Middleware;

use App\Core\Auth;

class AuthMiddleware
{
    public function handle($params = [])
    {
        Auth::requireLogin();
    }
}
