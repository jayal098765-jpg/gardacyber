<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\OwaspCategory;
use App\Services\AiService;
use App\Services\RemediationAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IssueController extends Controller
{
    public function aiExplain(Request $request, Finding $finding, AiService $ai): JsonResponse
    {
        abort_unless($request->user()->groups()->whereKey($finding->asset_group_id)->exists(), 404);

        $finding->loadMissing(['group', 'asset']);
        $explanation = $ai->explainFinding($finding, $request->user());

        return response()->json($explanation);
    }

    public function show(Request $request, Finding $finding, RemediationAdvisor $advisor): View
    {
        abort_unless($request->user()->groups()->whereKey($finding->asset_group_id)->exists(), 404);

        $finding->load(['group', 'asset']);

        return view('app.findings.show', [
            'finding' => $finding,
            'guide' => $advisor->guideFor($finding),
            'owaspLabels' => OwaspCategory::labels(),
        ]);
    }

    public function update(Request $request, Finding $finding): RedirectResponse
    {
        abort_unless($request->user()->groups()->whereKey($finding->asset_group_id)->exists(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Finding::STATUSES)],
        ]);
        $finding->update($validated);

        return back()->with('status', 'Finding updated.');
    }

    public function index(Request $request): View
    {
        $query = Finding::query()
            ->with(['group', 'asset'])
            ->where('status', Finding::STATUS_OPEN)
            ->whereIn('asset_group_id', $request->user()->groups()->pluck('id'));

        if ($request->filled('kind') && in_array($request->query('kind'), Finding::KINDS, true)) {
            $query->where('kind', $request->query('kind'));
        }

        if ($request->filled('sev') && in_array($request->query('sev'), Finding::SEVERITIES, true)) {
            $query->where('severity', $request->query('sev'));
        }

        $findings = $query->orderByDesc('last_seen_at')->paginate(12)->withQueryString();

        return view('app.issues', [
            'findings' => $findings,
            'kind' => $request->query('kind'),
            'severity' => $request->query('sev'),
        ]);
    }
}
