<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('app.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'workspace_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'ai_provider' => ['nullable', 'string', 'max:50'],
            'ai_base_url' => ['nullable', 'url', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'telegram_webhook_url' => ['nullable', 'url', 'max:255'],
        ]);

        $request->user()->update($validated);

        return back()->with('status', 'Profile updated.');
    }

    public function telegramTest(Request $request, TelegramService $telegram): JsonResponse
    {
        $result = $telegram->sendTestAlert($request->user(), $request->only([
            'signal',
            'asset',
            'bot_token',
            'chat_id',
            'webhook_url',
        ]));

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);

        return back()->with('status', 'Password updated.');
    }
}
