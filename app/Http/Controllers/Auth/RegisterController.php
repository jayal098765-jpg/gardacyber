<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.signup');
    }

    public function store(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        if (! $recaptcha->verify($request->input('g-recaptcha-response'))) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['recaptcha' => 'reCAPTCHA verification failed. Please try again.']);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'display_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => str($validated['email'])->before('@')->toString(),
            'display_name' => $validated['display_name'] ?? null,
            'workspace_name' => $validated['display_name'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('terms.accept');
    }
}
