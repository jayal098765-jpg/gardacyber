<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('app.ai-settings', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['nullable', 'string', 'max:50'],
            'ai_base_url' => ['nullable', 'url', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_model' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'AI settings saved.',
                'settings' => $request->user()->only(['ai_provider', 'ai_base_url', 'ai_model']),
            ]);
        }

        return back()->with('status', 'AI settings saved.');
    }

    public function test(Request $request, AiService $ai): JsonResponse
    {
        $result = $ai->testConnection($request->user(), $request->only([
            'ai_provider',
            'ai_base_url',
            'ai_api_key',
            'ai_model',
        ]));

        $status = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $status);
    }
}
