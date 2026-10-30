<?php

namespace App\Support\DataCollection;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

// Whether a rejected Bearer token is a real token that has run out (as
// opposed to missing, revoked or never valid).
class TokenState
{
    public static function isExpired(Request $request): bool
    {
        $plain = $request->bearerToken();
        if (! $plain) {
            return false;
        }

        $token = PersonalAccessToken::findToken($plain);
        if (! $token) {
            return false;
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return true;
        }

        $minutes = config('sanctum.expiration');

        return $minutes && $token->created_at && $token->created_at->lte(now()->subMinutes($minutes));
    }
}
