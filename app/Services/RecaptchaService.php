<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.site_key') && (bool) config('services.recaptcha.secret_key');
    }

    /**
     * Verify a reCAPTCHA v2 response token.
     * Returns true when disabled (no keys configured) or when the token is valid.
     */
    public function verify(?string $token): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! $token) {
            return false;
        }

        $response = Http::connectTimeout(4)
            ->timeout(8)
            ->asForm()
            ->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
            ]);

        return $response->successful() && (bool) $response->json('success');
    }
}
