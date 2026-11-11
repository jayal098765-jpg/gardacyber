<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiService;
use App\Services\SecurityRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiChatController extends Controller
{
    public const HISTORY_SESSION_KEY = 'ai_chat_history';

    public function __construct(
        protected AiService $ai,
        protected SecurityRatingService $ratingService
    ) {}

    public function index(): View
    {
        return view('app.ai-chat');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $message = trim($validated['message']);

        $context = $this->buildWorkspaceContext($user);

        $history = $request->session()->get(self::HISTORY_SESSION_KEY, []);
        $result = $this->ai->chat($user, $message, $context, $history);

        // Keep a bounded conversation history in session.
        $history[] = ['role' => 'user', 'content' => $message];
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];
        $request->session()->put(self::HISTORY_SESSION_KEY, array_slice($history, -20));

        return response()->json($result);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->session()->forget(self::HISTORY_SESSION_KEY);

        return response()->json(['status' => 'success']);
    }

    /**
     * @return array{
     *     groups: array<int, array<string, mixed>>,
     *     findings: array<int, array<string, mixed>>
     * }
     */
    protected function buildWorkspaceContext(User $user): array
    {
        $groups = $user->groups()
            ->withCount('assets')
            ->get()
            ->map(fn ($group): array => [
                'root_domain' => $group->root_domain,
                'assets_count' => (int) $group->assets_count,
                'open_vulns' => $group->openVulnerabilities()->count(),
                'open_incidents' => $group->openIncidents()->count(),
                'vuln_grade' => $this->ratingService->rateGroup($group)['vuln']['grade'] ?? '?',
                'incident_grade' => $this->ratingService->rateGroup($group)['incident']['grade'] ?? '?',
                'last_scanned_at' => $group->last_scanned_at?->diffForHumans(),
            ])
            ->values()
            ->all();

        $findings = $user->groups()
            ->with(['findings' => fn ($q) => $q->open()->with('asset')->latest('last_seen_at')->take(6)])
            ->get()
            ->flatMap(fn ($group) => $group->findings)
            ->take(15)
            ->map(fn ($finding): array => [
                'severity' => $finding->severity,
                'title' => $finding->title,
                'category' => $finding->category,
                'hostname' => $finding->asset?->hostname,
                'status' => $finding->status,
            ])
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'findings' => $findings,
        ];
    }
}
