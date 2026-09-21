<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(title: 'YazCRM API', version: '1.0.0')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum token from POST /api/auth/login',
)]
abstract class Controller
{
    use AuthorizesRequests;
}
