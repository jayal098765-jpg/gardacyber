<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.site_key') && (bool) config('services.recaptcha.secret_key');
    }

    public function minimumScore(): float
    {
        return (float) config('services.recaptcha.min_score', 0.5);
    }

    /**
     * Verify a reCAPTCHA v3 response token.
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

        if (! $response->successful()) {
            return false;
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            return false;
        }

        // reCAPTCHA v3 returns a score between 0 and 1; enforce the minimum when present.
        if (isset($data['score']) && (float) $data['score'] < $this->minimumScore()) {
            return false;
        }

        return true;
    }
}
