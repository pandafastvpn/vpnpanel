<?php

namespace App\Middleware;

use App\Core\Auth;

class AdminMiddleware
{
    public function handle($params = [])
    {
        Auth::requireAdmin();
    }
}
