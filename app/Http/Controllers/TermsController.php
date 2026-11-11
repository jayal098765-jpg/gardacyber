<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TermsController extends Controller
{
    public function accept(): View
    {
        return view('auth.terms-accept');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'accept_terms' => ['required', 'accepted'],
            'accept_authorized' => ['required', 'accepted'],
        ]);

        $request->user()->update(['terms_accepted_at' => now()]);

        return redirect()->route('dashboard');
    }
}
